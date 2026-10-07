<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerShopScopeTest extends TestCase
{
    use RefreshDatabase;

    private function manager(Shop $shop): User
    {
        $user = User::factory()->create();
        $user->shops()->attach($shop->id, ['role_in_shop' => 'manager', 'status' => 'active']);

        return $user;
    }

    public function test_seller_lists_only_shops_they_manage(): void
    {
        $first = Shop::factory()->create(['name' => 'First Shop']);
        $second = Shop::factory()->create(['name' => 'Second Shop']);
        $manager = $this->manager($first);

        $this->actingAs($manager, 'sanctum')->getJson('/api/seller/shops')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id);

        $this->assertNotSame($second->id, $this->actingAs($manager, 'sanctum')->getJson('/api/seller/shops')->json('data.0.id'));
    }

    public function test_customer_cannot_open_a_shop_management_dashboard(): void
    {
        $shop = Shop::factory()->create();
        $customer = User::factory()->create();

        $this->actingAs($customer, 'sanctum')->getJson("/api/seller/shops/{$shop->id}/dashboard")->assertForbidden();
    }

    public function test_seller_cannot_read_another_shops_dashboard_or_products(): void
    {
        $mine = Shop::factory()->create();
        $theirs = Shop::factory()->create();
        $manager = $this->manager($mine);
        Product::factory()->create(['shop_id' => $theirs->id]);

        $this->actingAs($manager, 'sanctum')->getJson("/api/seller/shops/{$theirs->id}/dashboard")->assertForbidden();
        $this->actingAs($manager, 'sanctum')->getJson("/api/seller/shops/{$theirs->id}/products")->assertForbidden();
    }

    public function test_seller_product_routes_are_forced_to_the_route_shop(): void
    {
        $mine = Shop::factory()->create();
        $other = Shop::factory()->create();
        $manager = $this->manager($mine);

        $response = $this->actingAs($manager, 'sanctum')->postJson("/api/seller/shops/{$mine->id}/products", [
            'name' => 'Scoped Seller Product',
            'price' => 19.99,
            'initial_stock' => 7,
            'shop_id' => $other->id,
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
        ])->assertCreated()->assertJsonPath('data.shop_id', $mine->id);

        $this->assertDatabaseHas('products', ['name' => 'Scoped Seller Product', 'shop_id' => $mine->id]);
        $variantId = $response->json('data.variants.0.id');
        $this->assertDatabaseHas('inventories', ['product_variant_id' => $variantId, 'shop_id' => $mine->id, 'quantity' => 7]);
    }

    public function test_seller_inventory_is_limited_to_the_selected_shop(): void
    {
        $mine = Shop::factory()->create();
        $other = Shop::factory()->create();
        $manager = $this->manager($mine);
        Product::factory()->withVariant(10, 3)->create(['shop_id' => $mine->id]);
        Product::factory()->withVariant(10, 3)->create(['shop_id' => $other->id]);

        $this->actingAs($manager, 'sanctum')->getJson("/api/seller/shops/{$mine->id}/inventory")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.shop_id', $mine->id);
    }

    public function test_seller_only_sees_orders_allocated_to_their_shop(): void
    {
        $mine = Shop::factory()->create();
        $other = Shop::factory()->create();
        $manager = $this->manager($mine);
        $order = \App\Models\Order::factory()->create();

        ShopOrder::create(['order_id' => $order->id, 'shop_id' => $mine->id, 'shop_order_number' => 'MINE-001', 'status' => 'pending', 'total' => 25]);
        ShopOrder::create(['order_id' => $order->id, 'shop_id' => $other->id, 'shop_order_number' => 'OTHER-001', 'status' => 'pending', 'total' => 25]);

        $this->actingAs($manager, 'sanctum')->getJson("/api/seller/shops/{$mine->id}/orders")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.shop_order_number', 'MINE-001');
    }

    public function test_seller_can_submit_more_than_one_shop_application(): void
    {
        $seller = User::factory()->create();

        foreach (['First Application', 'Second Application'] as $name) {
            $this->actingAs($seller, 'sanctum')->postJson('/api/seller/application', ['name' => $name])->assertCreated();
        }

        $this->assertSame(2, $seller->fresh()->shops()->wherePivot('role_in_shop', 'owner')->count());
    }
}
