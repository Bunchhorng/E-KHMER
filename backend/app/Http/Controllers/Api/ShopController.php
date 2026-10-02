<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ShopResource;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $shops = Shop::query()
            ->active()
            ->withCount('products')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return ShopResource::collection($shops);
    }

    public function show(Shop $shop)
    {
        abort_unless($shop->isActive(), 404);

        $shop->loadCount('products');

        return new ShopResource($shop);
    }

    public function products(Request $request, Shop $shop)
    {
        abort_unless($shop->isActive(), 404);

        // Same bound as the catalog listing: an uncapped per_page turned this
        // public endpoint into a whole-shop dump.
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $products = Product::query()
            ->active()
            ->where('shop_id', $shop->id)
            ->with(['brand', 'category', 'images', 'variants'])
            ->orderBy('name')
            ->paginate(
                (int) ($validated['per_page'] ?? 20),
                ['*'],
                'page',
                (int) ($validated['page'] ?? 1),
            );

        return ProductResource::collection($products);
    }
}
