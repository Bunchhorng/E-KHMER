<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShopOrderTransitionRequest;
use App\Http\Requests\ShopShipmentRequest;
use App\Http\Resources\ShopOrderResource;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Services\ShopOrderFulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SellerShopOrderController extends Controller
{
    public function __construct(private ShopOrderFulfillmentService $fulfillment) {}

    public function index(Request $request, Shop $shop): AnonymousResourceCollection
    {
        $paginator = ShopOrder::query()
            ->where('shop_id', $shop->id)
            ->with(['order', 'shop', 'items.shop', 'shipment', 'trackingEvents'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $query->whereHas('order', fn ($order) => $order->where('order_number', 'like', $term)->orWhere('customer_name', 'like', $term));
            })
            ->latest('id')
            ->paginate(15);

        return ShopOrderResource::collection($paginator);
    }

    public function show(Shop $shop, ShopOrder $shopOrder): ShopOrderResource
    {
        return new ShopOrderResource($this->fulfillment->load($shopOrder));
    }

    public function transition(ShopOrderTransitionRequest $request, Shop $shop, ShopOrder $shopOrder): ShopOrderResource
    {
        $data = $request->validated();

        return new ShopOrderResource($this->fulfillment->transition($shopOrder, $data['status'], $request->user()->id, $data['note'] ?? null, array_intersect_key($data, array_flip(['carrier', 'tracking_number']))));
    }

    public function shipment(ShopShipmentRequest $request, Shop $shop, ShopOrder $shopOrder): ShopOrderResource
    {
        return new ShopOrderResource($this->fulfillment->updateShipment($shopOrder, $request->validated(), $request->user()->id));
    }
}
