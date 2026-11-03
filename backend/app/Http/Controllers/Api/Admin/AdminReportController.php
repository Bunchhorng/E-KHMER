<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Analytics\FinancialMetricService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

    public function summary(Request $request, FinancialMetricService $financial)
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : null;
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : null;

        $metrics = $financial->report($from, $to);

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
                // The aliases preserve the previous API contract. Revenue is
                // now explicitly net collected revenue.
                'revenue' => round($metrics['net_revenue'] / 100, 2),
                'settled_revenue' => round($metrics['settled_revenue'] / 100, 2),
                'net_revenue' => round($metrics['net_revenue'] / 100, 2),
                'gross_order_demand' => round($metrics['gross_order_demand'] / 100, 2),
                'items_revenue' => round($metrics['items_revenue'] / 100, 2),
                'refunded' => round($metrics['refunded_amount'] / 100, 2),
                'refunded_amount' => round($metrics['refunded_amount'] / 100, 2),
                'orders_count' => $metrics['orders_created'],
                'orders_created' => $metrics['orders_created'],
                'paid_orders' => $metrics['paid_orders'],
                'cancelled_orders' => $metrics['cancelled_orders'],
                'customers_count' => $metrics['customers_count'],
                'units_ordered' => $metrics['units_ordered'],
                'units_sold' => $metrics['units_sold'],
                'avg_order_value' => $metrics['paid_orders'] > 0 ? round($metrics['net_revenue'] / $metrics['paid_orders'] / 100, 2) : 0,
                'payment_methods' => $paymentMethods,
                'low_stock_count' => (int) $lowStockCount,
                'sales_by_category' => collect($metrics['categories'])->sortByDesc('revenue')->values()->map(fn (array $category) => [
                    ...$category,
                    'revenue' => round($category['revenue'] / 100, 2),
                ])->all(),
                'top_selling_products' => collect($metrics['products'])->sortByDesc('units_sold')->take(5)->values()->map(fn (array $product) => [
                    'product_id' => $product['product_id'],
                    'product_name' => $product['product_name'],
                    'total_qty' => $product['units_sold'],
                    'revenue' => round($product['revenue'] / 100, 2),
                ])->all(),
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

    public function productsCsv(Request $request, FinancialMetricService $financial)
    {
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : null;
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : null;

        $rows = collect($financial->report($from, $to)['products'])
            ->sortByDesc('units_sold')
            ->values();

        $headers = ['product_id', 'product_name', 'units_sold', 'revenue'];

        return response()->streamDownload(function () use ($rows, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['product_id'],
                    $row['product_name'],
                    (string) $row['units_sold'],
                    number_format($row['revenue'] / 100, 2, '.', ''),
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
