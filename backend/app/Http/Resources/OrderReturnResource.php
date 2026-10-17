<?php

namespace App\Http\Resources;

use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'order_number' => $this->order?->order_number, 'product_name' => $this->item?->product_name,
            'variant_label' => $this->item?->variant_label, 'quantity' => $this->quantity, 'status' => $this->status,
            'reason' => $this->reason, 'resolution_note' => $this->resolution_note, 'refund_amount' => (float) $this->refund_amount,
            'currency' => $this->order?->currency, 'photo_count' => count($this->photos ?? []),
            'refund_memo' => RefundService::memo($this->order, $this->id),
            'shop_name' => $this->shop?->name, 'created_at' => $this->created_at?->toISOString()];
    }
}
