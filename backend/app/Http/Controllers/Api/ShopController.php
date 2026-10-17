<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogFilterRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\PublicShopResource;
use App\Models\Review;
use App\Models\Shop;
use App\Services\CatalogService;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    private function publicQuery()
    {
        return Shop::query()->active()
            ->withCount(['products' => fn ($q) => $q->active(), 'reviews' => fn ($q) => $q->where('status', Review::STATUS_APPROVED)])
            ->withAvg(['reviews' => fn ($q) => $q->where('status', Review::STATUS_APPROVED)], 'rating');
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = $this->publicQuery();
        if ($term = trim($data['q'] ?? '')) {
            $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%');
        }

        return PublicShopResource::collection($query->orderByDesc('is_default')->orderBy('name')->paginate($data['per_page'] ?? 12));
    }

    public function show(Shop $shop): PublicShopResource
    {
        abort_unless($shop->isActive(), 404);

        return new PublicShopResource($this->publicQuery()->findOrFail($shop->id));
    }

    public function products(CatalogFilterRequest $request, Shop $shop, CatalogService $catalog)
    {
        abort_unless($shop->isActive(), 404);
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:48']]);
        $filters = array_replace($request->filters(), ['shop_id' => $shop->id, 'perPage' => $request->integer('per_page', 12)]);

        return ProductResource::collection($catalog->filtered($filters));
    }
}
