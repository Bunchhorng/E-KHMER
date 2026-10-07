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
     * Notify admins once when a variant dips to or below its low-stock
     * threshold, and clear the flag again when stock is restored above it.
     */
    public function checkLowStock(Inventory $inventory): void
    {
        $inventory->refresh();

        if ($inventory->is_low_stock && $inventory->low_stock_notified_at === null) {
            $inventory->forceFill(['low_stock_notified_at' => now()])->save();

            $admins = User::query()->where('role', User::ROLE_SUPER_ADMIN)->get();

            if ($admins->isEmpty()) {
                return;
            }

            $variant = $inventory->variant()->with(['product', 'attributeValues.value.attribute'])->first();

            Notification::send($admins, new LowStockNotification(
                $inventory,
                $variant?->product?->name ?? 'Product',
                $variant !== null ? $this->variantLabel($variant) : '',
                $inventory->available_quantity,
            ));
        } elseif (! $inventory->is_low_stock && $inventory->low_stock_notified_at !== null) {
            $inventory->forceFill(['low_stock_notified_at' => null])->save();
        }
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
