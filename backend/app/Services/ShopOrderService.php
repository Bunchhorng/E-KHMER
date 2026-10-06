<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\ShopOrder;

class ShopOrderService
{
    /** Create one seller-facing order per shop from immutable parent item snapshots. */
    public function split(Order $order): void
    {
        // Preserve platform/legacy products that predate shop ownership. They
        // remain part of the parent order; seller-owned lines are split below.
        $groups = $order->items()->get()
            ->filter(fn ($item) => $item->shop_id !== null)
            ->groupBy('shop_id');

        if ($groups->isEmpty()) {
            return;
        }

        $subtotal = (float) $order->subtotal;
        $shopIds = $groups->keys()->sort()->values();
        $remaining = [
            'discount_amount' => (float) $order->discount_amount,
            'tax_amount' => (float) $order->tax_amount,
            'shipping_amount' => (float) $order->shipping_amount,
        ];

        foreach ($shopIds as $index => $shopId) {
            $items = $groups->get($shopId);
            $shopSubtotal = round((float) $items->sum('line_total'), 2);
            $isLast = $index === $shopIds->count() - 1;
            $allocations = [];

            foreach ($remaining as $field => $amount) {
                $allocated = $isLast
                    ? $amount
                    : round($subtotal > 0 ? $amount * ($shopSubtotal / $subtotal) : 0, 2);
                $allocations[$field] = $allocated;
                $remaining[$field] = round($amount - $allocated, 2);
            }

            $shopOrder = ShopOrder::create([
                'order_id' => $order->id,
                'shop_id' => (int) $shopId,
                'shop_order_number' => $order->order_number.'-'.($index + 1),
                'status' => Order::STATUS_PENDING,
                'subtotal' => $shopSubtotal,
                'discount_amount' => $allocations['discount_amount'],
                'tax_amount' => $allocations['tax_amount'],
                'shipping_amount' => $allocations['shipping_amount'],
                'total' => round($shopSubtotal - $allocations['discount_amount'] + $allocations['tax_amount'] + $allocations['shipping_amount'], 2),
            ]);

            $items->each->update(['shop_order_id' => $shopOrder->id]);
        }
    }
}
