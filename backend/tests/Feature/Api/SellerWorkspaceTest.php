<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SellerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_listing_returns_shop_totals_and_stock_after_checkout_holds(): void
    {
        $shop = Shop::factory()->create();
        $other = Shop::factory()->create();
        $product = Product::factory()->withVariant(20, 19)->create(['shop_id' => $shop->id]);
        $product->variants()->sole()->inventory()->update(['reserved_quantity' => 14]);
        Product::factory()->withVariant(20, 20)->inactive()->create(['shop_id' => $shop->id]);
        Product::factory()->withVariant(20, 0)->create(['shop_id' => $shop->id]);
        Product::factory()->withVariant(20, 1)->create(['shop_id' => $other->id]);

        $this->actingAs($this->owner($shop), 'sanctum')
            ->getJson("/api/seller/shops/{$shop->id}/products?q={$product->sku}&shop_id={$other->id}")
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.sku', $product->sku)
            ->assertJsonPath('data.0.available_quantity', 5)
            ->assertJsonPath('data.0.is_low_stock', true)
            ->assertJsonPath('summary.total_products', 3)
            ->assertJsonPath('summary.live_products', 2)
            ->assertJsonPath('summary.hidden_products', 1)
            ->assertJsonPath('summary.low_stock_products', 2)
            ->assertJsonCount(3, 'categories');
    }

    public function test_product_filters_combine_category_visibility_stock_and_variant_search(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->withVariant(20, 3)->create(['shop_id' => $shop->id]);
        $variant = $product->variants()->sole();
        $variant->update(['barcode' => '0012345678905']);
        Product::factory()->withVariant(20, 3)->inactive()->create(['shop_id' => $shop->id, 'category_id' => $product->category_id]);
        Product::factory()->withVariant(20, 30)->create(['shop_id' => $shop->id, 'category_id' => $product->category_id]);
        Product::factory()->withVariant(20, 3)->create(['shop_id' => $shop->id]);
        $url = "/api/seller/shops/{$shop->id}/products?category_id={$product->category_id}&status=live&stock_status=low";
        $this->actingAs($this->owner($shop), 'sanctum');

        $this->getJson($url)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $product->id);
        $this->getJson($url.'&q='.$variant->sku)->assertOk()->assertJsonPath('data.0.id', $product->id);
        $this->getJson($url.'&q=0012345678905')->assertOk()->assertJsonPath('data.0.id', $product->id);
        $this->getJson($url.'&q=missing')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('summary.total_products', 4);
    }

    public function test_product_stock_filters_ignore_inactive_variants_and_reservations(): void
    {
        $shop = Shop::factory()->create();
        $held = Product::factory()->withVariant(20, 12)->create(['shop_id' => $shop->id]);
        $held->variants()->sole()->inventory()->update(['reserved_quantity' => 12]);
        $inactive = Product::factory()->withVariant(20, 50)->create(['shop_id' => $shop->id]);
        $inactive->variants()->update(['is_active' => false]);
        $healthy = Product::factory()->withVariant(20, 50)->create(['shop_id' => $shop->id]);
        $this->actingAs($this->owner($shop), 'sanctum');

        $this->getJson("/api/seller/shops/{$shop->id}/products?stock_status=in")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $healthy->id);
        $this->getJson("/api/seller/shops/{$shop->id}/products?stock_status=out")
            ->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson("/api/seller/shops/{$shop->id}/products?stock_status=low")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $held->id)
            ->assertJsonPath('data.0.available_quantity', 0);
    }

    #[TestWith(['newest', 'Beta'])]
    #[TestWith(['name_asc', 'Alpha'])]
    #[TestWith(['name_desc', 'Beta'])]
    #[TestWith(['price_asc', 'Beta'])]
    #[TestWith(['price_desc', 'Alpha'])]
    public function test_product_sort_order_is_applied_before_pagination(string $sort, string $first): void
    {
        $shop = Shop::factory()->create();
        Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Alpha', 'price' => 30]);
        Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Beta', 'price' => 10]);

        $this->actingAs($this->owner($shop), 'sanctum')->getJson("/api/seller/shops/{$shop->id}/products?sort={$sort}")
            ->assertOk()->assertJsonPath('data.0.name', $first);
    }

    public function test_product_page_size_and_page_number_apply_to_the_whole_shop(): void
    {
        $shop = Shop::factory()->create();
        Product::factory()->count(11)->create(['shop_id' => $shop->id]);

        $this->actingAs($this->owner($shop), 'sanctum')->getJson("/api/seller/shops/{$shop->id}/products?per_page=10&page=2")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 11)->assertJsonPath('summary.total_products', 11);
    }

    #[TestWith(['sort', 'price; DROP TABLE products'])]
    #[TestWith(['per_page', 100000])]
    #[TestWith(['page', 0])]
    #[TestWith(['category_id', -1])]
    #[TestWith(['status', 'deleted'])]
    #[TestWith(['stock_status', 'invalid'])]
    public function test_invalid_product_filters_return_422(string $key, mixed $value): void
    {
        $shop = Shop::factory()->create();

        $this->actingAs($this->owner($shop), 'sanctum')->getJson("/api/seller/shops/{$shop->id}/products?".http_build_query([$key => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($key);
    }

    public function test_product_listing_requires_authentication(): void
    {
        $shop = Shop::factory()->create();

        $this->getJson("/api/seller/shops/{$shop->id}/products")->assertUnauthorized();
    }

    private function owner(Shop $shop): User
    {
        $owner = User::factory()->create();
        $owner->shops()->attach($shop->id, ['role_in_shop' => 'owner', 'status' => 'active']);

        return $owner;
    }

    private function allocation(Shop $shop, string $status, string $parentStatus = 'confirmed'): ShopOrder
    {
        return ShopOrder::create([
            'shop_id' => $shop->id,
            'order_id' => Order::factory()->create(['status' => $parentStatus])->id,
            'shop_order_number' => 'WORKSPACE-'.$shop->id.'-'.fake()->unique()->numerify('######'),
            'status' => $status,
            'total' => 20,
        ]);
    }

    public function test_overview_counts_only_actionable_own_orders_and_live_low_stock(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->owner($shop);
        $other = Shop::factory()->create();
        Product::factory()->withVariant(20, 3)->create(['shop_id' => $shop->id]);
        Product::factory()->withVariant(20, 30)->create(['shop_id' => $shop->id]);
        Product::factory()->withVariant(20, 0)->inactive()->create(['shop_id' => $shop->id]);
        Product::factory()->withVariant(20, 0)->create(['shop_id' => $other->id]);
        $this->allocation($shop, 'confirmed');
        $this->allocation($shop, 'processing');
        $this->allocation($shop, 'shipped');
        $this->allocation($shop, 'confirmed', 'pending');
        $this->allocation($shop, 'confirmed', 'cancelled');
        $returned = $this->allocation($shop, 'shipped');
        Shipment::create(['order_id' => $returned->order_id, 'shop_order_id' => $returned->id, 'status' => 'returned', 'address_snapshot' => []]);
        $this->allocation($other, 'confirmed');

        $response = $this->actingAs($owner, 'sanctum')->getJson("/api/seller/shops/{$shop->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.metrics.products', 3)
            ->assertJsonPath('data.metrics.active_products', 2)
            ->assertJsonPath('data.metrics.low_stock', 1)
            ->assertJsonPath('data.metrics.in_stock', 2)
            ->assertJsonPath('data.metrics.orders', 6)
            ->assertJsonPath('data.metrics.to_pack', 1)
            ->assertJsonPath('data.metrics.to_ship', 1)
            ->assertJsonPath('data.metrics.on_way', 1)
            ->assertJsonPath('data.metrics.returns', 1)
            ->assertJsonCount(5, 'data.recent_orders')
            ->assertJsonCount(1, 'data.low_stock');

        foreach ($response->json('data.recent_orders') as $allocation) {
            $this->assertSame($shop->id, $allocation['shop']['id']);
        }
        $this->getJson("/api/seller/shops/{$other->id}/dashboard")->assertForbidden();
        $this->getJson('/api/seller/dashboard')->assertOk()->assertJsonPath('data.metrics.to_pack', 1);
    }

    public function test_simple_product_price_and_visibility_changes_preserve_stock_holds(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->owner($shop);
        $product = Product::factory()->withVariant(20, 12)->create(['shop_id' => $shop->id]);
        $variant = $product->variants()->first();
        $variant->inventory()->update(['reserved_quantity' => 3]);
        $url = "/api/seller/shops/{$shop->id}/products/{$product->id}";
        $this->actingAs($owner, 'sanctum')->putJson($url, ['price' => 29.5, 'compare_at_price' => 40, 'is_active' => false])->assertOk();
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'price' => 29.5, 'compare_at_price' => 40, 'is_active' => false]);
        $this->assertDatabaseHas('inventories', ['product_variant_id' => $variant->id, 'quantity' => 12, 'reserved_quantity' => 3]);
        $this->putJson($url, ['is_active' => true, 'compare_at_price' => null])->assertOk();
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'price' => 29.5, 'compare_at_price' => null, 'is_active' => true]);
    }

    public function test_basic_edits_do_not_replace_multiple_product_options(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->owner($shop);
        $product = Product::factory()->withVariant(20, 12)->create(['shop_id' => $shop->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'shop_id' => $shop->id, 'price' => 25]);
        $this->actingAs($owner, 'sanctum')->putJson("/api/seller/shops/{$shop->id}/products/{$product->id}", ['price' => 30])->assertOk();
        $this->assertSame(2, $product->variants()->count());
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'price' => 25]);
        $this->assertEquals(20, $product->variants()->where('is_default', true)->first()->price);
    }

    public function test_simple_product_stock_can_be_saved_with_details_and_checkout_holds_are_preserved(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->owner($shop);
        $product = Product::factory()->withVariant(20, 12)->create(['shop_id' => $shop->id]);
        $variant = $product->variants()->sole();
        $variant->inventory()->update(['reserved_quantity' => 3]);
        $url = "/api/seller/shops/{$shop->id}/products/{$product->id}";

        $this->actingAs($owner, 'sanctum')->post($url, [
            '_method' => 'PUT', 'name' => 'Updated product', 'price' => '25', 'quantity' => '18',
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.variants.0.quantity', 18)
            ->assertJsonPath('data.variants.0.reserved_quantity', 3);
        $this->assertDatabaseHas('inventories', ['product_variant_id' => $variant->id, 'quantity' => 18, 'reserved_quantity' => 3]);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_id' => $variant->inventory->id, 'type' => 'adjust',
            'quantity' => 6, 'created_by' => $owner->id,
        ]);

        $this->putJson($url, ['name' => 'Must roll back', 'price' => 99, 'quantity' => 2])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame('Updated product', $product->fresh()->name);
        $this->assertEquals(25, $variant->fresh()->price);
        $this->assertEquals(18, $variant->inventory()->sole()->quantity);

        $this->putJson($url, ['quantity' => 3])->assertOk()
            ->assertJsonPath('data.variants.0.quantity', 3)
            ->assertJsonPath('data.variants.0.reserved_quantity', 3);
    }

    public function test_edited_stock_requires_a_non_negative_whole_number_and_is_shop_scoped(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->withVariant(20, 12)->create(['shop_id' => $shop->id]);
        $url = "/api/seller/shops/{$shop->id}/products/{$product->id}";
        $this->actingAs($this->owner($shop), 'sanctum');
        foreach ([-1, 1.5, 1000001, null] as $quantity) {
            $this->putJson($url, ['quantity' => $quantity])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        }
        $this->actingAs(User::factory()->create(), 'sanctum')->putJson($url, ['quantity' => 0])->assertForbidden();
        $this->assertEquals(12, $product->variants()->sole()->inventory->quantity);
    }

    public function test_variant_stock_edits_are_atomic_when_an_option_has_checkout_holds(): void
    {
        $shop = Shop::factory()->create();
        $this->actingAs($this->owner($shop), 'sanctum');
        $url = "/api/seller/shops/{$shop->id}/products";
        $id = $this->postJson($url, ['name' => 'Two options', 'variants' => [
            ['name' => 'Blue', 'sku' => 'STOCK-BLUE', 'price' => 20, 'quantity' => 12],
            ['name' => 'Red', 'sku' => 'STOCK-RED', 'price' => 25, 'quantity' => 8],
        ]])->assertCreated()->json('data.id');
        $product = Product::findOrFail($id);
        $variants = $product->variants()->orderBy('id')->get();
        $variants[1]->inventory()->update(['reserved_quantity' => 3]);
        $rows = [['id' => $variants[0]->id, 'quantity' => 18], ['id' => $variants[1]->id, 'quantity' => 2]];
        $this->putJson($url.'/'.$id, ['variants' => $rows])->assertUnprocessable();
        $this->assertEquals(12, $variants[0]->inventory()->sole()->quantity);
        $this->assertEquals(8, $variants[1]->inventory()->sole()->quantity);
        $rows[1]['quantity'] = 4;
        $this->putJson($url.'/'.$id, ['variants' => $rows])->assertOk();
        $this->assertEquals(18, $variants[0]->inventory()->sole()->quantity);
        $this->assertEquals(4, $variants[1]->inventory()->sole()->quantity);
        $this->assertEquals(3, $variants[1]->inventory()->sole()->reserved_quantity);
    }

    public function test_new_product_stock_requires_a_non_negative_whole_number(): void
    {
        $shop = Shop::factory()->create();
        $this->actingAs($this->owner($shop), 'sanctum');
        foreach ([-1, 1.5, 1000001] as $quantity) {
            $this->postJson("/api/seller/shops/{$shop->id}/products", ['name' => 'Invalid stock', 'initial_stock' => $quantity])
                ->assertUnprocessable()->assertJsonValidationErrors('initial_stock');
        }
    }

    public function test_order_search_accepts_shop_order_code_and_excludes_deleted_parents(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->owner($shop);
        $allocation = $this->allocation($shop, 'confirmed');
        $deleted = $this->allocation($shop, 'confirmed');
        $deleted->order()->first()->delete();
        $this->actingAs($owner, 'sanctum')->getJson("/api/seller/shops/{$shop->id}/orders?q={$allocation->shop_order_number}")
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $allocation->id);
        $this->getJson("/api/seller/shops/{$shop->id}/orders")->assertOk()->assertJsonPath('meta.total', 1);
    }
}
