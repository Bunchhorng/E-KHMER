<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    public function __construct() {}

    public function index(Request $request, DashboardService $dashboard)
    {
        $query = ($request->boolean('attention') ? $dashboard->paymentExceptionsQuery() : Payment::query())
            ->with(['order.user'])->orderByDesc('created_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('method') && $request->method !== 'all') {
            $query->where('method', $request->method);
        }

        if ($request->filled('q')) {
            $term = '%'.trim((string) $request->q).'%';
            $query->where(function ($q) use ($term) {
                $q->where('transaction_id', 'like', $term)
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', $term));
            });
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $paginator = $query->paginate(15);

        return [
            'data' => PaymentResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function show(Payment $payment)
    {
        return new PaymentResource($payment->load(['order.user', 'transactions']));
    }
}
