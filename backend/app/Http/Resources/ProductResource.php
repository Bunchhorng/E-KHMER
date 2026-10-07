<?php

namespace App\Http\Resources;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'shop_id' => $this->shop_id === null ? null : (int) $this->shop_id,
            'shop' => $this->whenLoaded('shop', fn () => $this->shop === null ? null : [
                'id' => (int) $this->shop->id,
                'name' => $this->shop->name,
                'slug' => $this->shop->slug,
                'code' => $this->shop->code,
                'logo' => $this->shop->logo,
            ]),
            'short_description' => $this->short_description,
            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price !== null ? (float) $this->compare_at_price : null,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => (int) $this->rating_count,
            'is_featured' => (bool) $this->is_featured,
            'is_active' => (bool) $this->is_active,
            'in_stock' => $this->inStock ?? $this->computeInStock(),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants
                ->filter(fn ($variant) => (bool) $variant->is_active)
                ->map(fn ($variant) => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'price' => $variant->price !== null ? (float) $variant->price : (float) $this->price,
                    'in_stock' => $this->variantInStock($variant),
                    // Without this the storefront cannot render colour/size swatches
                    // on a listing card, because the card only has the list payload.
                    'attributes' => $this->variantAttributes($variant),
                ])
                ->values()),
            'colors' => $this->whenLoaded('variants', fn () => $this->attributeSummary('color')),
            'sizes' => $this->whenLoaded('variants', fn () => $this->attributeSummary('size')),
            'cover_image' => $this->resolveCoverImage(),
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand->id,
                'slug' => $this->brand->slug,
                'name' => $this->brand->name,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'slug' => $this->category->slug,
                'name' => $this->category->name,
            ]),
        ];
    }

    protected function variantAttributes(ProductVariant $variant): array
    {
        if (! $variant->relationLoaded('attributeValues')) {
            return [];
        }

        return $variant->attributeValues
            ->map(fn ($pivot) => [
                'attribute_slug' => $pivot->value?->attribute?->slug,
                'name' => $pivot->value?->attribute?->name,
                'value' => $pivot->value?->value,
                'swatch_color' => $pivot->value?->swatch_color,
            ])
            ->filter(fn ($attribute) => $attribute['value'] !== null)
            ->values()
            ->all();
    }

    /**
     * Distinct values of one attribute across the active variants, so a card can
     * show its swatches without walking the variant list.
     */
    protected function attributeSummary(string $attributeSlug): array
    {
        $summary = [];

        foreach ($this->variants ?? [] as $variant) {
            if (! (bool) $variant->is_active) {
                continue;
            }

            foreach ($this->variantAttributes($variant) as $attribute) {
                if ($attribute['attribute_slug'] !== $attributeSlug) {
                    continue;
                }

                $summary[$attribute['value']] ??= [
                    'value' => $attribute['value'],
                    'name' => $attribute['value'],
                    'swatch_color' => $attribute['swatch_color'],
                ];
            }
        }

        return array_values($summary);
    }

    protected function variantInStock(ProductVariant $variant): bool
    {
        if (! (bool) $variant->is_active) {
            return false;
        }
        $inventory = $this->relationLoaded('variants') ? $variant->inventory : null;
        $quantity = $inventory ? (int) $inventory->quantity - (int) $inventory->reserved_quantity : 0;

        return $quantity > 0;
    }

    protected function computeInStock(): bool
    {
        $variants = $this->variants;
        if ($variants === null) {
            return (float) ($this->inventory?->first()?->quantity ?? 0) > 0;
        }

        foreach ($variants as $variant) {
            if (! (bool) $variant->is_active) {
                continue;
            }
            $inventory = $variant->inventory;
            $quantity = $inventory ? (int) $inventory->quantity - (int) $inventory->reserved_quantity : 0;
            if ($quantity > 0) {
                return true;
            }
        }

        return false;
    }

    protected function resolveCoverImage(): ?string
    {
        if ($this->relationLoaded('images')) {
            $cover = $this->images->firstWhere('is_cover', true);
            $image = $cover ?? $this->images->first();

            return $image?->image_path;
        }

        return $this->cover_image;
    }
}
