<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'sku' => $this->sku,
            'weight' => $this->weight !== null ? (float) $this->weight : null,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price !== null ? (float) $this->compare_at_price : null,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => (int) $this->rating_count,
            'is_featured' => (bool) $this->is_featured,
            'is_active' => (bool) $this->is_active,
            'shop_id' => $this->shop_id !== null ? (int) $this->shop_id : null,
            'shop' => $this->whenLoaded('shop', fn () => $this->shop === null ? null : [
                'id' => (int) $this->shop->id,
                'slug' => $this->shop->slug,
                'name' => $this->shop->name,
                'code' => $this->shop->code,
                'logo' => $this->shop->logo,
            ]),
            'in_stock' => $this->inStock ?? $this->computeInStock(),
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
            'gallery' => $this->resolveGallery(),
            // The admin editor must receive inactive variants too. Otherwise it
            // submits only the active rows and syncVariants() interprets every
            // omitted inactive row as a deletion. Storefront responses remain
            // limited to sellable variants.
            'attributes' => $this->resolveAttributes($request->is('api/admin/*')),
            'variants' => $this->resolveVariants($request->is('api/admin/*')),
        ];
    }

    protected function computeInStock(): bool
    {
        foreach ($this->resolveActiveVariants() as $variant) {
            if ($this->variantAvailable($variant) > 0) {
                return true;
            }
        }

        return false;
    }

    protected function resolveCoverImage(): ?string
    {
        $images = $this->resolveGalleryImages();
        $cover = $images->firstWhere('is_cover', true);
        $image = $cover ?? $images->first();

        return $image?->image_path;
    }

    protected function resolveGalleryImages()
    {
        if ($this->relationLoaded('images')) {
            return $this->images->sortBy('sort_order')->values();
        }

        return collect();
    }

    protected function resolveGallery(): array
    {
        return $this->resolveGalleryImages()
            ->map(fn ($image) => [
                'id' => $image->id,
                'image_path' => $image->image_path,
                'alt_text' => $image->alt_text,
                'sort_order' => (int) $image->sort_order,
                'is_cover' => (bool) $image->is_cover,
            ])
            ->values()
            ->all();
    }

    protected function resolveActiveVariants()
    {
        $variants = $this->relationLoaded('variants') ? $this->variants : $this->variants()->get();

        return $variants->filter(fn ($variant) => (bool) $variant->is_active)->values();
    }

    protected function variantAvailable($variant): int
    {
        $inventory = $this->relationLoaded('variants') && $variant->relationLoaded('inventory')
            ? $variant->inventory
            : $variant->inventory()->first();

        if ($inventory === null) {
            return 0;
        }

        return (int) $inventory->quantity - (int) $inventory->reserved_quantity;
    }

    protected function resolveAttributes(bool $includeInactive = false): array
    {
        $grouped = [];

        foreach ($this->resolveVariantsForResponse($includeInactive) as $variant) {
            foreach ($variant->attributeValues as $vav) {
                if ($vav->value === null) {
                    continue;
                }
                $attribute = $vav->value->attribute;
                if ($attribute === null) {
                    continue;
                }
                $key = $attribute->id;
                if (! isset($grouped[$key])) {
                    $grouped[$key] = [
                        'id' => $attribute->id,
                        'name' => $attribute->name,
                        'slug' => $attribute->slug,
                        'type' => $attribute->type,
                        'values' => [],
                    ];
                }
                $existing = collect($grouped[$key]['values'])->first(fn ($v) => $v['id'] === $vav->value->id);
                if ($existing === null) {
                    $grouped[$key]['values'][] = [
                        'id' => $vav->value->id,
                        'value' => $vav->value->value,
                        'swatch_color' => $vav->value->swatch_color,
                    ];
                }
            }
        }

        return array_values($grouped);
    }

    protected function resolveVariants(bool $includeInactive = false): array
    {
        return $this->resolveVariantsForResponse($includeInactive)
            ->map(function ($variant) {
                $attributes = [];
                foreach ($variant->attributeValues as $vav) {
                    if ($vav->value === null || $vav->value->attribute === null) {
                        continue;
                    }
                    $attributes[] = [
                        'attribute_slug' => $vav->value->attribute->slug,
                        'name' => $vav->value->attribute->name,
                        'value' => $vav->value->value,
                    ];
                }

                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'price' => $variant->price !== null ? (float) $variant->price : (float) $this->price,
                    'compare_at_price' => $variant->compare_at_price !== null ? (float) $variant->compare_at_price : null,
                    'is_default' => (bool) $variant->is_default,
                    'is_active' => (bool) $variant->is_active,
                    'available_quantity' => $this->variantAvailable($variant),
                    'in_stock' => $this->variantAvailable($variant) > 0,
                    'attributes' => $attributes,
                ];
            })
            ->values()
            ->all();
    }

    protected function resolveVariantsForResponse(bool $includeInactive)
    {
        $variants = $this->relationLoaded('variants') ? $this->variants : $this->variants()->get();

        return $includeInactive
            ? $variants->values()
            : $variants->filter(fn ($variant) => (bool) $variant->is_active)->values();
    }
}
