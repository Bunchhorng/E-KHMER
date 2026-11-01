<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\ShopOrder;

class ShopOrderService
{
    /** Create one seller-facing order per shop from immutable parent item snapshots. */
    public function split(Order $order, array $shippingByShop = [], ?int $couponShopId = null): void
    {
        // Include legacy lines in allocation weights, but leave their share on
        // the parent order. Only seller-owned groups become shop orders.
        $groups = $order->items()->get()->toBase()->groupBy(fn ($item) => (int) ($item->shop_id ?? 0))->sortKeys();
        $sellerGroups = $groups->except(0);
        if ($sellerGroups->isEmpty()) {
            return;
        }

        $subtotals = $groups->map(fn ($items) => (int) round((float) $items->sum('line_total') * 100))->all();
        $discountCents = (int) round((float) $order->discount_amount * 100);
        $discounts = $couponShopId === null
            ? $this->allocate($discountCents, $subtotals)
            : array_replace(array_fill_keys(array_keys($subtotals), 0), [$couponShopId => $discountCents]);
        $net = [];
        foreach ($subtotals as $shopId => $amount) {
            $net[$shopId] = $amount - $discounts[$shopId];
        }
        // Tax follows net goods: another seller must not inherit a shop coupon's
        // tax reduction. Cumulative cents apportionment preserves parent totals.
        $taxes = $this->allocate((int) round((float) $order->tax_amount * 100), $net);
        $shipping = [];
        foreach ($shippingByShop as $shopId => $amount) {
            if (array_key_exists($shopId, $subtotals)) {
                $shipping[$shopId] = (int) round($amount * 100);
            }
        }
        $shipping += $this->allocate(
            (int) round((float) $order->shipping_amount * 100) - array_sum($shipping),
            array_diff_key($subtotals, $shipping),
        );

        foreach ($sellerGroups->keys()->values() as $index => $shopId) {
            $shopOrder = ShopOrder::create([
                'order_id' => $order->id,
                'shop_id' => (int) $shopId,
                'shop_order_number' => $order->order_number.'-'.($index + 1),
                'status' => Order::STATUS_PENDING,
                'subtotal' => $subtotals[$shopId] / 100,
                'discount_amount' => $discounts[$shopId] / 100,
                'tax_amount' => $taxes[$shopId] / 100,
                'shipping_amount' => $shipping[$shopId] / 100,
                'total' => ($net[$shopId] + $taxes[$shopId] + $shipping[$shopId]) / 100,
            ]);

            $groups->get($shopId)->each->update(['shop_order_id' => $shopOrder->id]);
            $shopOrder->trackingEvents()->create(['order_id' => $order->id, 'status' => Order::STATUS_PENDING, 'description' => 'Shop order placed']);
        }
    }

    /** @param array<int, int> $weights @return array<int, int> */
    private function allocate(int $amount, array $weights): array
    {
        $total = array_sum($weights);
        if ($total === 0) {
            $weights = array_fill_keys(array_keys($weights), 1);
            $total = count($weights);
        }
        $result = [];
        $cumulative = 0;
        $allocated = 0;
        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            $through = (int) round($amount * $cumulative / $total);
            $result[$key] = $through - $allocated;
            $allocated = $through;
        }

        return $result;
    }
}
