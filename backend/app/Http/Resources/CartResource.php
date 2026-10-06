<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subtotal = 0.0;
        $itemsCount = 0;

        if ($this->relationLoaded('items')) {
            foreach ($this->items as $item) {
                $price = 0.0;
                if ($item->relationLoaded('variant') && $item->variant !== null) {
                    $price = $item->variant->price !== null
                        ? (float) $item->variant->price
                        : (float) ($item->variant->product?->price ?? 0);
                }
                $subtotal += $price * (int) $item->quantity;
                $itemsCount += (int) $item->quantity;
            }
        }

        $subtotal = round($subtotal, 2);
        $tax = round($subtotal * 0.10, 2);

        return [
            'id' => $this->id,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'totals' => [
                'subtotal' => $subtotal,
                'discount_amount' => 0.0,
                'tax_amount' => $tax,
                'total' => round($subtotal + $tax, 2),
                'items_count' => $itemsCount,
            ],
        ];
    }
}
