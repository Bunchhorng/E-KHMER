<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Services\DashboardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    protected function applyFilters(Request $request, $query)
    {
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('from')) {
            $query->where('placed_at', '>=', Carbon::parse($request->from)->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('placed_at', '<=', Carbon::parse($request->to)->endOfDay());
        }

        return $query;
    }

    public function summary(Request $request, DashboardService $dashboard)
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : null;
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : null;

        $ordersQuery = Order::query()
            ->when($from !== null, fn ($q) => $q->where('placed_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('placed_at', '<=', $to));

        $paidQuery = (clone $ordersQuery)->where('payment_status', Order::PAYMENT_PAID);

        $revenue = round((float) (clone $paidQuery)->sum('total'), 2);
        $refunded = round((float) (clone $ordersQuery)->whereIn('status', [Order::STATUS_REFUNDED])->sum('total'), 2);
        $ordersCount = (clone $ordersQuery)->count();
        $customersCount = (clone $ordersQuery)->whereNotNull('user_id')->distinct()->count('user_id');

        $itemsQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
            ->when($from !== null, fn ($q) => $q->where('orders.placed_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('orders.placed_at', '<=', $to));

        $unitsSold = (int) (clone $itemsQuery)->sum('quantity');
        $itemsRevenue = round((float) (clone $itemsQuery)->sum('line_total'), 2);

        $paymentMethods = Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->when($from !== null, fn ($q) => $q->where('orders.placed_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('orders.placed_at', '<=', $to))
            ->selectRaw('payments.method, COUNT(*) as count, SUM(payments.amount) as amount')
            ->groupBy('payments.method')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->method,
                'count' => (int) $row->count,
                'amount' => round((float) $row->amount, 2),
            ])
            ->values()
            ->all();

        $lowStockCount = Inventory::query()
            ->whereRaw('quantity - reserved_quantity <= low_stock_threshold')
            ->count();

        return [
            'data' => [
                'revenue' => $revenue,
                'items_revenue' => $itemsRevenue,
                'refunded' => $refunded,
                'orders_count' => $ordersCount,
                'customers_count' => $customersCount,
                'units_sold' => $unitsSold,
                'avg_order_value' => $ordersCount > 0 ? round($revenue / $ordersCount, 2) : 0,
                'payment_methods' => $paymentMethods,
                'low_stock_count' => (int) $lowStockCount,
                'sales_by_category' => $dashboard->salesByCategory($from, $to),
                'top_selling_products' => $dashboard->topSellingProducts(5, $from, $to),
            ],
        ];
    }

    public function ordersCsv(Request $request)
    {
        $query = $this->applyFilters($request, Order::query()->orderByDesc('placed_at'));

        $orders = $query->get();

        $headers = [
            'order_number', 'placed_at', 'customer_name', 'email', 'status',
            'payment_status', 'subtotal', 'discount_amount', 'tax_amount',
            'shipping_amount', 'total', 'coupon_code',
        ];

        return response()->streamDownload(function () use ($orders, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->order_number,
                    $order->placed_at?->toDateTimeString(),
                    $order->customer_name,
                    $order->email,
                    $order->status,
                    $order->payment_status,
                    (string) $order->subtotal,
                    (string) $order->discount_amount,
                    (string) $order->tax_amount,
                    (string) $order->shipping_amount,
                    (string) $order->total,
                    $order->coupon_code,
                ]);
            }

            fclose($handle);
        }, 'orders-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function ordersPdf(Request $request)
    {
        $query = $this->applyFilters($request, Order::query()->orderByDesc('placed_at'));

        $orders = $query->get();

        $pdf = Pdf::loadView('reports.orders', [
            'orders' => $orders,
            'status' => $request->filled('status') ? $request->status : 'all',
        ]);

        return $pdf->download('orders-'.now()->format('Y-m-d').'.pdf');
    }

    public function productsCsv(Request $request)
    {
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : null;
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : null;

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
            ->when($from !== null, fn ($q) => $q->where('orders.placed_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('orders.placed_at', '<=', $to))
            ->selectRaw('order_items.product_id, order_items.product_name,
                SUM(order_items.quantity) as units_sold,
                SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('units_sold')
            ->get();

        $headers = ['product_id', 'product_name', 'units_sold', 'revenue'];

        return response()->streamDownload(function () use ($rows, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->product_id,
                    $row->product_name,
                    (string) $row->units_sold,
                    (string) $row->revenue,
                ]);
            }

            fclose($handle);
        }, 'products-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function paymentsCsv(Request $request)
    {
        $query = Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->select('payments.*', 'orders.order_number')
            ->orderByDesc('payments.created_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('payments.status', $request->status);
        }

        if ($request->filled('from')) {
            $query->where('payments.created_at', '>=', Carbon::parse($request->from)->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('payments.created_at', '<=', Carbon::parse($request->to)->endOfDay());
        }

        $payments = $query->get();

        $headers = ['id', 'order_number', 'method', 'status', 'amount', 'transaction_id', 'paid_at'];

        return response()->streamDownload(function () use ($payments, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($payments as $payment) {
                fputcsv($handle, [
                    $payment->id,
                    $payment->order_number,
                    $payment->method,
                    $payment->status,
                    (string) $payment->amount,
                    $payment->transaction_id,
                    $payment->paid_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 'payments-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
