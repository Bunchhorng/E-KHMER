<?php

namespace App\Http\Resources;

use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->resource->loadMissing('items')->items;
        $totals = app(CartService::class)->totals($this->resource, $items);
        unset($totals['discount_applicable']);

        return [
            'id' => $this->id,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'totals' => [...$totals, 'discount_amount' => 0.0],
        ];
    }
}
