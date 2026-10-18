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
use Tests\TestCase;

class SellerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

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
