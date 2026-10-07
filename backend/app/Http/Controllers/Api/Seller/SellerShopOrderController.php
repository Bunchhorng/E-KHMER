<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopOrder;
use Illuminate\Http\Request;

class SellerShopOrderController extends Controller
{
    public function index(Request $request, Shop $shop): array
    {
        $paginator = ShopOrder::query()
            ->where('shop_id', $shop->id)
            ->with(['order:id,order_number,customer_name,email,phone,placed_at,payment_status', 'items'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $query->whereHas('order', fn ($order) => $order->where('order_number', 'like', $term)->orWhere('customer_name', 'like', $term));
            })
            ->latest('id')
            ->paginate(15);

        return [
            'data' => $paginator->getCollection()->map(fn (ShopOrder $shopOrder) => [
                'id' => $shopOrder->id,
                'shop_order_number' => $shopOrder->shop_order_number,
                'status' => $shopOrder->status,
                'total' => (float) $shopOrder->total,
                'items_count' => $shopOrder->items->sum('quantity'),
                'customer_name' => $shopOrder->order?->customer_name,
                'payment_status' => $shopOrder->order?->payment_status,
                'placed_at' => $shopOrder->order?->placed_at?->toISOString(),
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
