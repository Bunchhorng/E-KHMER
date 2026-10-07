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
            'can_cancel' => $this->canBeCancelled(),
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
            'shop_orders' => $this->whenLoaded('shopOrders', fn () => $this->shopOrders->map(function ($shopOrder) use ($request) {
                $shopOrder->setRelation('order', $this->resource);

                return (new ShopOrderResource($shopOrder))->toArray($request);
            })->values()),
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
                $shipment = $this->shipments->firstWhere('shop_order_id', null)
                    ?? ($this->shipments->count() === 1 ? $this->shipments->first() : null);
                if ($shipment === null) {
                    return null;
                }

                return [
                    'shop_order_id' => $shipment->shop_order_id,
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
