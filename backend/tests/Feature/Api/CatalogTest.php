<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\VariantAttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function makeVariant(Product $product, string $sku, array $attributeValueIds, int $stock = 5, ?float $price = null): ProductVariant
    {
        // No price override by default: that is the common real-world case and it
        // is what the product-price fallback has to cover.
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'shop_id' => $product->shop_id,
            'name' => 'Variant',
            'sku' => $sku,
            'price' => $price,
            'is_default' => true,
            'is_active' => true,
        ]);

        $variant->inventory()->create([
            'shop_id' => $product->shop_id,
            'quantity' => $stock,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 5,
            'sold_count' => 0,
        ]);

        foreach ($attributeValueIds as $valueId) {
            VariantAttributeValue::create([
                'product_variant_id' => $variant->id,
                'attribute_value_id' => $valueId,
            ]);
        }

        return $variant;
    }

    public function test_lists_only_active_products_with_meta(): void
    {
        Product::factory()->create(['is_active' => true, 'price' => 10]);
        Product::factory()->create(['is_active' => false, 'price' => 20]);

        $response = $this->getJson('/api/catalog/products?perPage=10')
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame(10.0, (float) $response->json('data.0.price'));
        $this->assertArrayHasKey('slug', $response->json('data.0'));
    }

    public function test_filters_by_category_and_brand(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        Product::factory()->withVariant()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'price' => 10]);
        Product::factory()->withVariant()->create(['price' => 99]);

        $this->getJson("/api/catalog/products?category={$category->slug}&perPage=10")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson("/api/catalog/products?brand={$brand->slug}&perPage=10")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_price_range_and_sort(): void
    {
        Product::factory()->create(['price' => 10]);
        Product::factory()->create(['price' => 50]);
        Product::factory()->create(['price' => 100]);

        $asc = $this->getJson('/api/catalog/products?min=20&max=90&sort=price-asc&perPage=10')
            ->assertOk();

        $this->assertSame(1, $asc->json('meta.total'));
        $this->assertSame(50.0, (float) $asc->json('data.0.price'));

        $sorted = $this->getJson('/api/catalog/products?sort=price-desc&perPage=10')->assertOk();
        $prices = collect($sorted->json('data'))->pluck('price')->map(fn ($p) => (float) $p)->values();
        $this->assertSame($prices->sortDesc()->values()->all(), $prices->all());
    }

    public function test_search_matches_name(): void
    {
        Product::factory()->create(['name' => 'Ruby Wireless Mouse', 'price' => 10]);
        Product::factory()->create(['name' => 'Sapphire Keyboard', 'price' => 20]);

        $this->getJson('/api/catalog/products?q=wireless&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_featured_only_returns_featured(): void
    {
        Product::factory()->featured()->create(['price' => 10]);
        Product::factory()->create(['price' => 20]);

        $this->getJson('/api/catalog/featured')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_facets_return_tracked_groupings(): void
    {
        $a = Brand::factory()->create();
        $b = Brand::factory()->create();
        Product::factory()->create(['brand_id' => $a->id]);
        Product::factory()->create(['brand_id' => $a->id]);
        Product::factory()->create(['brand_id' => $b->id]);

        $response = $this->getJson('/api/catalog/facets')->assertOk();

        $this->assertArrayHasKey('brands', $response->json('data'));
        $this->assertArrayHasKey('categories', $response->json('data'));
        $this->assertArrayHasKey('colors', $response->json('data'));
        $this->assertArrayHasKey('sizes', $response->json('data'));

        $brand = collect($response->json('data.brands'))->firstWhere('slug', $a->slug);
        $this->assertSame(2, $brand['count']);
    }

    public function test_product_detail_exposes_variants_and_gallery(): void
    {
        $product = Product::factory()->withVariant(price: 25.99, stock: 4)->create();

        $this->getJson("/api/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $product->slug)
            ->assertJsonCount(1, 'data.variants')
            ->assertJsonStructure(['data' => ['variants', 'gallery', 'attributes']]);
    }

    public function test_product_detail_404_for_unknown_slug(): void
    {
        $this->getJson('/api/catalog/products/does-not-exist')->assertStatus(404);
    }

    public function test_variant_price_falls_back_to_the_product_price_on_both_endpoints(): void
    {
        $color = Attribute::create(['name' => 'Color', 'slug' => 'color', 'type' => 'color', 'is_filterable' => true]);
        $red = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Red', 'swatch_color' => '#ff0000']);

        $product = Product::factory()->create(['price' => 40]);
        // No price override, so both endpoints have to report the product price.
        $this->makeVariant($product, 'SKU-NO-OVERRIDE', [$red->id]);

        $detail = $this->getJson("/api/catalog/products/{$product->slug}")->assertOk();
        $this->assertSame(40.0, (float) $detail->json('data.variants.0.price'));

        $list = $this->getJson('/api/catalog/products?perPage=10')->assertOk();
        $this->assertSame(40.0, (float) $list->json('data.0.variants.0.price'));
    }

    public function test_catalog_listing_exposes_variant_attributes_and_swatches(): void
    {
        $color = Attribute::create(['name' => 'Color', 'slug' => 'color', 'type' => 'color', 'is_filterable' => true]);
        $red = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Red', 'swatch_color' => '#ff0000']);
        $blue = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Blue', 'swatch_color' => '#0000ff']);

        $product = Product::factory()->create(['price' => 40]);
        $this->makeVariant($product, 'SKU-LIST-RED', [$red->id]);
        $this->makeVariant($product, 'SKU-LIST-BLUE', [$blue->id]);

        $data = $this->getJson('/api/catalog/products?perPage=10')
            ->assertOk()
            ->assertJsonPath('data.0.variants.0.attributes.0.value', 'Red')
            ->json('data.0');

        // The card renders swatches straight from these lists.
        $this->assertSame(['Red', 'Blue'], collect($data['colors'])->pluck('value')->all());
        $this->assertSame('#ff0000', $data['colors'][0]['swatch_color']);
        $this->assertSame('color', $data['variants'][0]['attributes'][0]['attribute_slug']);
        $this->assertSame([], $data['sizes']);
    }

    public function test_categories_and_brands_index(): void
    {
        Category::factory()->create();
        Brand::factory()->create();

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/brands')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_rejects_unbounded_or_invalid_pagination(): void
    {
        $this->getJson('/api/catalog/products?perPage=100000')->assertStatus(422)->assertJsonValidationErrors('perPage');
        $this->getJson('/api/catalog/products?perPage=0')->assertStatus(422)->assertJsonValidationErrors('perPage');
        $this->getJson('/api/catalog/products?page=-1')->assertStatus(422)->assertJsonValidationErrors('page');
        $this->getJson('/api/catalog/products?sort=drop%20table')->assertStatus(422)->assertJsonValidationErrors('sort');
        $this->getJson('/api/catalog/products?min=abc')->assertStatus(422)->assertJsonValidationErrors('min');
    }

    public function test_caps_page_size_instead_of_dumping_the_whole_table(): void
    {
        Product::factory()->count(6)->create(['price' => 10]);

        // Absurd page sizes are rejected outright rather than clamped silently.
        $this->getJson('/api/catalog/products?perPage=100000')
            ->assertStatus(422)
            ->assertJsonValidationErrors('perPage');

        $capped = $this->getJson('/api/catalog/products?perPage=48')->assertOk();
        $this->assertSame(48, $capped->json('meta.per_page'));
        $this->assertSame(6, $capped->json('meta.total'));
    }

    public function test_rejects_invalid_featured_limit(): void
    {
        $this->getJson('/api/catalog/featured?limit=-1')->assertStatus(422)->assertJsonValidationErrors('limit');
        $this->getJson('/api/catalog/featured?limit=500')->assertStatus(422)->assertJsonValidationErrors('limit');
        $this->getJson('/api/catalog/featured')->assertOk();
    }

    public function test_stock_filter_is_disabled_by_a_falsey_value(): void
    {
        $inStock = Product::factory()->create(['price' => 10]);
        $this->makeVariant($inStock, 'SKU-IN-STOCK', [], stock: 4);

        $outOfStock = Product::factory()->create(['price' => 20]);
        $this->makeVariant($outOfStock, 'SKU-OUT-OF-STOCK', [], stock: 0);

        $this->getJson('/api/catalog/products?stock=1&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        // `?stock=0` used to be treated as truthy and filtered instead of opting out.
        $this->getJson('/api/catalog/products?stock=0&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_color_and_size_filters_use_and_across_attributes(): void
    {
        $color = Attribute::create(['name' => 'Color', 'slug' => 'color', 'type' => 'color', 'is_filterable' => true]);
        $size = Attribute::create(['name' => 'Size', 'slug' => 'size', 'type' => 'text', 'is_filterable' => true]);

        $red = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Red']);
        $blue = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Blue']);
        $large = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'L']);

        $redLarge = Product::factory()->create(['name' => 'Red Large Hoodie', 'price' => 10]);
        $this->makeVariant($redLarge, 'SKU-RED-L', [$red->id, $large->id]);

        $blueLarge = Product::factory()->create(['name' => 'Blue Large Hoodie', 'price' => 10]);
        $this->makeVariant($blueLarge, 'SKU-BLUE-L', [$blue->id, $large->id]);

        $blueSmall = Product::factory()->create(['name' => 'Blue Small Hoodie', 'price' => 10]);
        $this->makeVariant($blueSmall, 'SKU-BLUE-S', [$blue->id]);

        // OR within a single attribute.
        $this->getJson('/api/catalog/products?colors=Red,Blue&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        // AND across attributes: Red or Blue, in size L.
        $this->getJson('/api/catalog/products?colors=Red,Blue&sizes=L&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['name' => 'Red Large Hoodie'])
            ->assertJsonFragment(['name' => 'Blue Large Hoodie'])
            ->assertJsonMissing(['name' => 'Blue Small Hoodie']);
    }

    public function test_attribute_filter_matches_exact_values_only(): void
    {
        $color = Attribute::create(['name' => 'Color', 'slug' => 'color', 'type' => 'color', 'is_filterable' => true]);
        $red = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Red']);
        $crimson = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Crimson Red']);

        $exact = Product::factory()->create(['name' => 'Red Jacket', 'price' => 10]);
        $this->makeVariant($exact, 'SKU-EXACT', [$red->id]);

        $partial = Product::factory()->create(['name' => 'Crimson Jacket', 'price' => 10]);
        $this->makeVariant($partial, 'SKU-PARTIAL', [$crimson->id]);

        // The old `LIKE %value%` match leaked "Crimson Red" into a "Red" filter.
        $this->getJson('/api/catalog/products?colors=Red&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['name' => 'Red Jacket'])
            ->assertJsonMissing(['name' => 'Crimson Jacket']);
    }

    public function test_resolves_variants_from_selected_attribute_values(): void
    {
        $color = Attribute::create(['name' => 'Color', 'slug' => 'color', 'type' => 'color', 'is_filterable' => true]);
        $size = Attribute::create(['name' => 'Size', 'slug' => 'size', 'type' => 'text', 'is_filterable' => true]);
        $red = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Red']);
        $blue = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Blue', 'swatch_color' => '#0000ff']);
        $large = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'L']);
        $small = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'S']);

        $product = Product::factory()->create(['name' => 'Hoodie', 'price' => 40]);

        $redLarge = $this->makeVariant($product, 'SKU-RED-L', [$red->id, $large->id], stock: 3);
        $redSmall = $this->makeVariant($product, 'SKU-RED-S', [$red->id, $small->id], stock: 0);
        $blueLarge = $this->makeVariant($product, 'SKU-BLUE-L', [$blue->id, $large->id], stock: 7);

        $response = $this->postJson('/api/catalog/variants/resolve', [
            'attribute_values' => [$red->id, $large->id],
            'product_id' => $product->id,
        ])->assertOk()->assertJsonCount(1, 'data');

        $variant = $response->json('data.0');
        $this->assertSame($redLarge->id, $variant['variant_id']);
        $this->assertSame('SKU-RED-L', $variant['sku']);
        $this->assertSame(3, $variant['available_quantity']);
        $this->assertTrue($variant['in_stock']);

        // Blue + L is a different variant, so it is excluded from the Red + L result.
        $this->assertNotSame($blueLarge->id, $variant['variant_id']);
        $this->assertNotSame($redSmall->id, $variant['variant_id']);

        // Selected values must all match, so a single value cannot widen the result.
        $this->postJson('/api/catalog/variants/resolve', [
            'attribute_values' => [$red->id, $blue->id],
            'product_id' => $product->id,
        ])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_variant_resolution_requires_valid_attribute_values(): void
    {
        $this->postJson('/api/catalog/variants/resolve', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('attribute_values');

        $this->postJson('/api/catalog/variants/resolve', ['attribute_values' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('attribute_values');
    }

    public function test_facets_expose_price_range_and_filterable_attributes(): void
    {
        $color = Attribute::create(['name' => 'Color', 'slug' => 'color', 'type' => 'color', 'is_filterable' => true]);
        Attribute::create(['name' => 'Material', 'slug' => 'material', 'type' => 'text', 'is_filterable' => false]);

        $red = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Red']);

        $product = Product::factory()->create(['price' => 15]);
        $this->makeVariant($product, 'SKU-FACET', [$red->id]);

        $data = $this->getJson('/api/catalog/facets')->assertOk()->json('data');

        $this->assertSame(15.0, (float) $data['price_range']['min']);
        $this->assertSame(15.0, (float) $data['price_range']['max']);
        $this->assertCount(1, $data['attributes']);
        $this->assertSame('color', $data['attributes'][0]['slug']);
        $this->assertSame(1, $data['attributes'][0]['values'][0]['count']);
    }

    public function test_facet_counts_ignore_inactive_products(): void
    {
        $brand = Brand::factory()->create();
        Product::factory()->create(['brand_id' => $brand->id, 'price' => 10]);
        Product::factory()->inactive()->create(['brand_id' => $brand->id, 'price' => 20]);

        $count = collect($this->getJson('/api/catalog/facets')->assertOk()->json('data.brands'))
            ->firstWhere('slug', $brand->slug)['count'];

        $this->assertSame(1, $count);
    }

    public function test_hides_products_from_non_active_shops(): void
    {
        $activeShop = Shop::factory()->create(['status' => Shop::STATUS_ACTIVE]);
        $pendingShop = Shop::factory()->create(['status' => Shop::STATUS_PENDING]);

        Product::factory()->create(['shop_id' => $activeShop->id, 'price' => 10]);
        $hidden = Product::factory()->create(['shop_id' => $pendingShop->id, 'price' => 20]);

        $this->getJson('/api/catalog/products?perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson("/api/catalog/products/{$hidden->slug}")->assertStatus(404);
    }

    public function test_search_matches_long_description_and_treats_wildcards_literally(): void
    {
        Product::factory()->create(['name' => 'Alpha', 'description' => 'Contains a hidden keyword', 'price' => 10]);
        $other = Product::factory()->create(['name' => 'Beta', 'description' => 'Nothing here', 'price' => 20]);

        $this->getJson('/api/catalog/products?q=hidden&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        // `%` is escaped, so it cannot act as a match-all wildcard.
        $this->getJson('/api/catalog/products?q=%25&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson('/api/catalog/products?q=Alpha&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->assertSame('Beta', $other->name);
    }

    public function test_supports_name_and_featured_sorting(): void
    {
        Product::factory()->create(['name' => 'Charlie', 'price' => 10]);
        Product::factory()->featured()->create(['name' => 'Alpha', 'price' => 20]);

        $names = collect($this->getJson('/api/catalog/products?sort=name-asc&perPage=10')->assertOk()->json('data'))
            ->pluck('name')->values()->all();
        $this->assertSame(['Alpha', 'Charlie'], $names);

        $featuredFirst = $this->getJson('/api/catalog/products?sort=featured&perPage=10')->assertOk()->json('data.0.name');
        $this->assertSame('Alpha', $featuredFirst);
    }
}
