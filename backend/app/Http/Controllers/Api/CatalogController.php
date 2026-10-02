<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogFilterRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductResource;
use App\Services\CatalogService;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(protected CatalogService $catalog)
    {
    }

    public function index(CatalogFilterRequest $request)
    {
        $products = $this->catalog->filtered($request->filters());

        return [
            'data' => ProductResource::collection($products->items()),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ];
    }

    public function show(string $slug)
    {
        $product = $this->catalog->findBySlug($slug);

        if ($product === null) {
            abort(404, 'Product not found.');
        }

        return new ProductDetailResource($product);
    }

    public function featured(Request $request)
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $limit = (int) ($validated['limit'] ?? 8);

        return ProductResource::collection($this->catalog->featured($limit));
    }

    public function facets()
    {
        return ['data' => $this->catalog->facets()];
    }

    /**
     * Dynamic variant filtering: map selected attribute value ids to the concrete
     * purchasable variants (with live stock) that satisfy all of them.
     */
    public function resolveVariants(Request $request)
    {
        $validated = $request->validate([
            'attribute_values' => ['required', 'array', 'min:1', 'max:10'],
            'attribute_values.*' => ['integer', 'min:1'],
            'product_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $variants = $this->catalog->resolveVariants(
            $validated['attribute_values'],
            isset($validated['product_id']) ? (int) $validated['product_id'] : null,
        );

        return ['data' => $variants];
    }
}