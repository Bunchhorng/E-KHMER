<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Resolve the shop that owns a variant. A variant always belongs to exactly
     * one product, and a product to exactly one shop, so the shop is derived
     * rather than passed in — this keeps the stock ledger authoritative even
     * when callers have no shop context (guest checkout, cron expiry, refunds).
     */
    public function shopIdForVariant(int $variantId): ?int
    {
        $shopId = DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('product_variants.id', $variantId)
            ->value('products.shop_id');

        return $shopId === null ? null : (int) $shopId;
    }

    /**
     * Stamp the owning shop onto a stock row that predates branch scoping (or
     * was written by a caller that had no shop context). Deriving the shop on
     * every mutation keeps the ledger self-healing instead of leaving a NULL
     * shop_id stranded forever on rows that already existed.
     */
    private function syncShop(Inventory $inventory): Inventory
    {
        if ($inventory->shop_id === null) {
            $inventory->shop_id = $this->shopIdForVariant((int) $inventory->product_variant_id);
            $inventory->save();
        }

        return $inventory;
    }

    /**
     * Get the currently available quantity for a variant (never negative).
     */
    public function available(int $variantId): int
    {
        $inventory = Inventory::firstOrCreate(
            ['product_variant_id' => $variantId],
            ['quantity' => 0, 'reserved_quantity' => 0, 'shop_id' => $this->shopIdForVariant($variantId)]
        );

        $inventory = $this->syncShop($inventory);

        return max((int) $inventory->quantity - (int) $inventory->reserved_quantity, 0);
    }

    /**
     * Atomically reserve stock for a single variant.
     */
    public function reserve(int $variantId, int $quantity): bool
    {
        if ($quantity <= 0) {
            return true;
        }

        return DB::transaction(function () use ($variantId, $quantity): bool {
            $inventory = Inventory::where('product_variant_id', $variantId)->lockForUpdate()->first();

            if ($inventory === null) {
                $inventory = Inventory::create([
                    'product_variant_id' => $variantId,
                    'shop_id' => $this->shopIdForVariant($variantId),
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                ]);
            }

            $inventory = $this->syncShop($inventory);

            $available = (int) $inventory->quantity - (int) $inventory->reserved_quantity;

            if ($available < $quantity) {
                return false;
            }

            $inventory->reserved_quantity = (int) $inventory->reserved_quantity + $quantity;
            $inventory->save();

            $this->log($inventory, 'reserve', $quantity, $available, 'VARIANT:'.$variantId, 'Checkout reservation');
            $this->checkLowStock($inventory);

            return true;
        });
    }

    /**
     * Atomically reserve stock for several variants at once; never partial-reserve.
     */
    public function reserveMany(array $items): bool
    {
        return DB::transaction(function () use ($items): bool {
            foreach ($items as $variantId => $quantity) {
                if (! $this->reserve((int) $variantId, (int) $quantity)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Return previously reserved stock back to the pool.
     */
    public function release(int $variantId, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($variantId, $quantity): void {
            $inventory = Inventory::where('product_variant_id', $variantId)->lockForUpdate()->first();

            if ($inventory === null) {
                return;
            }

            $inventory = $this->syncShop($inventory);

            $released = min((int) $inventory->reserved_quantity, $quantity);
            $inventory->reserved_quantity = (int) $inventory->reserved_quantity - $released;
            $inventory->save();

            $this->log($inventory, 'release', $released, (int) $inventory->quantity - (int) $inventory->reserved_quantity, 'VARIANT:'.$variantId, 'Reservation released');
            $this->checkLowStock($inventory);
        });
    }

    /**
     * Release reserved stock for several variants at once.
     */
    public function releaseMany(array $items): void
    {
        DB::transaction(function () use ($items): void {
            foreach ($items as $variantId => $quantity) {
                $this->release((int) $variantId, (int) $quantity);
            }
        });
    }

    /**
     * Permanently deduct stock on successful payment completion.
     */
    public function deduct(int $variantId, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($variantId, $quantity): void {
            $inventory = Inventory::where('product_variant_id', $variantId)->lockForUpdate()->first();

            if ($inventory === null) {
                return;
            }

            $inventory = $this->syncShop($inventory);

            if ($quantity > (int) $inventory->reserved_quantity || $quantity > (int) $inventory->quantity) {
                throw ValidationException::withMessages([
                    'inventory' => ['Stock reservation is no longer valid for this order.'],
                ]);
            }

            $inventory->quantity = max((int) $inventory->quantity - $quantity, 0);
            $inventory->reserved_quantity = max((int) $inventory->reserved_quantity - $quantity, 0);
            $inventory->sold_count = (int) $inventory->sold_count + $quantity;
            $inventory->save();

            $balanceAfter = (int) $inventory->quantity - (int) $inventory->reserved_quantity;
            $this->log($inventory, 'deduct', $quantity, $balanceAfter, 'VARIANT:'.$variantId, 'Order payment confirmed');
            $this->checkLowStock($inventory);
        });
    }

    /**
     * Permanently deduct reserved stock for several variants at once.
     */
    public function deductMany(array $items): void
    {
        DB::transaction(function () use ($items): void {
            foreach ($items as $variantId => $quantity) {
                $this->deduct((int) $variantId, (int) $quantity);
            }
        });
    }

    /**
     * Return stock permanently deducted by a successful payment back to the pool
     * when the order is cancelled or refunded, reverting both quantity and the
     * running sold-count.
     */
    public function restock(int $variantId, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($variantId, $quantity): void {
            $inventory = Inventory::where('product_variant_id', $variantId)->lockForUpdate()->first();

            if ($inventory === null) {
                return;
            }

            $inventory = $this->syncShop($inventory);

            $inventory->quantity = (int) $inventory->quantity + $quantity;
            $inventory->sold_count = max((int) $inventory->sold_count - $quantity, 0);
            $inventory->save();

            $balanceAfter = (int) $inventory->quantity - (int) $inventory->reserved_quantity;
            $this->log($inventory, 'restock', $quantity, $balanceAfter, 'VARIANT:'.$variantId, 'Order cancelled or refunded');
            $this->checkLowStock($inventory);
        });
    }

    /**
     * Return deducted stock for several variants at once.
     */
    public function restockMany(array $items): void
    {
        DB::transaction(function () use ($items): void {
            foreach ($items as $variantId => $quantity) {
                $this->restock((int) $variantId, (int) $quantity);
            }
        });
    }

    /**
     * Adjust total stock up or down for a variant.
     */
    public function adjust(int $variantId, int $newQuantity, ?int $userId = null): void
    {
        DB::transaction(function () use ($variantId, $newQuantity, $userId): void {
            $inventory = Inventory::where('product_variant_id', $variantId)->lockForUpdate()->first();

            if ($inventory === null) {
                $inventory = Inventory::create([
                    'product_variant_id' => $variantId,
                    'shop_id' => $this->shopIdForVariant($variantId),
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                ]);
            }

            $current = (int) $inventory->quantity;
            $delta = $newQuantity - $current;

            $inventory = $this->syncShop($inventory);

            if ($newQuantity < (int) $inventory->reserved_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Quantity cannot be lower than stock currently reserved for checkout.'],
                ]);
            }

            $inventory->quantity = max($newQuantity, 0);
            $inventory->save();

            $this->log($inventory, 'adjust', $delta, (int) $inventory->quantity, 'VARIANT:'.$variantId, 'Stock adjusted', $userId);
            $this->checkLowStock($inventory);
        });
    }

    private function log(Inventory $inventory, string $type, int $quantity, int $balanceAfter, ?string $reference, ?string $note, ?int $userId = null): void
    {
        InventoryTransaction::create([
            'inventory_id' => $inventory->id,
            'shop_id' => $inventory->shop_id,
            'created_by' => $userId,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $balanceAfter,
            'reference' => $reference,
            'note' => $note,
        ]);
    }

    /**
     * One business event per available-stock episode (including zero stock).
     * The marker records detection, independently of channel delivery. Always
     * reload under a row lock, including when called outside a stock mutation.
     */
    public function checkLowStock(Inventory $inventory): void
    {
        DB::transaction(function () use ($inventory): void {
            $current = Inventory::whereKey($inventory->id)->lockForUpdate()->first();
            if ($current === null) {
                return;
            }

            if (! $current->is_low_stock) {
                if ($current->low_stock_notified_at !== null) {
                    $current->forceFill(['low_stock_notified_at' => null])->save();
                }

                return;
            }

            if ($current->low_stock_notified_at !== null) {
                return;
            }

            $variant = $current->variant()->with(['product.shop', 'attributeValues.value.attribute'])->first();
            $product = $variant?->product;
            if ($product === null || ! $variant->is_active || ! $product->is_active
                || ($product->shop_id !== null && ! $product->shop?->isActive())) {
                return;
            }

            $current->setRelation('variant', $variant);
            $current->forceFill(['low_stock_notified_at' => now()])->save();

            // One query deduplicates users who are both a manager and admin.
            // managedShops enforces active shop/membership and owner/manager roles.
            $recipients = User::query()->where('role', User::ROLE_SUPER_ADMIN)
                ->when($product->shop_id !== null, fn ($query) => $query->orWhereHas(
                    'managedShops', fn ($shops) => $shops->whereKey($product->shop_id)
                ))->get();

            Notification::send($recipients, new LowStockNotification(
                $current,
                $product->name,
                $this->variantLabel($variant),
                $current->available_quantity,
            ));
        });
    }

    /** Reevaluate the platform threshold without holding a catalog-wide lock. */
    public function updateLowStockThreshold(int $threshold): void
    {
        Inventory::query()->select('id')->chunkById(100, function ($inventories) use ($threshold): void {
            foreach ($inventories as $inventory) {
                DB::transaction(function () use ($inventory, $threshold): void {
                    $current = Inventory::whereKey($inventory->id)->lockForUpdate()->first();
                    if ($current === null) {
                        return;
                    }

                    $current->update(['low_stock_threshold' => $threshold]);
                    $this->checkLowStock($current);
                });
            }
        });
    }

    private function variantLabel(ProductVariant $variant): string
    {
        $parts = [];

        foreach ($variant->attributeValues as $vav) {
            $value = $vav->value;
            $attribute = $value?->attribute;

            if ($value === null) {
                continue;
            }

            $parts[] = ($attribute?->name ?? 'Option').': '.$value->value;
        }

        if (count($parts) === 0 && $variant->name !== null) {
            return $variant->name;
        }

        return implode(', ', $parts);
    }
}
