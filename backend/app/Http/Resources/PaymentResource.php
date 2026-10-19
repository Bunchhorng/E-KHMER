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
                    'created_at' => $tx->created_at?->toISOString(),
                ]
            )),
        ];
    }
}
