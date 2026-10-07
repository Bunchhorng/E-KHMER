<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDashboardRequest;
use App\Http\Resources\AdminDashboardResource;
use App\Services\DashboardService;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function overview(AdminDashboardRequest $request): AdminDashboardResource
    {
        [$from, $to] = $this->resolveRange($request);

        return new AdminDashboardResource([
            'range' => $request->query('range', '30'),
            'period' => $this->dashboard->periodSummary($from, $to),
            'metrics' => $this->dashboard->metrics(),
            'revenue_trend' => $this->dashboard->revenueTrend($from, $to),
            'orders_trend' => $this->dashboard->ordersTrend($from, $to),
            'status_distribution' => $this->dashboard->orderStatusDistribution($from, $to),
            'payment_status_distribution' => $this->dashboard->paymentStatusDistribution($from, $to),
            'sales_by_category' => $this->dashboard->salesByCategory($from, $to),
            'top_selling_products' => $this->dashboard->topSellingProducts(5, $from, $to),
            'low_stock' => $this->dashboard->lowStockList(),
            'recent_orders' => $this->dashboard->recentOrders(5, $from, $to),
            'recent_customers' => $this->dashboard->recentCustomers(5, $from, $to),
            'recent_reviews' => $this->dashboard->recentReviews(5, $from, $to),
            'recent_payments' => $this->dashboard->recentPayments(5, $from, $to),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveRange(AdminDashboardRequest $request): array
    {
        $range = $request->query('range', '30');
        $now = Carbon::now()->endOfDay();

        if ($range === 'custom' && $request->filled(['from', 'to'])) {
            return [
                Carbon::parse($request->from)->startOfDay(),
                Carbon::parse($request->to)->endOfDay(),
            ];
        }

        return match ($range) {
            'today' => [$now->copy()->startOfDay(), $now],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            '7' => [$now->copy()->subDays(6)->startOfDay(), $now],
            'this_month' => [$now->copy()->startOfMonth(), $now],
            'last_month' => [$now->copy()->startOfMonth()->subMonth(), $now->copy()->startOfMonth()->subSecond()],
            default => [$now->copy()->subDays(29)->startOfDay(), $now],
        };
    }
}
