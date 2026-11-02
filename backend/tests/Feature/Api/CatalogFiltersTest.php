<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function product(float $price, float $rating = 0, array $attributes = []): Product
    {
        return Product::factory()->withVariant($price, 10)->create([
            'price' => $price, 'rating_avg' => $rating, 'rating_count' => $rating > 0 ? 2 : 0, ...$attributes,
        ]);
    }

    public static function acceptedRatings(): array
    {
        return array_map(fn ($rating) => [$rating], [1, 3, 3.5, 4, 4.5, 5]);
    }

    #[DataProvider('acceptedRatings')]
    public function test_integer_and_fractional_rating_thresholds_are_accepted(float $rating): void
    {
        $this->product(800, 5);
        $this->getJson('/api/catalog/products?rating='.$rating)->assertOk()->assertJsonCount(1, 'data');
    }

    public static function invalidFilters(): array
    {
        return [
            ['rating=-1', 'rating'], ['rating=5.5', 'rating'], ['rating=abc', 'rating'],
            ['rating=NaN', 'rating'], ['rating=Infinity', 'rating'], ['rating[]=4.5', 'rating'],
            ['min=-5', 'min'], ['max=-1', 'max'], ['min=abc', 'min'], ['max=Infinity', 'max'],
            ['min=NaN', 'min'], ['min=1e999', 'min'], ['max=1e999', 'max'], ['min=500&max=100', 'max'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected_on_marketplace_and_shop_endpoints(string $query, string $field): void
    {
        $shop = Shop::factory()->create();
        $this->getJson('/api/catalog/products?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->getJson("/api/shops/{$shop->slug}/products?{$query}")->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_rating_means_at_least_the_unrounded_stored_average_and_excludes_unrated_products(): void
    {
        $high = $this->product(800, 4.8);
        $edge = $this->product(300, 4.5);
        $this->product(100, 4.49);
        $this->product(50);
        $this->product(60, 4.9, ['rating_count' => 0]);
        $ids = $this->getJson('/api/catalog/products?rating=4.5')->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$high->id, $edge->id], $ids);
        $this->getJson('/api/catalog/products')->assertOk()->assertJsonCount(5, 'data');
    }

    public function test_decimal_price_bounds_and_prices_above_the_old_ceiling_are_filterable(): void
    {
        $this->product(0);
        $low = $this->product(19.99);
        $high = $this->product(49.99);
        $expensive = $this->product(800, 4.8);
        $this->product(1299.99);
        $ids = $this->getJson('/api/catalog/products?min=19.99&max=49.99')->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$low->id, $high->id], $ids);
        $this->getJson('/api/catalog/products?min=500&max=1000&rating=4.5')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $expensive->id);
        $this->getJson('/api/catalog/products?min=0&max=0')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.price', 0);
    }

    public function test_metadata_is_numeric_excludes_price_filters_and_is_empty_without_purchasable_prices(): void
    {
        $this->getJson('/api/catalog/products')->assertOk()->assertJsonPath('meta.price_range', ['min' => null, 'max' => null]);
        $this->product(4);
        $this->product(800);
        $this->product(1299.99);
        $this->getJson('/api/catalog/products?min=500&max=1000')->assertOk()->assertJsonPath('meta.price_range.min', 4)->assertJsonPath('meta.price_range.max', 1299.99);
        $this->getJson('/api/catalog/facets?min=500&max=1000')->assertOk()->assertJsonPath('data.price_range.min', 4)->assertJsonPath('data.price_range.max', 1299.99);
        $this->getJson('/api/catalog/products?min=2000')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.price_range.max', 1299.99);
    }

    public function test_variant_prices_use_one_matching_available_variant_and_card_adds_that_advertised_variant(): void
    {
        $product = $this->product(500);
        $cheap = $product->variants()->sole();
        $cheap->update(['price' => 20]);
        $expensive = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 100, 'is_active' => true]);
        $expensive->inventory()->create(['quantity' => 10, 'reserved_quantity' => 0]);
        $this->getJson('/api/catalog/products?max=50')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.price', 20)->assertJsonPath('data.0.variants.0.id', $cheap->id);
        $this->getJson('/api/catalog/products?min=80&max=120')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.price', 100)->assertJsonPath('data.0.variants.0.id', $expensive->id);
        $this->getJson('/api/catalog/products?min=30&max=90')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/catalog/products')->assertOk()->assertJsonPath('meta.price_range.min', 20)->assertJsonPath('meta.price_range.max', 100);
    }

    public function test_crossed_out_prices_and_purchase_cost_do_not_affect_selling_price_filters(): void
    {
        $product = $this->product(400, 0, ['compare_at_price' => 500, 'cost_price' => 5]);
        $product->variants()->sole()->update(['price' => null, 'compare_at_price' => 600]);
        $this->getJson('/api/catalog/products?max=450')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.price', 400)->assertJsonPath('meta.price_range.min', 400)->assertJsonPath('meta.price_range.max', 400);
        $this->getJson('/api/catalog/products?max=399.99')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_public_bounds_exclude_inactive_deleted_and_fully_reserved_inventory(): void
    {
        $this->product(10);
        $this->product(5000, 0, ['is_active' => false]);
        $this->product(6000, 0, ['shop_id' => Shop::factory()->suspended()->create()->id]);
        $this->product(7000)->delete();
        $inactive = $this->product(8000);
        $inactive->variants()->sole()->update(['is_active' => false]);
        $reserved = $this->product(9000);
        $reserved->variants()->sole()->inventory->update(['reserved_quantity' => 10]);
        Product::factory()->create(['price' => 10000]);
        $this->getJson('/api/catalog/facets')->assertOk()->assertJsonPath('data.price_range', ['min' => 10, 'max' => 10]);
    }

    public function test_shop_listing_preserves_pagination_and_uses_only_its_own_price_bounds(): void
    {
        $shop = Shop::factory()->create();
        foreach ([10, 50, 100] as $price) {
            $this->product($price, 4.5, ['shop_id' => $shop->id]);
        }
        $this->product(2000, 5, ['shop_id' => Shop::factory()->create()->id]);
        $this->getJson("/api/shops/{$shop->slug}/products?max=50&rating=4.5&per_page=1")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.price_range', ['min' => 10, 'max' => 100]);
    }

    public function test_category_brand_search_rating_and_variant_attributes_compose_with_price_and_sort(): void
    {
        $category = Category::factory()->create(['slug' => 'electronics']);
        $other = Category::factory()->create(['slug' => 'books']);
        $brand = Brand::factory()->create(['slug' => 'acme']);
        $color = Attribute::create(['name' => 'Color', 'slug' => 'color', 'type' => 'color']);
        $red = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Red']);
        $eligible = $this->product(800, 4.8, ['name' => 'Acme Camera', 'category_id' => $category->id, 'brand_id' => $brand->id]);
        $eligible->variants()->sole()->attributeValues()->create(['attribute_value_id' => $red->id]);
        $this->product(50, 5, ['name' => 'Camera Guide', 'category_id' => $other->id]);
        $this->product(1200, 3.5, ['category_id' => $category->id]);
        $query = http_build_query(['q' => 'Camera', 'category' => 'electronics', 'brand' => 'acme', 'rating' => 4.5, 'colors' => ['Red'], 'stock' => 1, 'min' => 500, 'max' => 1000, 'sort' => 'price-desc']);
        $this->getJson('/api/catalog/products?'.$query)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $eligible->id)->assertJsonPath('meta.price_range', ['min' => 800, 'max' => 800]);
        $this->getJson('/api/catalog/products?category=books')->assertOk()->assertJsonPath('meta.price_range', ['min' => 50, 'max' => 50]);
    }

    public function test_price_sort_uses_the_advertised_variant_selling_price(): void
    {
        $expensive = $this->product(800);
        $expensive->update(['price' => 1]);
        $cheap = $this->product(300);
        $cheap->update(['price' => 2000]);
        $this->getJson('/api/catalog/products?sort=price-asc')->assertOk()->assertJsonPath('data.0.id', $cheap->id)->assertJsonPath('data.0.price', 300)->assertJsonPath('data.1.price', 800);
    }

    public function test_listing_query_count_does_not_grow_per_product(): void
    {
        $this->product(10, 4.5);
        DB::enableQueryLog();
        $this->getJson('/api/catalog/products?rating=4.5')->assertOk();
        $first = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 8; $i++) {
            $this->product(20 + $i, 4.5);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/catalog/products?rating=4.5')->assertOk()->assertJsonCount(9, 'data');
        $this->assertSame($first, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }
}
