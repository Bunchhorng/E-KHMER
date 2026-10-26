<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\BusinessExpense;
use App\Models\OrderItem;
use App\Models\ShopOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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

    /** Sales are restated in their placement month using verified refunds known at generation time. */
    public function monthly(?int $shopId, Carbon $from, Carbon $to, string $currency): array
    {
        $buckets = [];
        for ($month = $from->copy()->startOfMonth(); $month->lte($to); $month->addMonth()) {
            $buckets[$month->format('Y-m')] = $this->emptyBucket();
        }
        $firstActivity = null;
        $paidOrders = fn (Builder $query) => $query
            ->whereBetween('placed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('currency', $currency)
            ->whereNotIn('status', ['pending', 'cancelled'])
            ->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded'])
            ->whereHas('payment', fn (Builder $payment) => $payment->whereIn('status', ['completed', 'refunded']));

        ShopOrder::query()->when($shopId !== null, fn (Builder $query) => $query->where('shop_id', $shopId))
            ->whereNotIn('status', ['pending', 'cancelled'])
            ->whereHas('order', $paidOrders)
            ->with(['items.returns', 'order.refunds.returnRecord', 'order.refunds.bakongPayment'])
            ->chunkById(200, function ($orders) use (&$buckets, &$firstActivity): void {
                foreach ($orders as $allocation) {
                    $month = $allocation->order->placed_at->format('Y-m');
                    $firstActivity = $firstActivity === null ? $month : min($firstActivity, $month);
                    $bucket = &$buckets[$month];
                    $goods = max(0, self::cents($allocation->subtotal) - self::cents($allocation->discount_amount));
                    $refunds = 0;
                    foreach ($allocation->order->refunds as $refund) {
                        if ($refund->currency !== $allocation->order->currency) {
                            continue;
                        }
                        if ($refund->returnRecord !== null) {
                            if (! $allocation->items->contains('id', $refund->returnRecord->order_item_id)) {
                                continue;
                            }
                            $basis = $goods + self::cents($allocation->tax_amount);
                        } elseif ($refund->bakongPayment !== null) {
                            if ((int) $refund->bakongPayment->shop_order_id !== (int) $allocation->id) {
                                continue;
                            }
                            $basis = self::cents($allocation->total);
                        } else {
                            $basis = self::cents($allocation->order->total);
                        }
                        $refunds += $basis > 0 ? (int) round(self::cents($refund->amount) * $goods / $basis) : 0;
                    }
                    $refunds = $allocation->order->payment_status === 'refunded' ? $goods : min($goods, $refunds);
                    $netRevenue = $goods - $refunds;
                    $bucket['revenue'] += $netRevenue;
                    $bucket['refunds'] += $refunds;
                    $bucket['orders'] += $netRevenue > 0 ? 1 : 0;
                    if ($allocation->items->isEmpty() && $goods > 0) {
                        $bucket['missing_cost_items']++;
                        $bucket['units']++;
                    }
                    foreach ($allocation->items as $item) {
                        $restocked = (int) $item->returns->where('status', 'refunded')->where('restocked', true)->sum('quantity');
                        $units = max(0, (int) $item->quantity - $restocked);
                        $bucket['units'] += $units;
                        if ($item->unit_cost === null && $units > 0) {
                            $bucket['missing_cost_items']++;
                        } else {
                            $bucket['cogs'] += self::cents($item->unit_cost) * $units;
                            $bucket['costed_units'] += $units;
                        }
                    }
                    unset($bucket);
                }
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

        $legacy = OrderItem::query()->when($shopId !== null, fn (Builder $query) => $query->where('shop_id', $shopId))
            ->whereNull('shop_order_id')->whereHas('order', $paidOrders)->exists();

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
}
