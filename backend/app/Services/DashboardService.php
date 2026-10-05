<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Memoised `GROUP BY status` aggregate. Both `metrics()` and
     * `orderStatusDistribution()` need the exact same result, and both run on
     * the same instance during one overview request.
     *
     * @var Collection<string,int>|null
     */
    protected ?Collection $orderCountsByStatus = null;

    public function metrics(): array
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $prevMonthStart = $monthStart->copy()->subMonth();
        $prevMonthEnd = $monthStart->copy()->subSecond();
        $weekStart = $now->copy()->startOfWeek();
        $todayStart = $now->copy()->startOfDay();

        // Every revenue figure the KPI cards need, in one conditional
        // aggregation instead of five separate SUM() round-trips. The month
        // figure is read twice by the response (as `month_revenue` and as the
        // numerator of `revenue_delta`), so the old code also ran the same SUM
        // a second time.
        $revenue = Order::query()
            ->where('payment_status', Order::PAYMENT_PAID)
            ->selectRaw(
                'COALESCE(SUM(total), 0) as total_revenue,
                 COALESCE(SUM(CASE WHEN placed_at >= ? THEN total ELSE 0 END), 0) as today_revenue,
                 COALESCE(SUM(CASE WHEN placed_at >= ? THEN total ELSE 0 END), 0) as week_revenue,
                 COALESCE(SUM(CASE WHEN placed_at >= ? AND placed_at <= ? THEN total ELSE 0 END), 0) as month_revenue,
                 COALESCE(SUM(CASE WHEN placed_at >= ? AND placed_at <= ? THEN total ELSE 0 END), 0) as prev_month_revenue',
                [$todayStart, $weekStart, $monthStart, $now, $prevMonthStart, $prevMonthEnd]
            )
            ->first();

        $monthRevenue = (float) $revenue->month_revenue;

        // Order + customer month-over-month counts, one query per table.
        $orderCounts = Order::query()
            ->selectRaw(
                'COUNT(*) as total,
                 SUM(CASE WHEN placed_at >= ? THEN 1 ELSE 0 END) as this_month,
                 SUM(CASE WHEN placed_at >= ? AND placed_at <= ? THEN 1 ELSE 0 END) as last_month',
                [$monthStart, $prevMonthStart, $prevMonthEnd]
            )
            ->first();

        $customerCounts = User::where('role', User::ROLE_CUSTOMER)
            ->selectRaw(
                'COUNT(*) as total,
                 SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as this_month,
                 SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 ELSE 0 END) as last_month',
                [$monthStart, $prevMonthStart, $prevMonthEnd]
            )
            ->first();

        $ordersPerStatus = $this->orderCountsByStatus();

        return [
            'total_revenue' => round((float) $revenue->total_revenue, 2),
            'today_revenue' => round((float) $revenue->today_revenue, 2),
            'week_revenue' => round((float) $revenue->week_revenue, 2),
            'month_revenue' => round($monthRevenue, 2),
            'revenue_delta' => $this->percentChange($monthRevenue, (float) $revenue->prev_month_revenue),
            'orders_count' => (int) $ordersPerStatus->sum(),
            'orders_delta' => $this->percentChange((int) $orderCounts->this_month, (int) $orderCounts->last_month),
            'pending_orders' => (int) ($ordersPerStatus[Order::STATUS_PENDING] ?? 0),
            'processing_orders' => (int) ($ordersPerStatus[Order::STATUS_PROCESSING] ?? 0),
            'completed_orders' => (int) ($ordersPerStatus[Order::STATUS_DELIVERED] ?? 0),
            'cancelled_orders' => (int) ($ordersPerStatus[Order::STATUS_CANCELLED] ?? 0),
            'customers_count' => (int) $customerCounts->total,
            'customers_delta' => $this->percentChange((int) $customerCounts->this_month, (int) $customerCounts->last_month),
            'total_products' => Product::count(),
            'total_categories' => Category::count(),
            'total_brands' => Brand::count(),
            'low_stock_products' => $this->lowStockQuery()->count(),
            'out_of_stock_products' => ProductVariant::query()
                ->where('is_active', true)
                ->whereHas('inventory', fn ($q) => $q->whereRaw('quantity - reserved_quantity <= 0'))
                ->count(),
        ];
    }

    public function revenueTrend(?Carbon $from = null, ?Carbon $to = null): array
    {
        if ($from === null || $to === null) {
            $to = Carbon::today();
            $from = Carbon::today()->subDays(29)->startOfDay();
        }

        $dayCount = (int) $from->diffInDays($to) + 1;

        $rows = Order::where('payment_status', Order::PAYMENT_PAID)
            ->where('placed_at', '>=', $from)
            ->where('placed_at', '<=', $to->copy()->endOfDay())
            ->selectRaw('DATE(placed_at) as day, SUM(total) as revenue')
            ->groupBy('day')
            ->pluck('revenue', 'day');

        $series = [];
        for ($i = 0; $i < $dayCount; $i++) {
            $date = $from->copy()->addDays($i);
            $key = $date->format('Y-m-d');
            $series[] = [
                'date' => $key,
                'revenue' => round((float) ($rows[$key] ?? 0), 2),
            ];
        }

        return $series;
    }

    public function ordersTrend(?Carbon $from = null, ?Carbon $to = null): array
    {
        if ($from === null || $to === null) {
            $to = Carbon::today();
            $from = Carbon::today()->subDays(29)->startOfDay();
        }

        $dayCount = (int) $from->diffInDays($to) + 1;

        $rows = Order::where('placed_at', '>=', $from)
            ->where('placed_at', '<=', $to->copy()->endOfDay())
            ->selectRaw('DATE(placed_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day');

        $series = [];
        for ($i = 0; $i < $dayCount; $i++) {
            $date = $from->copy()->addDays($i);
            $key = $date->format('Y-m-d');
            $series[] = [
                'date' => $key,
                'orders' => (int) ($rows[$key] ?? 0),
            ];
        }

        return $series;
    }

    public function orderStatusDistribution(): array
    {
        return $this->orderCountsByStatus()
            ->map(fn ($count, $status) => [
                'status' => $status,
                'count' => (int) $count,
            ])
            ->values()
            ->all();
    }

    public function paymentStatusDistribution(): array
    {
        return Payment::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($count, $status) => [
                'status' => $status,
                'count' => (int) $count,
            ])
            ->values()
            ->all();
    }

    public function salesByCategory(): array
    {
        return DB::table('categories')
            ->leftJoin('products', 'products.category_id', '=', 'categories.id')
            ->leftJoin('order_items', 'order_items.product_id', '=', 'products.id')
            ->where('categories.is_active', true)
            ->selectRaw('categories.id, categories.name, categories.slug,
                COALESCE(SUM(order_items.line_total), 0) as revenue,
                COUNT(DISTINCT order_items.id) as order_count')
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'slug' => $row->slug,
                'revenue' => round((float) $row->revenue, 2),
                'order_count' => (int) $row->order_count,
            ])
            ->values()
            ->all();
    }

    public function topSellingProducts(int $limit = 5, ?Carbon $from = null, ?Carbon $to = null): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
            ->whereNotNull('orders.placed_at')
            ->when($from !== null, fn ($q) => $q->where('orders.placed_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('orders.placed_at', '<=', $to->copy()->endOfDay()))
            ->selectRaw('order_items.product_id, order_items.product_name,
                SUM(order_items.quantity) as total_qty,
                SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product_name,
                'total_qty' => (int) $row->total_qty,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->values()
            ->all();
    }

    public function lowStockList(int $limit = 10): array
    {
        return $this->lowStockQuery()
            ->with(['variant.product.brand'])
            ->orderByRaw('(quantity - reserved_quantity) asc')
            ->limit($limit)
            ->get()
            ->map(fn (Inventory $inventory) => [
                'id' => $inventory->id,
                'product_id' => $inventory->variant?->product_id,
                'product_name' => $inventory->variant?->product?->name,
                'product_slug' => $inventory->variant?->product?->slug,
                'variant_name' => $inventory->variant?->name,
                'sku' => $inventory->variant?->sku,
                'quantity' => (int) $inventory->quantity,
                'reserved_quantity' => (int) $inventory->reserved_quantity,
                'available_quantity' => $inventory->available_quantity,
                'low_stock_threshold' => (int) $inventory->low_stock_threshold,
                'is_out_of_stock' => $inventory->available_quantity <= 0,
            ])
            ->values()
            ->all();
    }

    public function recentCustomers(int $limit = 5): array
    {
        return User::where('role', User::ROLE_CUSTOMER)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'created_at' => $user->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    public function recentReviews(int $limit = 5): array
    {
        return Review::query()
            ->with(['user', 'product'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Review $review) => [
                'id' => $review->id,
                'rating' => (float) $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'status' => $review->status,
                'user_name' => $review->user?->name,
                'product_name' => $review->product?->name,
                'created_at' => $review->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    public function recentPayments(int $limit = 5): array
    {
        return Payment::query()
            ->with('order')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'order_number' => $payment->order?->order_number,
                'method' => $payment->method,
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
                'transaction_id' => $payment->transaction_id,
                'paid_at' => $payment->paid_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    protected function lowStockQuery()
    {
        return Inventory::query()
            ->whereRaw('quantity - reserved_quantity <= low_stock_threshold');
    }

    /**
     * Order counts grouped by status, run at most once per instance.
     *
     * @return Collection<string,int>
     */
    protected function orderCountsByStatus(): Collection
    {
        return $this->orderCountsByStatus ??= Order::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');
    }

    protected function percentChange(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
