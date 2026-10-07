<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'product_variant_id' => (int) $this->product_variant_id,
            'shop_id' => $this->shop_id === null ? null : (int) $this->shop_id,
            'shop' => $this->relationLoaded('shop') && $this->shop !== null
                ? [
                    'id' => (int) $this->shop->id,
                    'name' => $this->shop->name,
                    'slug' => $this->shop->slug,
                    'code' => $this->shop->code,
                ]
                : null,
            'product' => $this->variant !== null && $this->variant->relationLoaded('product')
                ? (
                    $this->variant->product !== null
                        ? [
                            'id' => (int) $this->variant->product->id,
                            'name' => $this->variant->product->name,
                            'slug' => $this->variant->product->slug,
                        ]
                        : null
                )
                : null,
            'variant' => $this->whenLoaded('variant', fn () => [
                'id' => (int) $this->variant->id,
                'name' => $this->variant->name,
                'sku' => $this->variant->sku,
                'is_active' => (bool) $this->variant->is_active,
            ]),
            'variant_label' => $this->variantLabel(),
            'product_name' => $this->variant?->product?->name,
            'sku' => $this->variant?->sku,
            'quantity' => (int) $this->quantity,
            'reserved_quantity' => (int) $this->reserved_quantity,
            'available_quantity' => (int) $this->available_quantity,
            'sold_count' => (int) $this->sold_count,
            'low_stock_threshold' => (int) $this->low_stock_threshold,
            'is_low_stock' => (bool) $this->is_low_stock,
            'is_out_of_stock' => (bool) ($this->available_quantity <= 0),
            'low_stock_notified_at' => $this->low_stock_notified_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    protected function variantLabel(): string
    {
        if (! $this->relationLoaded('variant')) {
            return '';
        }

        $parts = [];

        foreach ($this->variant->attributeValues as $vav) {
            $value = $vav->value;
            $attribute = $value?->attribute;

            if ($value === null) {
                continue;
            }

            $parts[] = ($attribute?->name ?? 'Option').': '.$value->value;
        }

        if (count($parts) === 0 && $this->variant->name !== null) {
            return $this->variant->name;
        }

        return implode(', ', $parts);
    }
}
