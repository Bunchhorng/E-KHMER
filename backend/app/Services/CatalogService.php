<?php

namespace App\Services;

use App\Http\Requests\CatalogFilterRequest;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CatalogService
{
    /**
     * Public catalog listing.
     *
     * `$filters` is expected to come from {@see CatalogFilterRequest::filters()}.
     * Pagination is still clamped defensively because the service is also called
     * directly (search, admin widgets, queued jobs).
     */
    public function filtered(array $filters): LengthAwarePaginator
    {
        $query = Product::query()
            ->active()
            ->with(['brand', 'category', 'images', 'variants.inventory', 'variants.attributeValues.value.attribute']);

        if (($term = trim((string) ($filters['q'] ?? ''))) !== '') {
            $needle = '%' . $this->escapeLike(mb_strtolower($term)) . '%';
            $query->where(function (Builder $q) use ($needle) {
                $q->whereRaw('LOWER(name) LIKE ? ESCAPE ?', [$needle, '!'])
                    ->orWhereRaw('LOWER(short_description) LIKE ? ESCAPE ?', [$needle, '!'])
                    ->orWhereRaw('LOWER(description) LIKE ? ESCAPE ?', [$needle, '!'])
                    ->orWhereRaw('LOWER(sku) LIKE ? ESCAPE ?', [$needle, '!']);
            });
        }

        if ($slug = trim((string) ($filters['category'] ?? ''))) {
            $query->whereHas('category', fn (Builder $q) => $q->where('slug', $slug));
        }

        if ($slug = trim((string) ($filters['brand'] ?? ''))) {
            $query->whereHas('brand', fn (Builder $q) => $q->where('slug', $slug));
        }

        $this->applyPriceRange($query, $filters);
        $this->applyAttributeFilters($query, $filters);

        if (!empty($filters['rating']) && (int) $filters['rating'] > 0) {
            $query->where('rating_avg', '>=', (float) $filters['rating']);
        }

        if (!empty($filters['stock'])) {
            $query->whereHas('variants', function (Builder $q) {
                $q->where('is_active', true)->whereHas('inventory', function (Builder $iq) {
                    $iq->whereRaw('quantity - reserved_quantity > 0');
                });
            });
        }

        $this->applySort($query, (string) ($filters['sort'] ?? 'newest'));

        $perPage = max(1, min((int) ($filters['perPage'] ?? 12), CatalogFilterRequest::MAX_PER_PAGE));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findBySlug(string $slug): ?Product
    {
        return Product::query()
            ->active()
            ->with([
                'brand',
                'category',
                'images',
                'variants' => fn ($q) => $q->with(['inventory', 'attributeValues.value.attribute']),
            ])
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Curated homepage rail. The limit is clamped here as well because the
     * parameter is caller supplied.
     */
    public function featured(int $limit = 8): Collection
    {
        $limit = max(1, min($limit, 24));

        return Product::query()
            ->active()
            ->with(['brand', 'category', 'images', 'variants.inventory'])
            ->where('is_featured', true)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Sidebar data for the catalog. Counts only include products a shopper can
     * actually reach, so the facet numbers match the listing totals.
     */
    public function facets(): array
    {
        $brands = Brand::query()
            ->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->active()])
            ->orderBy('name')
            ->get()
            ->map(fn ($brand) => [
                'slug' => $brand->slug,
                'name' => $brand->name,
                'count' => $brand->products_count,
            ])
            ->all();

        $categories = Category::query()
            ->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->active()])
            ->orderBy('name')
            ->get()
            ->map(fn ($cat) => [
                'slug' => $cat->slug,
                'name' => $cat->name,
                'count' => $cat->products_count,
            ])
            ->all();

        $attributes = Attribute::query()
            ->where('is_filterable', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Attribute $attribute) => [
                'slug' => $attribute->slug,
                'name' => $attribute->name,
                'type' => $attribute->type,
                'values' => $this->valuesFor($attribute),
            ])
            ->values()
            ->all();

        $priceRange = Product::query()->active()->selectRaw('MIN(price) as min_price, MAX(price) as max_price')->first();

        return [
            'brands' => $brands,
            'categories' => $categories,
            // Retained for the existing color/size swatch UI in the frontend.
            'colors' => $this->valuesForSlug('color'),
            'sizes' => $this->valuesForSlug('size'),
            'attributes' => $attributes,
            'price_range' => [
                'min' => $priceRange?->min_price,
                'max' => $priceRange?->max_price,
            ],
            'max_per_page' => CatalogFilterRequest::MAX_PER_PAGE,
        ];
    }

    public function search(string $term)
    {
        return $this->filtered(['q' => $term, 'perPage' => 20]);
    }

    /**
     * Dynamic variant resolution (PRD-19): map a set of attribute value ids to the
     * concrete product variants that satisfy *all* of them, with live stock.
     *
     * Used by the product detail page to resolve "Size: L + Color: Blue" to a
     * single purchasable variant and by the catalog to power filter chips.
     */
    public function resolveVariants(array $attributeValueIds, ?int $productId = null): Collection
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $attributeValueIds ?: []),
            fn ($id) => $id > 0
        )));

        if ($ids === []) {
            return collect();
        }

        $query = ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', fn (Builder $q) => $q->active())
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            // "has at least N matching pivots" == the variant carries every
            // selected value, which is what AND semantics require.
            ->whereHas('attributeValues', fn ($q) => $q->whereIn('attribute_value_id', $ids), '>=', count($ids))
            ->with(['product:id,name,slug', 'inventory', 'attributeValues.value.attribute']);

        return $query->orderBy('id')->get()->map(fn (ProductVariant $variant) => [
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'sku' => $variant->sku,
            'name' => $variant->name,
            'price' => $variant->price ?? $variant->product?->price,
            'available_quantity' => (int) ($variant->inventory?->available_quantity ?? 0),
            'in_stock' => (int) ($variant->inventory?->available_quantity ?? 0) > 0,
            'attributes' => $variant->attributeValues->map(fn ($pivot) => [
                'attribute' => $pivot->value?->attribute?->slug,
                'value' => $pivot->value?->value,
                'swatch_color' => $pivot->value?->swatch_color,
            ])->values()->all(),
        ]);
    }

    /**
     * Facet values for one attribute, counting only reachable products.
     */
    protected function valuesFor(Attribute $attribute): array
    {
        return AttributeValue::query()
            ->where('attribute_id', $attribute->id)
            ->orderBy('value')
            ->get()
            ->map(fn (AttributeValue $value) => [
                'slug' => Str::slug($value->value),
                // `value` is what the catalog filter matches on (exact, case
                // insensitive); `slug` is only for stable DOM keys.
                'value' => $value->value,
                'name' => $value->value,
                'swatch_color' => $value->swatch_color,
                'count' => $value->variants()
                    ->where('is_active', true)
                    ->whereHas('product', fn ($q) => $q->active())
                    ->count(),
            ])
            ->values()
            ->all();
    }

    protected function valuesForSlug(string $attributeSlug): array
    {
        $attribute = Attribute::query()
            ->where('slug', $attributeSlug)
            ->first();

        return $attribute === null ? [] : $this->valuesFor($attribute);
    }

    protected function applyPriceRange(Builder $query, array $filters): void
    {
        $min = $filters['min'] ?? null;
        $max = $filters['max'] ?? null;

        if ($min !== null && $min !== '') {
            $query->where('price', '>=', (float) $min);
        }

        if ($max !== null && $max !== '') {
            $query->where('price', '<=', (float) $max);
        }
    }

    /**
     * Attribute filters are AND-across-attributes, OR-within-an-attribute:
     * colors=Red,Blue AND sizes=L means "Red or Blue, in size L".
     *
     * The previous implementation grouped colours and sizes into one OR block, so
     * selecting Red + L matched any product with either Red or L, and matched on
     * `LIKE '%value%'`, so "Red" also matched "Crimson Red" and "S".
     */
    protected function applyAttributeFilters(Builder $query, array $filters): void
    {
        $groups = [
            'color' => $this->normaliseList($filters['colors'] ?? null),
            'size' => $this->normaliseList($filters['sizes'] ?? null),
        ];

        foreach ($groups as $attributeSlug => $values) {
            if ($values === []) {
                continue;
            }

            $query->where(function (Builder $product) use ($attributeSlug, $values) {
                foreach ($values as $value) {
                    $product->orWhereHas(
                        'variants.attributeValues.value',
                        function (Builder $valueQuery) use ($attributeSlug, $value) {
                            $valueQuery
                                ->whereHas('attribute', fn (Builder $a) => $a->where('slug', $attributeSlug))
                                ->whereRaw('LOWER(value) = ?', [mb_strtolower($value)]);
                        }
                    );
                }
            });
        }
    }

    protected function applySort(Builder $query, string $sort): void
    {
        // Every sort gets a deterministic id tiebreaker, otherwise paginated
        // results can repeat or drop rows when the sort column ties.
        match ($sort) {
            'price-asc' => $query->orderBy('price', 'asc')->orderBy('id', 'asc'),
            'price-desc' => $query->orderBy('price', 'desc')->orderBy('id', 'asc'),
            'name-asc' => $query->orderBy('name', 'asc')->orderBy('id', 'asc'),
            'name-desc' => $query->orderBy('name', 'desc')->orderBy('id', 'asc'),
            'rating' => $query->orderBy('rating_avg', 'desc')->orderBy('rating_count', 'desc')->orderBy('id', 'asc'),
            'popularity' => $query->orderBy('rating_count', 'desc')->orderBy('id', 'asc'),
            'featured' => $query->orderByDesc('is_featured')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    protected function normaliseList($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(
                array_map(fn ($v) => trim((string) $v), $value),
                fn ($v) => $v !== ''
            ));
        }

        if (is_string($value) && $value !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
        }

        return [];
    }
}