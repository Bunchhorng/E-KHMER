<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Brand;
use App\Models\BusinessExpense;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeededMarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_refactored_factories_create_valid_cambodian_defaults(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->defaultAddress()->for($user)->create();
        $shop = Shop::factory()->withBakong()->create();
        $category = Category::factory()->withIcon('sparkles')->create();
        $brand = Brand::factory()->create();
        $product = Product::factory()->forShop($shop)->featured()->withVariant(14.50, 25)->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);
        $variant = ProductVariant::factory()->for($product)->withInventory(12)->create();
        $lowStock = Inventory::factory()->lowStock(2, 5)->create(['product_variant_id' => ProductVariant::factory()->for($product)->create()->id]);
        $coupon = Coupon::factory()->forShop($shop)->fixed(5)->create();
        $shipping = ShippingMethod::factory()->forShop($shop)->create();
        $order = Order::factory()->for($user)->delivered()->create();
        $cart = Cart::factory()->for($user)->create();
        $cartItem = CartItem::factory()->for($cart)->for($variant, 'variant')->create();
        $expense = BusinessExpense::factory()->for($shop)->create(['created_by' => $user->id]);

        $this->assertSame('KH', $address->country);
        $this->assertTrue($address->is_default);
        $this->assertSame('KH', $shop->country);
        $this->assertNotNull($shop->bakong_account_id);
        $this->assertSame('sparkles', $category->icon);
        $this->assertSame($shop->id, $variant->shop_id);
        $this->assertSame(2, $lowStock->quantity);
        $this->assertSame($shop->id, $coupon->shop_id);
        $this->assertSame($shop->id, $shipping->shop_id);
        $this->assertSame(Order::STATUS_DELIVERED, $order->status);
        $this->assertSame($cart->id, $cartItem->cart_id);
        $this->assertSame($shop->id, $expense->shop_id);
    }

    public function test_seeded_marketplace_endpoints_serve_catalog_shops_cart_orders_and_admin_data(): void
    {
        Storage::fake('public');
        $this->seed();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(15, 'data');

        $this->getJson('/api/shops?per_page=24')
            ->assertOk()
            ->assertJsonPath('meta.total', 14)
            ->assertJsonCount(14, 'data');

        $this->getJson('/api/catalog/products?perPage=12')
            ->assertOk()
            ->assertJsonPath('meta.total', 245);

        $this->getJson('/api/catalog/products?category=kampot-pepper-spices')
            ->assertOk()
            ->assertJsonPath('meta.total', 17);

        $customer = User::where('email', 'customer@ekhmer.com')->firstOrFail();
        $this->actingAs($customer, 'sanctum');

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonCount(3, 'data.items');

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', $customer->orders()->count());

        $seller = User::where('email', 'seller@ekhmer.com')->firstOrFail();
        $this->actingAs($seller, 'sanctum');
        $this->getJson('/api/seller/dashboard')->assertOk();
        $this->getJson('/api/seller/shops')->assertOk();

        $admin = User::where('email', 'admin@ekhmer.com')->firstOrFail();
        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/admin/dashboard/overview')->assertOk();
    }
}
