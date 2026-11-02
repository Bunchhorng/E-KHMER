<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogColorsTest extends TestCase
{
    use RefreshDatabase;

    private Attribute $color;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->color = Attribute::create(['name' => 'Color', 'slug' => 'color', 'type' => 'color', 'is_filterable' => true]);
        $this->shop = Shop::factory()->create();
    }

    private function product(string $color, array $overrides = []): Product
    {
        $value = AttributeValue::firstOrCreate(['attribute_id' => $this->color->id, 'value' => $color], ['swatch_color' => '#ADD8E6']);
        $product = Product::factory()->withVariant(200, 10)->create([
            'shop_id' => $this->shop->id, 'name' => $color.' Product', 'price' => 200,
            'rating_avg' => 4.8, 'rating_count' => 2, ...$overrides,
        ]);
        $product->variants()->sole()->attributeValues()->create(['attribute_value_id' => $value->id]);

        return $product;
    }

    private function paths(array $filters): array
    {
        $query = http_build_query($filters);

        return ['/api/catalog/products?'.$query, "/api/shops/{$this->shop->slug}/products?{$query}"];
    }

    public static function canonicalColors(): array
    {
        return array_map(fn ($value) => [$value], [
            'Light Blue', 'Dark Green', 'Rose Gold', 'Midnight Blue', 'Space Gray',
            'Black', 'White', 'Red', 'Blue', 'Black/White', 'Blue & Gray', 'Off-White',
        ]);
    }

    #[DataProvider('canonicalColors')]
    public function test_actual_color_values_match_on_marketplace_and_shop(string $value): void
    {
        $product = $this->product($value);
        foreach ($this->paths(['colors' => [$value]]) as $path) {
            $this->getJson($path)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id);
        }
    }

    public function test_slugs_unknown_values_and_query_fragments_are_not_reinterpreted_as_real_colors(): void
    {
        $this->product('Light Blue');
        foreach (['light-blue', 'NotARealColor', "Light Blue' OR 1=1 --"] as $value) {
            foreach ($this->paths(['colors' => [$value]]) as $path) {
                $this->getJson($path)->assertOk()->assertJsonCount(0, 'data');
            }
        }
    }

    public function test_matching_is_explicitly_case_insensitive_but_preserves_spaces_and_punctuation(): void
    {
        $product = $this->product('Light Blue');
        foreach ($this->paths(['colors' => ['lIgHt bLuE']]) as $path) {
            $this->getJson($path)->assertOk()->assertJsonPath('data.0.id', $product->id);
        }
        $this->getJson('/api/catalog/products?colors[]=Light')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_multiple_colors_keep_or_semantics_and_legacy_string_and_csv_queries_work(): void
    {
        $blue = $this->product('Light Blue');
        $black = $this->product('Black');
        $this->product('Red');
        foreach ([['Light Blue', 'Black'], 'Light Blue,Black'] as $selection) {
            foreach ($this->paths(['colors' => $selection]) as $path) {
                $ids = $this->getJson($path)->assertOk()->assertJsonCount(2, 'data')->json('data.*.id');
                $this->assertEqualsCanonicalizing([$blue->id, $black->id], $ids);
            }
        }
        $this->getJson('/api/catalog/products?colors=Light%20Blue')->assertOk()->assertJsonPath('data.0.id', $blue->id);
    }

    public function test_color_composes_with_category_brand_rating_price_and_shop_scope(): void
    {
        $category = Category::factory()->create(['slug' => 'clothing']);
        $brand = Brand::factory()->create(['slug' => 'example-brand']);
        $attributes = ['category_id' => $category->id, 'brand_id' => $brand->id];
        $eligible = $this->product('Light Blue', $attributes);
        $this->product('Black', $attributes);
        $this->product('Light Blue', [...$attributes, 'rating_avg' => 3.5]);
        $this->product('Light Blue', [...$attributes, 'shop_id' => Shop::factory()->create()->id]);
        $filters = ['colors' => ['Light Blue'], 'category' => 'clothing', 'brand' => 'example-brand', 'rating' => 4.5, 'min' => 100, 'max' => 500, 'stock' => 1];
        $shopPath = $this->paths($filters)[1];
        $this->getJson($shopPath)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $eligible->id)->assertJsonPath('meta.price_range', ['min' => 200, 'max' => 200]);
        $this->getJson($this->paths($filters)[0])->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_color_filtering_preserves_server_pagination(): void
    {
        $this->product('Light Blue');
        $this->product('Black');
        $this->product('Red');
        $this->getJson('/api/catalog/products?colors[]=Light%20Blue&colors[]=Black&perPage=1&page=2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.last_page', 2);
    }

    public function test_facet_metadata_keeps_canonical_value_slug_and_swatch_separate(): void
    {
        $this->product('Light Blue');
        $this->getJson('/api/catalog/facets')->assertOk()->assertJsonPath('data.colors.0.value', 'Light Blue')->assertJsonPath('data.colors.0.slug', 'light-blue')->assertJsonPath('data.colors.0.swatch_color', '#ADD8E6');
    }

    public static function malformedColors(): array
    {
        return [
            ['colors[0][nested]=Light%20Blue', 'colors.0'],
            [http_build_query(['colors' => [str_repeat('a', 256)]]), 'colors.0'],
            [http_build_query(['colors' => array_fill(0, 51, 'Blue')]), 'colors'],
        ];
    }

    #[DataProvider('malformedColors')]
    public function test_malformed_or_excessive_color_filters_are_clean_validation_errors(string $query, string $field): void
    {
        foreach (['/api/catalog/products?', "/api/shops/{$this->shop->slug}/products?"] as $prefix) {
            $this->getJson($prefix.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
}
