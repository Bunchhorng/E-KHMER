<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminProductRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductResource;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantAttributeValue;
use App\Services\InventoryService;
use App\Services\MediaUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminProductController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
        private MediaUploadService $mediaService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::with(['brand', 'category', 'shop', 'images', 'variants.inventory']);

        if ($request->boolean('deleted')) {
            $query->onlyTrashed();
        }

        if ($request->filled('q')) {
            $term = mb_strtolower(trim((string) $request->q));
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$term}%"]);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->category_id);
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', (int) $request->brand_id);
        }

        if ($request->filled('shop_id')) {
            $request->shop_id === 'none'
                ? $query->whereNull('shop_id')
                : $query->where('shop_id', (int) $request->shop_id);
        }

        if ($request->filled('stock_status') && $request->stock_status !== 'all') {
            if ($request->stock_status === 'in') {
                $query->whereHas('variants', fn ($q) => $q->where('is_active', true)
                    ->whereHas('inventory', fn ($i) => $i->whereRaw('quantity - reserved_quantity > 0')));
            } elseif ($request->stock_status === 'out') {
                $query->whereDoesntHave('variants', fn ($q) => $q->where('is_active', true)
                    ->whereHas('inventory', fn ($i) => $i->whereRaw('quantity - reserved_quantity > 0')));
            }
        }

        $paginator = $query->orderByDesc('id')->paginate(15);

        return [
            'data' => ProductResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function store(AdminProductRequest $request)
    {
        $data = $this->productData($request);

        $this->authorize('create', Product::class, $data);

        $product = Product::create($data);

        if ($request->filled('variants')) {
            $this->assertUniqueSkus($request->input('variants'), null, $product->shop_id);
            $this->createVariants($product, $request->input('variants'));
        }

        if ($request->has('images')) {
            $this->syncImages($product, $request->input('images', []));
        }

        return (new ProductDetailResource(
            $this->loadDetail($product)
        ))->response()->setStatusCode(201);
    }

    public function show(Product $product)
    {
        $this->authorize('view', $product);

        return new ProductDetailResource($this->loadDetail($product));
    }

    public function update(AdminProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        $data = $this->productData($request);

        if (array_key_exists('shop_id', $data)) {
            $this->authorize('changeShop', $product, $data['shop_id'] === null ? null : (int) $data['shop_id']);
        }

        $shopChanged = array_key_exists('shop_id', $data) && (int) ($data['shop_id'] ?? 0) !== (int) $product->shop_id;

        // Reassigning a product carries its existing variant SKUs into the new
        // branch, so they have to be validated there before anything is written.
        if ($shopChanged) {
            $this->assertExistingSkusAvailableInShop(
                $product,
                $data['shop_id'] === null ? null : (int) $data['shop_id']
            );
        }

        if (isset($data['slug']) && $data['slug'] !== $product->slug) {
            $data['slug'] = $this->uniqueSlug(Product::class, $data['slug'], $product->id);
        }

        $product->update($data);

        // A shop reassignment must cascade to the variants, their stock rows and
        // the ledger, otherwise branch-scoped inventory, SKU uniqueness and
        // reports all go stale.
        if ($shopChanged) {
            $product->variants()->update(['shop_id' => $product->shop_id]);

            Inventory::whereIn('product_variant_id', $product->variants()->pluck('id'))
                ->update(['shop_id' => $product->shop_id]);
        }

        if ($request->has('variants')) {
            $this->assertUniqueSkus($request->input('variants') ?? [], $product->id, $product->shop_id);
            $this->syncVariants($product, $request->input('variants') ?? [], $request->user()?->id);
        }

        if ($request->has('images')) {
            $this->syncImages($product, $request->input('images', []));
        }

        return new ProductDetailResource($this->loadDetail($product));
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $product->delete();

        return response()->json(['data' => ['message' => 'Product deleted.']]);
    }

    /**
     * Restore a soft-deleted product.
     *
     * Implicit route-model binding cannot resolve soft-deleted rows, so the
     * record is fetched with trashed scope explicitly. Authorization still runs
     * against the resolved model so ownership is enforced on restore too.
     */
    public function restore(int $product)
    {
        $trashed = Product::withTrashed()->findOrFail($product);

        $this->authorize('restore', $trashed);

        $trashed->restore();

        return new ProductDetailResource($this->loadDetail($trashed));
    }

    public function updateStatus(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:products,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        // Authorize per product so a branch manager can only toggle visibility
        // inside their own branch, even though the route is admin-gated today.
        $products = Product::whereIn('id', $data['ids'])->get();

        foreach ($products as $product) {
            $this->authorize('update', $product);
        }

        Product::whereIn('id', $data['ids'])->update(['is_active' => $data['is_active']]);

        return ['data' => ['updated' => count($data['ids'])]];
    }

    /**
     * SKU uniqueness is scoped per branch: two different shops may legitimately
     * stock the same manufacturer SKU, but one shop may not list it twice.
     */
    protected function assertUniqueSkus(array $variants, ?int $exceptProductId = null, ?int $shopId = null): void
    {
        $incoming = [];

        foreach ($variants as $variant) {
            $sku = isset($variant['sku']) ? trim((string) $variant['sku']) : '';

            if ($sku === '') {
                continue;
            }

            $key = mb_strtoupper($sku);

            if (isset($incoming[$key])) {
                throw ValidationException::withMessages([
                    'variants' => "Duplicate SKU \"{$sku}\" in the variant list.",
                ]);
            }

            $incoming[$key] = $sku;
        }

        if ($incoming === []) {
            return;
        }

        $existing = ProductVariant::withTrashed()
            ->whereNotNull('sku')
            ->when($exceptProductId !== null, fn ($q) => $q->where('product_id', '!=', $exceptProductId))
            ->when($shopId !== null, fn ($q) => $q->where('shop_id', $shopId))
            ->whereIn('sku', array_values($incoming))
            ->pluck('sku');

        foreach ($existing as $existingSku) {
            if (isset($incoming[mb_strtoupper($existingSku)])) {
                throw ValidationException::withMessages([
                    'variants' => "SKU \"{$existingSku}\" is already used by another product in this branch.",
                ]);
            }
        }
    }

    /**
     * Guard a branch reassignment: every SKU the product already uses must be
     * free in the destination branch, otherwise the composite unique index
     * would reject the write as an unhandled 500.
     */
    protected function assertExistingSkusAvailableInShop(Product $product, ?int $targetShopId): void
    {
        $ownSkus = ProductVariant::withTrashed()
            ->where('product_id', $product->id)
            ->whereNotNull('sku')
            ->pluck('sku');

        if ($ownSkus->isEmpty()) {
            return;
        }

        $clashes = ProductVariant::withTrashed()
            ->where('product_id', '!=', $product->id)
            ->whereNotNull('sku')
            ->when($targetShopId !== null, fn ($q) => $q->where('shop_id', $targetShopId))
            ->whereIn('sku', $ownSkus)
            ->pluck('sku');

        if ($clashes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'shop_id' => "Cannot move this product: SKU \"{$clashes->first()}\" is already used by another product in the target branch.",
            ]);
        }
    }

    protected function productData(AdminProductRequest $request): array
    {
        $data = $request->only([
            'name', 'slug', 'category_id', 'brand_id', 'shop_id', 'short_description',
            'description', 'price', 'compare_at_price', 'sku', 'weight',
            'is_featured', 'is_active',
        ]);

        foreach ([
            'is_featured' => false,
            'is_active' => true,
            'price' => 0,
        ] as $key => $default) {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                $data[$key] = $default;
            }
        }

        if (! empty($data['is_featured'])) {
            $data['is_featured'] = true;
        }
        if (! empty($data['is_active'])) {
            $data['is_active'] = true;
        }

        return $data;
    }

    protected function createVariants(Product $product, array $variants): void
    {
        foreach ($variants as $variantData) {
            $variant = $product->variants()->create([
                'shop_id' => $product->shop_id,
                'name' => $variantData['name'] ?? null,
                'sku' => $variantData['sku'] ?? null,
                'price' => ($variantData['price'] ?? null) !== null ? max((float) $variantData['price'], 0) : null,
                'compare_at_price' => ($variantData['compare_at_price'] ?? null) !== null ? max((float) $variantData['compare_at_price'], 0) : null,
                'is_active' => $variantData['is_active'] ?? true,
            ]);

            Inventory::create([
                'product_variant_id' => $variant->id,
                'shop_id' => $product->shop_id,
                'quantity' => max((int) ($variantData['quantity'] ?? 0), 0),
                'reserved_quantity' => 0,
                'low_stock_threshold' => 5,
            ]);

            $this->attachAttributes($variant, $variantData['attributes'] ?? []);
        }
    }

    protected function syncVariants(Product $product, array $variants, ?int $userId = null): void
    {
        $existing = $product->variants()->get();
        $referencedIds = [];

        foreach ($variants as $variantData) {
            $variant = null;

            if (! empty($variantData['id'])) {
                $variant = $existing->firstWhere('id', (int) $variantData['id']);
            }

            if ($variant === null && ! empty($variantData['sku'])) {
                $variant = $existing->first(fn ($v) => $v->sku === $variantData['sku']);
            }

            if ($variant === null) {
                $variant = $product->variants()->create([
                    'shop_id' => $product->shop_id,
                    'name' => $variantData['name'] ?? null,
                    'sku' => $variantData['sku'] ?? null,
                    'price' => $variantData['price'] ?? null,
                    'compare_at_price' => $variantData['compare_at_price'] ?? null,
                    'is_active' => true,
                ]);

                Inventory::firstOrCreate(
                    ['product_variant_id' => $variant->id],
                    [
                        'shop_id' => $product->shop_id,
                        'quantity' => 0,
                        'reserved_quantity' => 0,
                        'low_stock_threshold' => 5,
                    ]
                );
            } else {
                $variant->update([
                    'shop_id' => $product->shop_id,
                    'name' => $variantData['name'] ?? $variant->name,
                    'sku' => $variantData['sku'] ?? $variant->sku,
                    'price' => array_key_exists('price', $variantData) ? $variantData['price'] : $variant->price,
                    'compare_at_price' => array_key_exists('compare_at_price', $variantData) ? $variantData['compare_at_price'] : $variant->compare_at_price,
                    'is_active' => $variantData['is_active'] ?? $variant->is_active,
                ]);

                if (array_key_exists('quantity', $variantData)) {
                    $newQuantity = max((int) $variantData['quantity'], 0);
                    $currentQuantity = (int) Inventory::where('product_variant_id', $variant->id)->value('quantity');

                    if ($newQuantity !== $currentQuantity) {
                        $this->inventoryService->adjust($variant->id, $newQuantity, $userId);
                    } else {
                        Inventory::updateOrCreate(
                            ['product_variant_id' => $variant->id],
                            ['quantity' => $newQuantity, 'shop_id' => $product->shop_id],
                        );
                    }
                }
            }

            $referencedIds[] = $variant->id;

            if (array_key_exists('attributes', $variantData)) {
                VariantAttributeValue::where('product_variant_id', $variant->id)->delete();
                $this->attachAttributes($variant, $variantData['attributes'] ?? []);
            }
        }

        foreach ($existing as $variant) {
            if (! in_array($variant->id, $referencedIds, true)) {
                $variant->delete();
            }
        }
    }

    protected function syncImages(Product $product, array $paths): void
    {
        $paths = array_values(array_filter(array_map('trim', $paths), fn ($p) => $p !== ''));

        $existing = $product->images()->orderBy('sort_order')->get();

        if (count($paths) === 0) {
            foreach ($existing as $image) {
                $this->mediaService->deleteImage($image->image_path);
                $image->delete();
            }

            return;
        }

        $keep = array_flip($paths);

        foreach ($existing as $image) {
            if (! isset($keep[$image->image_path])) {
                $this->mediaService->deleteImage($image->image_path);
                $image->delete();
            }
        }

        $sortOrder = 0;

        foreach ($paths as $path) {
            $row = $existing->firstWhere('image_path', $path);
            $isCover = $sortOrder === 0;

            if ($row !== null) {
                if ((int) $row->sort_order !== $sortOrder || (bool) $row->is_cover !== $isCover) {
                    $row->update(['sort_order' => $sortOrder, 'is_cover' => $isCover]);
                }
            } else {
                $product->images()->create([
                    'image_path' => $path,
                    'sort_order' => $sortOrder,
                    'is_cover' => $isCover,
                ]);
            }

            $sortOrder++;
        }
    }

    protected function attachAttributes(ProductVariant $variant, array $attributes): void
    {
        foreach ($attributes as $entry) {
            $attributeName = $entry['attribute'] ?? null;
            $attributeValue = $entry['value'] ?? null;

            if ($attributeName === null || $attributeValue === null) {
                continue;
            }

            $attribute = Attribute::where('slug', Str::slug($attributeName))
                ->orWhere('name', $attributeName)
                ->first();

            if ($attribute === null) {
                $attribute = Attribute::create([
                    'name' => (string) $attributeName,
                    'slug' => Str::slug((string) $attributeName),
                    'type' => 'text',
                    'is_filterable' => false,
                ]);
            }

            $value = AttributeValue::firstOrCreate(
                ['attribute_id' => $attribute->id, 'value' => (string) $attributeValue],
                ['swatch_color' => null]
            );

            VariantAttributeValue::firstOrCreate([
                'product_variant_id' => $variant->id,
                'attribute_value_id' => $value->id,
            ]);
        }
    }

    protected function loadDetail(Product $product): Product
    {
        return $product->load([
            'brand',
            'category',
            'shop',
            'images',
            'variants' => fn ($q) => $q->with(['inventory', 'attributeValues.value.attribute']),
        ]);
    }

    protected function uniqueSlug(string $model, string $slug, ?int $ignoreId = null): string
    {
        $base = $slug;
        $suffix = 1;

        while ($model::where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
