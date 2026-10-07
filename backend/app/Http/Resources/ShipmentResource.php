<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'shop_order_id' => $this->shop_order_id,
            'shop' => $this->whenLoaded('shopOrder', fn () => $this->shopOrder?->shop === null ? null : [
                'id' => $this->shopOrder->shop->id, 'name' => $this->shopOrder->shop->name,
            ]),
            'order_number' => $this->order?->order_number,
            'customer_name' => $this->order?->customer_name,
            'shipping_method_id' => $this->shipping_method_id,
            'shipping_method' => $this->whenLoaded('method', fn () => $this->method?->name),
            'tracking_number' => $this->tracking_number,
            'carrier' => $this->carrier,
            'status' => $this->status,
            'address_snapshot' => $this->address_snapshot,
            'shipped_at' => $this->shipped_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
