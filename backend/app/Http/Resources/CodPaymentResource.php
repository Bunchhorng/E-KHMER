<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A shop's COD allocation is settled by its collection transaction, not a second payment record. */
class CodPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $collection = $this->codCollection;

        return [
            'id' => $this->id, 'payment_id' => $this->order->payment->id,
            'order_number' => $this->order->order_number, 'shop_order_number' => $this->shop_order_number,
            'shop' => ['id' => $this->shop_id, 'name' => $this->shop?->name],
            'method' => 'cod', 'amount' => (float) $this->total, 'currency' => $this->order->currency,
            'status' => $this->codPaymentStatus(), 'can_collect' => $this->canCollectCod(),
            'collected_at' => $collection?->created_at?->toISOString(),
            'collected_by' => $collection?->meta['actor_name'] ?? null,
        ];
    }
}
