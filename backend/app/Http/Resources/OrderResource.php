<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'shipping_amount' => (float) $this->shipping_amount,
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'shipping_address' => $this->shipping_address,
            'billing_address' => $this->billing_address,
            'email' => $this->email,
            'phone' => $this->phone,
            'customer_name' => $this->customer_name,
            'note' => $this->note,
            'coupon_code' => $this->coupon_code,
            'placed_at' => $this->placed_at?->toISOString(),
            'tracking_events' => $this->whenLoaded('trackingEvents', function () {
                return $this->trackingEvents->map(fn ($e) => [
                    'from_status' => $e->from_status,
                    'status' => $e->status,
                    'description' => $e->description,
                    'changed_by' => $e->relationLoaded('changedBy') && $e->changedBy !== null ? [
                        'id' => (int) $e->changedBy->id,
                        'name' => $e->changedBy->name,
                    ] : null,
                    'at' => $e->created_at?->toISOString(),
                ])->values();
            }),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'shop_orders' => $this->whenLoaded('shopOrders', fn () => $this->shopOrders->map(fn ($shopOrder) => [
                'shop_order_number' => $shopOrder->shop_order_number,
                'status' => $shopOrder->status,
                'subtotal' => (float) $shopOrder->subtotal,
                'discount_amount' => (float) $shopOrder->discount_amount,
                'tax_amount' => (float) $shopOrder->tax_amount,
                'shipping_amount' => (float) $shopOrder->shipping_amount,
                'total' => (float) $shopOrder->total,
                'shop' => $shopOrder->relationLoaded('shop') ? [
                    'id' => $shopOrder->shop?->id,
                    'name' => $shopOrder->shop?->name,
                    'slug' => $shopOrder->shop?->slug,
                ] : null,
                'items' => $shopOrder->relationLoaded('items') ? OrderItemResource::collection($shopOrder->items) : [],
            ])->values()),
            'payment' => $this->whenLoaded('payment', function () {
                if ($this->payment === null) {
                    return null;
                }
                return [
                    'id' => $this->payment->id,
                    'method' => $this->payment->method,
                    'status' => $this->payment->status,
                    'transaction_id' => $this->payment->transaction_id,
                    'amount' => (float) $this->payment->amount,
                    'paid_at' => $this->payment->paid_at?->toISOString(),
                ];
            }),
            'shipment' => $this->whenLoaded('shipments', function () {
                $shipment = $this->shipments->first();
                if ($shipment === null) {
                    return null;
                }
                return [
                    'tracking_number' => $shipment->tracking_number,
                    'carrier' => $shipment->carrier,
                    'status' => $shipment->status,
                    'shipped_at' => $shipment->shipped_at?->toISOString(),
                    'delivered_at' => $shipment->delivered_at?->toISOString(),
                    'address_snapshot' => $shipment->address_snapshot,
                ];
            }),
        ];
    }
}
