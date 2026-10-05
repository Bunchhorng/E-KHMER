<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminBrandController extends Controller
{
    public function index(Request $request)
    {
        return BrandResource::collection(Brand::withCount('products')->orderBy('name')->get());
    }

    public function store(AdminBrandRequest $request)
    {
        $brand = Brand::create($request->validated());
        Cache::forget('brands:active');

        return (new BrandResource($brand))->response()->setStatusCode(201);
    }

    public function update(AdminBrandRequest $request, Brand $brand)
    {
        $brand->update($request->validated());
        Cache::forget('brands:active');

        return new BrandResource($brand);
    }

    public function destroy(Brand $brand)
    {
        // products.brand_id is nullOnDelete. Deleting a brand that is still in
        // use would therefore silently remove meaningful catalog metadata from
        // its products. Make reassignment an explicit admin action instead.
        if ($productCount = $brand->products()->count()) {
            $noun = $productCount === 1 ? 'product' : 'products';

            return response()->json([
                'data' => ['message' => "Move its {$productCount} {$noun} to another brand first."],
            ], 422);
        }

        $brand->delete();
        Cache::forget('brands:active');

        return response()->json(['data' => ['message' => 'Brand deleted.']]);
    }
}
