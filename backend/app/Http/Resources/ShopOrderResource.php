<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'shop_order_number' => $this->shop_order_number,
            'order_number' => $this->order?->order_number,
            'status' => $this->status,
            'parent_status' => $this->order?->status,
            'payment_status' => $this->order?->payment_status,
            'currency' => $this->order?->currency ?? 'USD',
            'customer_name' => $this->order?->customer_name,
            'email' => $this->order?->email,
            'phone' => $this->order?->phone,
            'shipping_address' => $this->order?->shipping_address,
            'placed_at' => $this->order?->placed_at?->toISOString(),
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'shipping_amount' => (float) $this->shipping_amount,
            'total' => (float) $this->total,
            'items_count' => $this->relationLoaded('items') ? $this->items->sum('quantity') : null,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'shop' => $this->whenLoaded('shop', fn () => $this->shop === null ? null : [
                'id' => $this->shop->id, 'name' => $this->shop->name, 'slug' => $this->shop->slug, 'logo' => $this->shop->logo,
            ]),
            'shipment' => $this->whenLoaded('shipment', fn () => $this->shipment === null ? null : [
                'id' => $this->shipment->id,
                'status' => $this->shipment->status,
                'carrier' => $this->shipment->carrier,
                'tracking_number' => $this->shipment->tracking_number,
                'shipped_at' => $this->shipment->shipped_at?->toISOString(),
                'delivered_at' => $this->shipment->delivered_at?->toISOString(),
            ]),
            'tracking_events' => $this->whenLoaded('trackingEvents', fn () => $this->trackingEvents->map(fn ($event) => [
                'id' => $event->id,
                'from_status' => $event->from_status,
                'status' => $event->status,
                'description' => $event->description,
                'at' => $event->created_at?->toISOString(),
            ])->values()),
            'allowed_transitions' => $this->allowedTransitions(),
        ];
    }
}
