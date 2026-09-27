<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderTransitionRequest;
use App\Http\Resources\OrderListResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(protected OrderService $orders)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        $query = Order::with(['items', 'user'])->withCount('items')->orderByDesc('placed_at');

        // Branch scoping: a shop manager only ever sees orders that contain at
        // least one of their own line items.
        $shopIds = $this->visibleShopIds($request);

        if ($shopIds !== null) {
            if ($shopIds === []) {
                return $this->emptyOrderPage();
            }

            $query->whereHas('items', fn ($q) => $q->whereIn('shop_id', $shopIds));
        }

        if ($request->filled('shop_id') && $request->shop_id !== 'all') {
            $query->whereHas('items', fn ($q) => $q->where('shop_id', (int) $request->shop_id));
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('q')) {
            $term = '%' . trim((string) $request->q) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('order_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        $paginator = $query->paginate(15);

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

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        $order->load(['items.shop', 'payment', 'shipments', 'trackingEvents']);

        $this->restrictItemsToActorShop($order, request());

        return new OrderResource($order);
    }

    public function transition(OrderTransitionRequest $request, Order $order)
    {
        $this->authorize('view', $order);

        $order = $this->orders->transition($order, $request->status);

        return new OrderResource($order);
    }

    public function receipt(Order $order)
    {
        $this->authorize('view', $order);

        $order->load(['items.shop', 'payment']);

        $this->restrictItemsToActorShop($order, request());

        return Pdf::loadView('reports.receipt', ['order' => $order])
            ->download('receipt-' . $order->order_number . '.pdf');
    }

    /**
     * Shop ids the acting user may see, or null when unrestricted (super admin).
     */
    private function visibleShopIds(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || $user->isAdmin()) {
            return null;
        }

        return $user->shops()
            ->wherePivot('status', 'active')
            ->pluck('shops.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Drop order lines belonging to other branches so a shop manager never sees
     * a competitor's items, prices or customers inside a shared order.
     */
    private function restrictItemsToActorShop(Order $order, Request $request): void
    {
        $user = $request->user();

        if ($user === null || $user->isAdmin()) {
            return;
        }

        $shopIds = $this->visibleShopIds($request) ?? [];

        $order->setRelation(
            'items',
            $order->items->filter(fn ($item) => in_array((int) $item->shop_id, $shopIds, true))->values()
        );
    }

    private function emptyOrderPage(): array
    {
        return [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 15,
                'total' => 0,
            ],
        ];
    }
}
