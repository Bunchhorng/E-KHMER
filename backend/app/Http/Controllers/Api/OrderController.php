<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderListResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orders)
    {
    }

    public function index(Request $request)
    {
        $paginator = $this->orders->listFor($request->user(), $request->query('status'));

        return [
            'data' => OrderListResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function show(Request $request, string $orderNumber)
    {
        return $this->resolveForUser($request->user(), $orderNumber);
    }

    public function guest(Request $request, string $orderNumber)
    {
        $sessionId = $request->header('X-Session-Id');

        if ($sessionId === null || trim($sessionId) === '') {
            abort(422, 'A session identifier is required for guest order lookup.');
        }

        $order = Order::where('order_number', $orderNumber)->first();

        if ($order === null) {
            abort(404, 'Order not found.');
        }

        $stored = $order->payment?->provider_data['session_id'] ?? null;

        if ($stored !== 'guest_' . md5((string) $sessionId)) {
            abort(404, 'Order not found.');
        }

        return new OrderResource($order->load(['items.shop', 'payment', 'shipments', 'trackingEvents.changedBy']));
    }

    public function receipt(Request $request, string $orderNumber)
    {
        $order = $this->resolveForUser($request->user(), $orderNumber);
        $order->load(['items.shop', 'payment']);

        return Pdf::loadView('reports.receipt', ['order' => $order])
            ->download('receipt-' . $order->order_number . '.pdf');
    }

    public function cancel(Request $request, string $orderNumber)
    {
        $order = $this->resolveForUser($request->user(), $orderNumber);
        $order = $this->orders->cancelOwn($order, $request->user()?->id);

        return new OrderResource($order);
    }

    protected function resolveForUser($user, string $orderNumber): \App\Models\Order
    {
        try {
            return $this->orders->findByNumber($user, $orderNumber);
        } catch (ModelNotFoundException $e) {
            abort(404, 'Order not found.');
        }
    }
}
