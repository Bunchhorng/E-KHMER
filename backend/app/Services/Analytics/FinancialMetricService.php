<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\BusinessExpense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ShopOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Canonical financial policy for reports, dashboards, and forecasts.
 *
 * Reports are grouped by order placement date. An online sale settles after a
 * verified payment; a COD sale settles after its shop allocation is collected.
 * PaymentRefund rows are completed refunds because RefundService creates them
 * only after verifying the relevant refund evidence.
 */
class FinancialMetricService
{
    public static function cents(string|int|float|null $amount): int
    {
        return (int) round((float) ($amount ?? 0) * 100);
    }

    public function emptyBucket(): array
    {
        return ['revenue' => 0, 'cogs' => 0, 'expense' => 0, 'refunds' => 0, 'orders' => 0,
            'missing_cost_items' => 0, 'units' => 0, 'costed_units' => 0];
    }

    /** Return canonical report-card metrics, with money accumulated in cents. */
    public function report(?Carbon $from = null, ?Carbon $to = null, ?int $shopId = null, ?string $currency = null): array
    {
        $orders = $this->ordersInRange($from, $to, $currency);
        $demandOrders = (clone $orders)->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED]);
        $summary = [
            'gross_order_demand' => self::cents((clone $demandOrders)->sum('total')),
            'orders_created' => (clone $orders)->count(),
            'cancelled_orders' => (clone $orders)->where('status', Order::STATUS_CANCELLED)->count(),
            'customers_count' => (clone $orders)->whereNotNull('user_id')->distinct()->count('user_id'),
            'units_ordered' => (int) OrderItem::query()->whereHas('order', fn (Builder $query) => $query
                ->whereIn('id', (clone $demandOrders)->select('id')))->sum('quantity'),
            'settled_revenue' => 0,
            'refunded_amount' => 0,
            'net_revenue' => 0,
            'items_revenue' => 0,
            'units_sold' => 0,
            'paid_orders' => 0,
            'products' => [],
            'categories' => [],
        ];

        $paidOrders = [];
        $this->eachSettledAllocation($from, $to, $shopId, $currency, function (array $sale) use (&$summary, &$paidOrders): void {
            $summary['settled_revenue'] += $sale['gross'];
            $summary['refunded_amount'] += $sale['refunds'];
            $summary['net_revenue'] += $sale['net'];
            $summary['items_revenue'] += $sale['items_revenue'];
            $summary['units_sold'] += $sale['units'];
            $paidOrders[$sale['order_id']] = true;

            foreach ($sale['products'] as $product) {
                $key = $product['product_id'].'|'.$product['product_name'];
                $summary['products'][$key] ??= [
                    'product_id' => $product['product_id'],
                    'product_name' => $product['product_name'],
                    'category_id' => $product['category_id'],
                    'category_name' => $product['category_name'],
                    'category_slug' => $product['category_slug'],
                    'units_sold' => 0,
                    'revenue' => 0,
                ];
                $summary['products'][$key]['units_sold'] += $product['units_sold'];
                $summary['products'][$key]['revenue'] += $product['revenue'];
                if ($product['category_id'] !== null) {
                    $categoryKey = $product['category_id'];
                    $summary['categories'][$categoryKey] ??= ['id' => $product['category_id'], 'name' => $product['category_name'], 'slug' => $product['category_slug'], 'revenue' => 0, 'order_ids' => []];
                    $summary['categories'][$categoryKey]['revenue'] += $product['revenue'];
                    $summary['categories'][$categoryKey]['order_ids'][$sale['order_id']] = true;
                }
            }
        });

        $summary['paid_orders'] = count($paidOrders);
        $summary['categories'] = collect($summary['categories'])->map(function (array $category): array {
            $category['order_count'] = count($category['order_ids']);
            unset($category['order_ids']);

            return $category;
        })->all();

        return $summary;
    }

    /** Sales and refunds are restated in their original order-placement month. */
    public function monthly(?int $shopId, Carbon $from, Carbon $to, string $currency): array
    {
        $buckets = [];
        for ($month = $from->copy()->startOfMonth(); $month->lte($to); $month->addMonth()) {
            $buckets[$month->format('Y-m')] = $this->emptyBucket();
        }
        $firstActivity = null;
        $this->eachSettledAllocation($from, $to, $shopId, $currency, function (array $sale) use (&$buckets, &$firstActivity): void {
            $month = $sale['placed_at']->format('Y-m');
            $firstActivity = $firstActivity === null ? $month : min($firstActivity, $month);
            $bucket = &$buckets[$month];
            // Forecast profit uses recognised merchandise revenue. Tax and
            // shipping are collections, not trading revenue.
            $bucket['revenue'] += $sale['items_revenue'];
            $bucket['refunds'] += $sale['item_refunds'];
            $bucket['orders'] += $sale['items_revenue'] > 0 ? 1 : 0;
            $bucket['units'] += $sale['units'];
            foreach ($sale['costs'] as $cost) {
                if ($cost['missing']) {
                    $bucket['missing_cost_items']++;

                    continue;
                }
                $bucket['cogs'] += $cost['cogs'];
                $bucket['costed_units'] += $cost['units'];
            }
            unset($bucket);
        });

        BusinessExpense::query()->when($shopId !== null, fn (Builder $query) => $query->where('shop_id', $shopId))
            ->where('currency', $currency)->whereDate('incurred_on', '>=', $from->toDateString())->whereDate('incurred_on', '<=', $to->toDateString())
            ->chunkById(200, function ($expenses) use (&$buckets, &$firstActivity): void {
                foreach ($expenses as $expense) {
                    $month = $expense->incurred_on->format('Y-m');
                    $firstActivity = $firstActivity === null ? $month : min($firstActivity, $month);
                    $buckets[$month]['expense'] += self::cents($expense->amount);
                }
            });

        $legacy = $this->legacySettledOrders($from, $to, $currency)->when($shopId !== null, fn (Builder $query) => $query->whereHas('items', fn (Builder $items) => $items->where('shop_id', $shopId)))->exists();

        return ['buckets' => $buckets, 'first_activity' => $firstActivity, 'legacy_unallocated_sales' => $legacy];
    }

    public function add(array $left, array $right): array
    {
        foreach ($left as $key => $value) {
            $left[$key] = $value + $right[$key];
        }

        return $left;
    }

    public function metrics(array $bucket): array
    {
        $cogs = $bucket['missing_cost_items'] > 0 ? null : $bucket['cogs'];
        $profit = $cogs === null ? null : $bucket['revenue'] - $cogs - $bucket['expense'];

        return [
            'revenue' => $bucket['revenue'] / 100,
            'cogs' => $cogs === null ? null : $cogs / 100,
            'expense' => $bucket['expense'] / 100,
            'profit' => $profit === null ? null : $profit / 100,
            'loss' => $profit === null ? null : min(0, $profit) / 100,
            'refunds' => $bucket['refunds'] / 100,
            'orders' => $bucket['orders'],
            'average_order_value' => $bucket['orders'] > 0 ? round($bucket['revenue'] / $bucket['orders'] / 100, 2) : null,
            'missing_cost_items' => $bucket['missing_cost_items'],
            'cost_coverage' => $bucket['units'] > 0 ? round(100 * $bucket['costed_units'] / $bucket['units'], 1) : 100,
        ];
    }

    private function ordersInRange(?Carbon $from, ?Carbon $to, ?string $currency): Builder
    {
        return Order::query()->whereNotNull('placed_at')
            ->when($from, fn (Builder $query) => $query->where('placed_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('placed_at', '<=', $to))
            ->when($currency, fn (Builder $query) => $query->where('currency', $currency));
    }

    private function eachSettledAllocation(?Carbon $from, ?Carbon $to, ?int $shopId, ?string $currency, callable $consumer): void
    {
        $orders = $this->ordersInRange($from, $to, $currency);
        $online = fn (Builder $payment) => $payment->where('method', '!=', 'cod')->whereIn('status', [Payment::STATUS_COMPLETED, Payment::STATUS_REFUNDED]);
        ShopOrder::query()->when($shopId !== null, fn (Builder $query) => $query->where('shop_id', $shopId))
            ->whereHas('order', fn (Builder $query) => $query->whereIn('id', (clone $orders)->select('id')))
            ->where(fn (Builder $query) => $query->whereHas('order.payment', $online)->orWhereHas('codCollection'))
            ->with(['items.returns', 'items.product.category', 'codCollection', 'order.payment', 'order.refunds.returnRecord.item', 'order.refunds.bakongPayment'])
            ->chunkById(200, function ($allocations) use ($consumer): void {
                foreach ($allocations as $allocation) {
                    $consumer($this->allocationMetrics($allocation));
                }
            });

        // Preserve historical online sales from before shop allocations existed.
        $this->legacySettledOrders($from, $to, $currency)->with(['items.returns', 'items.product.category', 'refunds.returnRecord.item', 'refunds.bakongPayment'])
            ->chunkById(200, function ($orders) use ($shopId, $consumer): void {
                foreach ($orders as $order) {
                    $items = $order->items->when($shopId !== null, fn ($items) => $items->where('shop_id', $shopId));
                    if ($shopId === null || $items->isNotEmpty()) {
                        $consumer($this->legacyMetrics($order, $items));
                    }
                }
            });
    }

    private function legacySettledOrders(?Carbon $from, ?Carbon $to, ?string $currency): Builder
    {
        return $this->ordersInRange($from, $to, $currency)->whereDoesntHave('shopOrders')
            ->whereHas('payment', fn (Builder $payment) => $payment->where('method', '!=', 'cod')->whereIn('status', [Payment::STATUS_COMPLETED, Payment::STATUS_REFUNDED]));
    }

    private function allocationMetrics(ShopOrder $allocation): array
    {
        $order = $allocation->order;
        $gross = $order->payment?->method === 'cod'
            ? self::cents($allocation->codCollection?->amount)
            : self::cents($allocation->total);

        return $this->saleMetrics($order, $allocation->items, $gross, self::cents($allocation->subtotal), self::cents($allocation->discount_amount), self::cents($allocation->total), $allocation->id);
    }

    private function legacyMetrics(Order $order, $items): array
    {
        return $this->saleMetrics($order, $items, self::cents($order->total), self::cents($order->subtotal), self::cents($order->discount_amount), self::cents($order->total), null);
    }

    private function saleMetrics(Order $order, $items, int $gross, int $subtotal, int $discount, int $allocationTotal, ?int $shopOrderId): array
    {
        $goods = max(0, $subtotal - $discount);
        $refunds = 0;
        $itemRefunds = 0;
        $specificRefunds = [];
        foreach ($order->refunds as $refund) {
            if ($refund->currency !== $order->currency) {
                continue;
            }
            $amount = self::cents($refund->amount);
            $returnItem = $refund->returnRecord?->item;
            if ($returnItem !== null) {
                if (($shopOrderId !== null && (int) $returnItem->shop_order_id !== $shopOrderId) || ($shopOrderId === null && $returnItem->shop_order_id !== null)) {
                    continue;
                }
                $specificRefunds[$returnItem->id] = ($specificRefunds[$returnItem->id] ?? 0) + $amount;
                $refunds += $amount;
                $itemRefunds += $allocationTotal > 0 ? (int) round($amount * $goods / $allocationTotal) : 0;

                continue;
            }
            if ($refund->bakongPayment?->shop_order_id !== null && (int) $refund->bakongPayment->shop_order_id !== $shopOrderId) {
                continue;
            }
            $allocated = $shopOrderId === null || self::cents($order->total) === 0 ? $amount : (int) round($amount * $allocationTotal / self::cents($order->total));
            $refunds += $allocated;
            $itemRefunds += $allocationTotal > 0 ? (int) round($allocated * $goods / $allocationTotal) : 0;
        }
        $refunds = min($gross, $refunds);
        $itemRefunds = min($goods, $itemRefunds);
        $net = $gross - $refunds;
        $itemsRevenue = $goods - $itemRefunds;
        $products = [];
        $costs = [];
        $units = 0;
        foreach ($items as $item) {
            $returned = (int) $item->returns->where('status', 'refunded')->sum('quantity');
            $itemUnits = $net === 0 ? 0 : max(0, (int) $item->quantity - $returned);
            $units += $itemUnits;
            $itemGross = $subtotal > 0 ? (int) round(self::cents($item->line_total) * $goods / $subtotal) : 0;
            $itemRefund = $specificRefunds[$item->id] ?? 0;
            $itemRefund = $allocationTotal > 0 ? (int) round($itemRefund * $goods / $allocationTotal) : 0;
            if ($itemRefunds > 0 && $goods > 0) {
                $itemRefund += (int) round($itemRefunds * $itemGross / $goods);
            }
            $itemNet = max(0, $itemGross - min($itemGross, $itemRefund));
            $products[] = [
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product_name,
                'category_id' => $item->product?->category_id,
                'category_name' => $item->product?->category?->name,
                'category_slug' => $item->product?->category?->slug,
                'units_sold' => $itemUnits,
                'revenue' => $itemNet,
            ];
            $costs[] = ['missing' => $item->unit_cost === null && $itemUnits > 0, 'cogs' => self::cents($item->unit_cost) * $itemUnits, 'units' => $itemUnits];
        }

        return ['order_id' => $order->id, 'placed_at' => $order->placed_at, 'gross' => $gross, 'refunds' => $refunds, 'net' => $net,
            'items_revenue' => $itemsRevenue, 'item_refunds' => $itemRefunds, 'units' => $units, 'products' => $products, 'costs' => $costs];
    }
}
