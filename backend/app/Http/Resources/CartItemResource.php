<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variant = $this->whenLoaded('variant', function () {
            $product = $this->variant->relationLoaded('product') ? $this->variant->product : null;
            $coverImage = null;
            if ($product !== null && $product->relationLoaded('images')) {
                $cover = $product->images->firstWhere('is_cover', true);
                $image = $cover ?? $product->images->first();
                $coverImage = $image?->image_path;
            }

            return [
                'id' => $this->variant->id,
                'sku' => $this->variant->sku,
                'name' => $this->variant->name,
                'price' => $this->variant->price !== null ? (float) $this->variant->price : null,
                'compare_at_price' => $this->variant->compare_at_price !== null ? (float) $this->variant->compare_at_price : null,
                'in_stock' => $this->resolveVariantInStock($this->variant),
                'attributes' => $this->variant->relationLoaded('attributeValues')
                    ? $this->variant->attributeValues->map(function ($link) {
                        $value = $link->value;
                        $attribute = $value?->attribute;

                        return $value === null ? null : [
                            'name' => $attribute?->name ?? 'Option',
                            'value' => $value->value,
                        ];
                    })->filter()->values()->all()
                    : [],
                'product' => $product !== null ? [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'cover_image' => $coverImage,
                ] : null,
            ];
        });

        return [
            'id' => $this->id,
            'quantity' => (int) $this->quantity,
            'variant' => $variant,
        ];
    }

    protected function resolveVariantInStock($variant): bool
    {
        if ($variant->relationLoaded('inventory')) {
            $inventory = $variant->inventory;
            return $inventory !== null && ((int) $inventory->quantity - (int) $inventory->reserved_quantity) > 0;
        }

        $inventory = $variant->inventory()->first();
        return $inventory !== null && ((int) $inventory->quantity - (int) $inventory->reserved_quantity) > 0;
    }
}
