<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShopOrderTransitionRequest;
use App\Http\Requests\ShopShipmentRequest;
use App\Http\Resources\ShopOrderResource;
use App\Models\Order;
use App\Models\ShopOrder;
use App\Services\ShopOrderFulfillmentService;

class AdminShopOrderController extends Controller
{
    public function __construct(private ShopOrderFulfillmentService $fulfillment) {}

    public function transition(ShopOrderTransitionRequest $request, Order $order, ShopOrder $shopOrder): ShopOrderResource
    {
        abort_unless((int) $shopOrder->order_id === (int) $order->id, 404);
        $data = $request->validated();

        return new ShopOrderResource($this->fulfillment->transition($shopOrder, $data['status'], $request->user()->id, $data['note'] ?? null, array_intersect_key($data, array_flip(['carrier', 'tracking_number']))));
    }

    public function shipment(ShopShipmentRequest $request, Order $order, ShopOrder $shopOrder): ShopOrderResource
    {
        abort_unless((int) $shopOrder->order_id === (int) $order->id, 404);

        return new ShopOrderResource($this->fulfillment->updateShipment($shopOrder, $request->validated(), $request->user()->id));
    }
}
