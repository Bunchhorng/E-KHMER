<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'shop_id' => $this->shop_id === null ? null : (int) $this->shop_id,
            'shop' => $this->relationLoaded('shop') && $this->shop !== null
                ? [
                    'id' => (int) $this->shop->id,
                    'name' => $this->shop->name,
                    'slug' => $this->shop->slug,
                ]
                : null,
            'product_name' => $this->product_name,
            'variant_label' => $this->variant_label,
            'sku' => $this->sku,
            'image_path' => $this->image_path,
            'unit_price' => (float) $this->unit_price,
            'quantity' => (int) $this->quantity,
            'line_total' => (float) $this->line_total,
        ];
    }
}
