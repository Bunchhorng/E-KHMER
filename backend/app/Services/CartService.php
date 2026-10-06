<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(private InventoryService $inventory)
    {
    }

    /**
     * Resolve (or create) the cart for a user or guest session.
     */
    public function forUser(?User $user, ?string $sessionId): Cart
    {
        if ($user !== null) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        } else {
            if ($sessionId === null) {
                throw ValidationException::withMessages(['message' => 'Unable to resolve cart']);
            }
            $cart = Cart::firstOrCreate(['session_id' => $sessionId]);
        }

        return $cart;
    }

    /**
     * Load all cart items with variant and product image data.
     */
    public function items(Cart $cart): Collection
    {
        return $cart->items()->with(['variant.product.images'])->get();
    }

    /**
     * Add a variant to the cart, capping quantity by available stock.
     */
    public function add(Cart $cart, int $variantId, int $quantity = 1): Cart
    {
        // The product has to be purchasable too: a variant can stay active while
        // its product is unpublished or owned by a shop that is no longer active.
        $variant = ProductVariant::where('id', $variantId)
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->active())
            ->first();

        if ($variant === null) {
            throw ValidationException::withMessages(['message' => 'Variant not found']);
        }

        $available = $this->inventory->available($variantId);

        if ($available <= 0) {
            throw ValidationException::withMessages(['message' => 'Variant is out of stock']);
        }

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => ["Only {$available} item(s) are currently available."],
            ]);
        }

        DB::transaction(function () use ($cart, $variantId, $quantity): void {
            $item = $cart->items()->where('product_variant_id', $variantId)->lockForUpdate()->first();

            if ($item === null) {
                $cart->items()->create([
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                ]);
            } else {
                $available = $this->inventory->available($variantId);
                $newQty = (int) $item->quantity + $quantity;

                if ($newQty > $available) {
                    throw ValidationException::withMessages([
                        'quantity' => ["Only {$available} item(s) are currently available."],
                    ]);
                }

                $item->update(['quantity' => $newQty]);
            }
        });

        return $cart->load('items.variant.product.images');
    }

    /**
     * Update a cart item quantity (removing when zero or negative).
     */
    public function update(Cart $cart, int $cartItemId, int $quantity): Cart
    {
        $item = $cart->items()->where('id', $cartItemId)->first();

        if ($item === null || (int) $item->cart_id !== (int) $cart->id) {
            throw ValidationException::withMessages(['message' => 'Not found']);
        }

        if ($quantity <= 0) {
            $item->delete();

            return $cart->load('items.variant.product.images');
        }

        $variant = ProductVariant::query()
            ->whereKey($item->product_variant_id)
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->active())
            ->first();

        if ($variant === null) {
            throw ValidationException::withMessages([
                'message' => ['This product is no longer available. Remove it from your cart to continue.'],
            ]);
        }

        $available = $this->inventory->available((int) $item->product_variant_id);
        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => ["Only {$available} item(s) are currently available."],
            ]);
        }

        $item->update(['quantity' => $quantity]);

        return $cart->load('items.variant.product.images');
    }

    /**
     * Remove an item from the cart.
     */
    public function remove(Cart $cart, int $cartItemId): void
    {
        $item = $cart->items()->where('id', $cartItemId)->first();

        if ($item !== null) {
            $item->delete();
        }
    }

    /**
     * Remove all items from the cart.
     */
    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }

    /**
     * Compute cart totals.
     *
     * @return array{items_count: int, subtotal: float, tax_amount: float, discount_applicable: float, total: float}
     */
    public function totals(Cart $cart): array
    {
        $subtotal = 0.0;
        $itemsCount = 0;

        foreach ($cart->items()->with('variant.product')->get() as $item) {
            $variant = $item->variant;

            if ($variant === null) {
                continue;
            }

            $unit = $variant->price !== null ? round((float) $variant->price, 2) : round((float) ($variant->product?->price ?? 0), 2);
            $subtotal += round($unit * (int) $item->quantity, 2);
            $itemsCount += (int) $item->quantity;
        }

        $subtotal = round($subtotal, 2);
        $taxAmount = round($subtotal * 0.10, 2);

        return [
            'items_count' => $itemsCount,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_applicable' => 0.0,
            'total' => round($subtotal + $taxAmount, 2),
        ];
    }

    /**
     * Migrate a guest (session) cart's items into the user's cart.
     */
    public function mergeGuestIntoUser(User $user, ?string $sessionId): void
    {
        if ($sessionId === null) {
            return;
        }

        $guestCart = Cart::where('session_id', $sessionId)->first();

        if ($guestCart === null) {
            return;
        }

        $userCart = Cart::firstOrCreate(['user_id' => $user->id]);

        DB::transaction(function () use ($userCart, $guestCart): void {
            foreach ($guestCart->items as $item) {
                // A guest cart can become stale while the customer is signing in:
                // products may be unpublished, variants deactivated, or stock
                // consumed. Never migrate an item that the customer could not add
                // today, and always cap merged quantities at live availability.
                $variant = ProductVariant::query()
                    ->whereKey($item->product_variant_id)
                    ->where('is_active', true)
                    ->whereHas('product', fn ($q) => $q->active())
                    ->first();

                if ($variant === null) {
                    continue;
                }

                $available = $this->inventory->available($variant->id);

                if ($available <= 0) {
                    continue;
                }

                $existing = $userCart->items()
                    ->where('product_variant_id', $variant->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing === null) {
                    $userCart->items()->create([
                        'product_variant_id' => $variant->id,
                        'quantity' => min((int) $item->quantity, $available),
                    ]);
                } else {
                    $existing->update([
                        'quantity' => min((int) $existing->quantity + (int) $item->quantity, $available),
                    ]);
                }
            }

            $guestCart->items()->delete();
        });
    }
}
