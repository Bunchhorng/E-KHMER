<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_number' => $this->order?->order_number,
            'customer_name' => $this->order?->user?->name ?? $this->order?->customer_name,
            'method' => $this->method,
            'status' => $this->status,
            'order_payment_status' => $this->order?->payment_status,
            'amount' => (float) $this->amount,
            'currency' => $this->order?->currency,
            'cod_allocations' => $this->method === 'cod' ? CodPaymentResource::collection($this->order->shopOrders()->with(['order.payment', 'shop', 'shipment', 'codCollection'])->get()) : [],
            'transaction_id' => $this->transaction_id,
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'transactions' => $this->whenLoaded('transactions', fn () => $this->transactions->map(
                fn ($tx) => [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'status' => $tx->status,
                    'amount' => (float) $tx->amount,
                    'reference' => $tx->reference,
                    'shop_order_id' => $tx->shop_order_id,
                    'recorded_by' => $tx->meta['actor_name'] ?? null,
                    'created_at' => $tx->created_at?->toISOString(),
                ]
            )),
        ];
    }
}
