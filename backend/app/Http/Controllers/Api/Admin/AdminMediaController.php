<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminImageUploadRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\MediaUploadService;
use Illuminate\Http\Request;

class AdminMediaController extends Controller
{
    public function __construct(protected MediaUploadService $media)
    {
    }

    /**
     * Upload a single image and return its public URL.
     */
    public function uploadImage(AdminImageUploadRequest $request)
    {
        $context = (string) ($request->input('context') ?? 'products');

        $path = $this->media->storeImage($request->file('image'), $context);

        return response()->json(['data' => ['path' => $path]]);
    }

    /**
     * Upload a new image and attach it to the product gallery.
     */
    public function storeProductImage(AdminImageUploadRequest $request, Product $product)
    {
        $path = $this->media->storeImage($request->file('image'), 'products');

        $isCover = $request->boolean('is_cover') || ! $product->images()->where('is_cover', true)->exists();

        // A gallery has exactly one cover, so promoting the new image has to clear
        // the previous flag. Without this the product rendered two cover images.
        if ($isCover) {
            $product->images()->where('is_cover', true)->update(['is_cover' => false]);
        }

        $image = $product->images()->create([
            'image_path' => $path,
            'alt_text' => $request->input('alt_text'),
            'sort_order' => (int) $product->images()->max('sort_order') + 1,
            'is_cover' => $isCover,
        ]);

        return (new ProductImageResource($image))->response()->setStatusCode(201);
    }

    /**
     * Remove an image from the product gallery.
     */
    public function destroyProductImage(Request $request, Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404, 'Image not found for this product.');

        $this->media->deleteImage($image->image_path);

        $image->delete();

        if ($product->images()->where('is_cover', true)->doesntExist()) {
            $product->images()->orderBy('sort_order')->first()?->update(['is_cover' => true]);
        }

        return response()->json(['data' => ['message' => 'Image deleted.']]);
    }

    /**
     * Upload and assign a logo to a brand, replacing any existing logo.
     */
    public function uploadBrandLogo(AdminImageUploadRequest $request, Brand $brand)
    {
        $logo = $this->media->storeImage($request->file('image'), 'brands');

        $this->media->deleteImage($brand->logo);

        $brand->update(['logo' => $logo]);

        return response()->json(['data' => ['logo' => $logo]]);
    }

    /**
     * Upload and assign an image to a category, replacing any existing image.
     */
    public function uploadCategoryImage(AdminImageUploadRequest $request, Category $category)
    {
        $image = $this->media->storeImage($request->file('image'), 'categories');

        $this->media->deleteImage($category->image);

        $category->update(['image' => $image]);

        return response()->json(['data' => ['image' => $image]]);
    }
}