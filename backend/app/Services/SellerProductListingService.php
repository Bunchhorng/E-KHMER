<?php

namespace App\Services;

use App\Http\Resources\SellerProductResource;
use App\Models\Category;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;

class SellerProductListingService
{
    public function listing(Shop $shop, array $filters): array
    {
        $base = $shop->products()->getQuery();
        $lowStock = fn (Builder $variants) => $variants->where('is_active', true)
            ->whereHas('inventory', fn (Builder $inventory) => $inventory->whereRaw('quantity - reserved_quantity <= low_stock_threshold'));
        $inStock = fn (Builder $variants) => $variants->where('is_active', true)
            ->whereHas('inventory', fn (Builder $inventory) => $inventory->whereColumn('quantity', '>', 'reserved_quantity'));
        $summary = [
            'total_products' => (clone $base)->count(),
            'live_products' => (clone $base)->where('is_active', true)->count(),
            'hidden_products' => (clone $base)->where('is_active', false)->count(),
            'low_stock_products' => (clone $base)->where('is_active', true)->whereHas('variants', $lowStock)->count(),
        ];
        $categories = Category::query()->whereIn('id', (clone $base)->select('category_id'))
            ->orderBy('name')->get(['id', 'name']);
        $query = (clone $base)->with(['brand', 'category', 'shop', 'images', 'variants.inventory']);

        if (filled($filters['q'] ?? null)) {
            $term = '%'.trim($filters['q']).'%';
            $query->where(fn (Builder $search) => $search->whereLike('name', $term)
                ->orWhereLike('sku', $term)->orWhereLike('barcode', $term)
                ->orWhereHas('variants', fn (Builder $variants) => $variants->whereLike('sku', $term)->orWhereLike('barcode', $term)));
        }
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (isset($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'live');
        }
        match ($filters['stock_status'] ?? '') {
            'in' => $query->whereHas('variants', $inStock),
            'out' => $query->whereDoesntHave('variants', $inStock),
            'low' => $query->whereHas('variants', $lowStock),
            default => null,
        };
        $sorts = [
            'newest' => ['id', 'desc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
            'price_asc' => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
        ];
        [$column, $direction] = $sorts[$filters['sort'] ?? 'newest'];
        $paginator = $query->orderBy($column, $direction)->orderByDesc('id')->paginate($filters['per_page'] ?? 15);

        return [
            'data' => SellerProductResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'summary' => $summary,
            'categories' => $categories,
        ];
    }
}
