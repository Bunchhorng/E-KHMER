<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'inventory_id' => (int) $this->inventory_id,
            'shop_id' => $this->shop_id === null ? null : (int) $this->shop_id,
            'type' => $this->type,
            'quantity' => (int) $this->quantity,
            'balance_after' => $this->balance_after !== null ? (int) $this->balance_after : null,
            'reference' => $this->reference,
            'note' => $this->note,
            'created_by' => $this->whenLoaded('createdBy') && $this->createdBy !== null ? [
                'id' => (int) $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}