<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Api\Admin\AdminProductController;
use App\Http\Controllers\Controller;
use App\Http\Requests\SellerProductRequest;
use App\Models\Product;
use App\Models\Shop;
use App\Services\MediaUploadService;
use Illuminate\Http\Request;
use Throwable;

class SellerProductController extends Controller
{
    public function __construct(private AdminProductController $products, private MediaUploadService $media) {}

    public function index(Request $request, Shop $shop): mixed
    {
        return $this->products->index($request);
    }

    public function store(SellerProductRequest $request, Shop $shop): mixed
    {
        return $this->withUploadedImages($request, fn () => $this->products->store($request));
    }

    public function show(Request $request, Shop $shop, Product $product): mixed
    {
        return $this->products->show($request, $product);
    }

    public function update(SellerProductRequest $request, Shop $shop, Product $product): mixed
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

        return $this->withUploadedImages($request, fn () => $this->products->update($request, $product));
    }

    private function withUploadedImages(SellerProductRequest $request, callable $save): mixed
    {
        $uploaded = [];

        try {
            foreach ($request->file('image_files', []) as $index => $file) {
                $uploaded[$index] = $this->media->storeImage($file, 'products');
            }

            if ($request->has('images')) {
                $request->merge(['images' => array_map(
                    fn ($path) => str_starts_with($path, 'upload:') ? $uploaded[(int) substr($path, 7)] : $path,
                    $request->input('images') ?? [],
                )]);
            }

            return $save();
        } catch (Throwable $exception) {
            foreach ($uploaded as $path) {
                $this->media->deleteImage($path);
            }

            throw $exception;
        }
    }

    public function destroy(Request $request, Shop $shop, Product $product): mixed
    {
        return $this->products->destroy($request, $product);
    }
}
