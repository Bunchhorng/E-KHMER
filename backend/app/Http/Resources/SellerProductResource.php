<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class SellerProductResource extends ProductResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $inventories = $this->variants->where('is_active', true)->pluck('inventory')->filter();

        return array_merge(parent::toArray($request), [
            'sku' => $this->sku,
            'available_quantity' => $inventories->sum(fn ($inventory) => $inventory->available_quantity),
            'is_low_stock' => $inventories->contains(fn ($inventory) => $inventory->is_low_stock),
        ]);
    }
}
