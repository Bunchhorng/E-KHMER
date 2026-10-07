<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Api\Admin\AdminProductController;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminProductRequest;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\Request;

class SellerProductController extends Controller
{
    public function __construct(private AdminProductController $products) {}

    public function index(Request $request, Shop $shop): mixed
    {
        return $this->products->index($request);
    }

    public function store(AdminProductRequest $request, Shop $shop): mixed
    {
        return $this->products->store($request);
    }

    public function show(Request $request, Shop $shop, Product $product): mixed
    {
        return $this->products->show($request, $product);
    }

    public function update(AdminProductRequest $request, Shop $shop, Product $product): mixed
    {
        // A simple seller product has one default variant. Keep its actual
        // selling price and visibility aligned with the owner's basic form.
        if (! $request->has('variants')) {
            $variants = $product->variants()->get();
            $variant = $variants->count() === 1 && $variants->first()->is_default ? $variants->first() : null;
            if ($variant !== null) {
                $data = $variant->only(['id', 'name', 'sku', 'price', 'compare_at_price', 'is_active']);
                foreach (['price', 'compare_at_price', 'is_active'] as $key) {
                    if ($request->has($key)) {
                        $data[$key] = $request->input($key);
                    }
                }
                if ($request->has('sku') && $request->input('sku') !== $product->sku) {
                    $data['sku'] = $request->input('sku');
                }
                $request->merge(['variants' => [$data]]);
            }
        }

        return $this->products->update($request, $product);
    }

    public function destroy(Request $request, Shop $shop, Product $product): mixed
    {
        return $this->products->destroy($request, $product);
    }
}
