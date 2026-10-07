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
use App\Models\Shop;
use App\Models\VariantAttributeValue;
use App\Services\InventoryService;
use App\Services\MediaUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        if (! $this->isManagedShopRequest($request)) {
            $this->authorize('viewAny', Product::class);
        }

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

        // A seller route has already authorized this exact shop in middleware.
        // Do not let a body field choose a different shop, even if the request
        // object was prepared before a form request was resolved.
        $managedShop = $request->attributes->get('managed_shop');
        if ($managedShop instanceof Shop) {
            $data['shop_id'] = $managedShop->id;
        }

        if (! $this->isManagedShopRequest($request)) {
            $this->authorize('create', [Product::class, $data]);
        }

        // products.slug has a unique index and the payload may repeat a slug that
        // is already taken, so it has to be de-duplicated before the insert.
        $data['slug'] = $this->uniqueSlug(Product::class, $this->slugForNewProduct($data));

        $variants = $request->input('variants') ?: null;

        // Seller quick-create intentionally captures a simple product rather
        // than the full variant matrix used by Super Admin. A sellable default
        // variant is still required for cart, checkout, and inventory flows.
        if ($variants === null && $request->is('api/seller/*')) {
            $variants = [[
                'name' => 'Default',
                'sku' => $data['sku'] ?? null,
                'price' => $data['price'] ?? 0,
                'compare_at_price' => $data['compare_at_price'] ?? null,
                'quantity' => max((int) $request->input('initial_stock', 0), 0),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]];
        }

        if (is_array($variants)) {
            // Variants inherit the product's branch, so that is the scope the
            // composite unique index will be checked against.
            $this->assertUniqueSkus($variants, $this->targetShopId($data));
            $this->assertUniqueCombinations($variants);
        }

        $images = $request->has('images') ? $request->input('images', []) : null;

        if (is_array($images)) {
            $this->assertManageableImagePaths(new Product, $images);
        }

        // Variants, stock rows and images must not survive a half-failed insert.
        $product = DB::transaction(function () use ($data, $variants, $images) {
            $product = Product::create($data);

            if (is_array($variants)) {
                $this->createVariants($product, $variants);
            }

            if (is_array($images)) {
                $this->syncImages($product, $images);
            }

            return $product;
        });

        return (new ProductDetailResource(
            $this->loadDetail($product)
        ))->response()->setStatusCode(201);
    }

    public function show(Request $request, Product $product)
    {
        if (! $this->isManagedShopRequest($request)) {
            $this->authorize('view', $product);
        }

        return new ProductDetailResource($this->loadDetail($product));
    }

    public function update(AdminProductRequest $request, Product $product)
    {
        $managedShop = $request->attributes->get('managed_shop');
        if (! $managedShop instanceof Shop) {
            $this->authorize('update', $product);
        }

        $data = $this->productData($request);

        if ($managedShop instanceof Shop) {
            // The seller middleware has already checked the product belongs to
            // this shop. Keep a seller update in that same shop even if an
            // older client submits a stale or manipulated body field.
            $data['shop_id'] = $managedShop->id;
        }

        if (! $managedShop instanceof Shop && array_key_exists('shop_id', $data)) {
            $this->authorize('changeShop', $product, $data['shop_id'] === null ? null : (int) $data['shop_id']);
        }

        $shopChanged = array_key_exists('shop_id', $data) && (int) ($data['shop_id'] ?? 0) !== (int) $product->shop_id;

        // Moving a product carries its variant SKUs into a branch that may already
        // stock them, which the (shop_id, sku) index would reject as a 500.
        if ($shopChanged) {
            $this->assertExistingSkusAvailableInShop(
                $product,
                $data['shop_id'] === null ? null : (int) $data['shop_id']
            );
        }

        if (isset($data['slug']) && $data['slug'] !== $product->slug) {
            $data['slug'] = $this->uniqueSlug(Product::class, $data['slug'], $product->id);
        }

        $variants = $request->has('variants') ? ($request->input('variants') ?? []) : null;
        $images = $request->has('images') ? ($request->input('images', []) ?? []) : null;

        if (is_array($variants)) {
            $this->assertUniqueSkus($variants, $this->targetShopId($data, $product));
            $this->assertUniqueCombinations($variants);
        }

        if (is_array($images)) {
            $this->assertManageableImagePaths($product, $images);
        }

        DB::transaction(function () use ($product, $data, $variants, $images, $shopChanged, $request) {
            $product->update($data);

            // A shop reassignment must cascade to the variants, their stock rows and
            // the ledger, otherwise branch-scoped inventory, SKU uniqueness and
            // reports all go stale.
            if ($shopChanged) {
                $product->variants()->update(['shop_id' => $product->shop_id]);

                Inventory::whereIn('product_variant_id', $product->variants()->pluck('id'))
                    ->update(['shop_id' => $product->shop_id]);
            }

            if (is_array($variants)) {
                $this->syncVariants($product, $variants, $request->user()?->id);
            }

            if (is_array($images)) {
                $this->syncImages($product, $images);
            }
        });

        return new ProductDetailResource($this->loadDetail($product));
    }

    public function destroy(Request $request, Product $product)
    {
        if (! $this->isManagedShopRequest($request)) {
            $this->authorize('delete', $product);
        }

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

        Product::whereIn('id', $products->pluck('id'))->update(['is_active' => $data['is_active']]);

        // Count what was actually written, not what was requested: ids that were
        // soft-deleted between validation and the write are silently skipped.
        return ['data' => ['updated' => $products->count()]];
    }

/**
 * SKU uniqueness is scoped per branch by `product_variants_shop_sku_unique`
 * (migration 2026_09_27_000002): two shops may stock the same manufacturer SKU,
 * but one shop may not list it twice.
 *
 * Rows referenced by the current payload are excluded from the clash check.
 * They are about to be rewritten, and excluding them is what lets an admin swap
 * two SKUs between two variants of the same product instead of tripping the
 * index on the first UPDATE.
 */
protected function assertUniqueSkus(array $variants, ?int $shopId = null): void
    {
        $incoming = [];
        $referencedIds = [];

        foreach ($variants as $variant) {
            $sku = isset($variant['sku']) ? trim((string) $variant['sku']) : '';

            if (! empty($variant['id'])) {
                $referencedIds[] = (int) $variant['id'];
            }

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
            ->whereNotIn('id', $referencedIds ?: [0])
            // A NULL shop_id is unconstrained in SQL, so mirror that here.
            ->where(fn ($q) => $shopId === null ? $q->whereNull('shop_id') : $q->where('shop_id', $shopId))
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
     * PVA-09: a product may not carry two variants with the same attribute
     * combination, otherwise the shopper has two indistinguishable choices.
     */
    protected function assertUniqueCombinations(array $variants): void
    {
        $seen = [];

        foreach ($variants as $index => $variant) {
            $attributes = $variant['attributes'] ?? null;

            if (! is_array($attributes) || $attributes === []) {
                continue;
            }

            $parts = [];

            foreach ($attributes as $entry) {
                $attribute = isset($entry['attribute']) ? Str::slug(trim((string) $entry['attribute'])) : '';
                $value = isset($entry['value']) ? mb_strtolower(trim((string) $entry['value'])) : '';

                if ($attribute === '' || $value === '') {
                    continue;
                }

                $parts[] = $attribute.'='.$value;
            }

            if ($parts === []) {
                continue;
            }

            sort($parts);
            $signature = implode('|', $parts);

            if (isset($seen[$signature])) {
                throw ValidationException::withMessages([
                    "variants.{$index}.attributes" => 'Two variants cannot share the same attribute combination.',
                ]);
            }

            $seen[$signature] = true;
        }
    }

    /**
     * `images` is a list of public URLs, and anything missing from that list gets
     * its file deleted. Accepting an arbitrary string therefore let a caller
     * delete another product's upload, or attach a remote URL to the gallery.
     *
     * A path is only manageable when it is already attached to this product or
     * when it points at a freshly uploaded file in the product uploads folder.
     */
    protected function assertManageableImagePaths(Product $product, array $paths): void
    {
        $paths = array_values(array_filter(array_map('trim', $paths), fn ($p) => $p !== ''));

        if ($paths === []) {
            return;
        }

        $owned = $product->exists
            ? $product->images()->pluck('image_path')->map(fn ($p) => (string) $p)->all()
            : [];

        foreach ($paths as $index => $path) {
            if (in_array($path, $owned, true)) {
                continue;
            }

            if ($this->isFreshProductUpload($path)) {
                continue;
            }

            throw ValidationException::withMessages([
                "images.{$index}" => 'Image must be uploaded through the media endpoint before it can be attached.',
            ]);
        }
    }

    protected function isFreshProductUpload(string $path): bool
    {
        $storageUrl = rtrim((string) config('filesystems.disks.public.url'), '/');
        $relative = null;

        if ($storageUrl !== '' && str_starts_with($path, $storageUrl)) {
            $relative = ltrim(substr($path, strlen($storageUrl)), '/');
        } elseif (str_starts_with($path, '/storage/')) {
            $relative = ltrim(substr($path, strlen('/storage/')), '/');
        }

        if ($relative === null || ! str_starts_with($relative, 'images/products/')) {
            return false;
        }

        if (str_contains($relative, '..')) {
            return false;
        }

        return Storage::disk('public')->exists($relative);
    }

/**
 * Fallback slug for a product whose name cannot produce one (for example a
 * name written entirely in a non-latin script).
 */
protected function slugForNewProduct(array $data): string
    {
        $slug = trim((string) ($data['slug'] ?? ''));

        if ($slug !== '') {
            return $slug;
        }

        return 'product-'.Str::lower(Str::random(10));
    }

    /**
     * The branch the product's variants will end up in, which is what the
     * per-branch SKU index applies to. A shop reassignment is part of the same
     * payload, so the incoming value wins over the stored one.
     */
protected function targetShopId(array $data, ?Product $product = null): ?int
    {
        if (array_key_exists('shop_id', $data)) {
            return $data['shop_id'] === null ? null : (int) $data['shop_id'];
        }

        return $product?->shop_id === null ? null : (int) $product->shop_id;
    }

    /**
 * Guard a branch reassignment: every SKU the product already uses must be free
 * in the destination branch.
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
            ->where(fn ($q) => $targetShopId === null ? $q->whereNull('shop_id') : $q->where('shop_id', $targetShopId))
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

    /**
     * Seller routes pass through EnsureManagedShop, which verifies both the
     * authenticated manager and the exact route resource before a controller
     * is invoked. Keep that checked context separate from platform-admin
     * policy checks; a request body must never be the source of seller scope.
     */
    protected function isManagedShopRequest(Request $request): bool
    {
        return $request->attributes->get('managed_shop') instanceof Shop;
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
            ]);

            $this->attachAttributes($variant, $variantData['attributes'] ?? []);
        }
    }

    protected function syncVariants(Product $product, array $variants, ?int $userId = null): void
    {
        $existing = $product->variants()->get();
        $referencedIds = [];

        // Snapshot the SKUs before anything is written: SKU-based row matching and
        // the "sku omitted" fallback both need the value as it arrived.
        $originalSku = $existing->mapWithKeys(fn ($variant) => [(int) $variant->id => $variant->sku])->all();

        // Phase 1: park the SKU of every variant this payload touches on a
        // temporary value. Without this, swapping two SKUs inside one product
        // trips the global unique index on the first UPDATE, because the other
        // variant still holds the value being written.
        foreach ($existing as $variant) {
            if ($variant->sku === null || ! $this->variantIsReferenced($variant, $variants, $originalSku)) {
                continue;
            }

            $variant->forceFill([
                'sku' => '__swap_'.$variant->id.'_'.Str::lower(Str::random(8)),
            ])->saveQuietly();
        }

        foreach ($variants as $variantData) {
            $variant = null;

            if (! empty($variantData['id'])) {
                $variant = $existing->firstWhere('id', (int) $variantData['id']);
            }

            if ($variant === null && ! empty($variantData['sku'])) {
                $wanted = $variantData['sku'];
                $variant = $existing->first(fn ($v) => ($originalSku[(int) $v->id] ?? $v->sku) === $wanted);
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
                    ]
                );
            } else {
                $variant->update([
                    'shop_id' => $product->shop_id,
                    'name' => $variantData['name'] ?? $variant->name,
                    // Never let the temporary SKU from phase 1 become the real one.
                    'sku' => $variantData['sku'] ?? ($originalSku[(int) $variant->id] ?? null),
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

    /**
     * Whether the incoming payload targets this variant, by id or by SKU.
     */
    protected function variantIsReferenced(ProductVariant $variant, array $variants, array $originalSku): bool
    {
        foreach ($variants as $variantData) {
            if (! empty($variantData['id']) && (int) $variantData['id'] === (int) $variant->id) {
                return true;
            }

            if (! empty($variantData['sku']) && ($originalSku[(int) $variant->id] ?? $variant->sku) === $variantData['sku']) {
                return true;
            }
        }

        return false;
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
