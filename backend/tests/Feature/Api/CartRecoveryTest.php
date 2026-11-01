<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CartRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    private function add(float $price, int $quantity = 1, ?Shop $shop = null): array
    {
        $product = Product::factory()->withVariant($price, 20)->create(['shop_id' => $shop?->id]);
        $variant = $product->variants()->sole();
        $this->postJson('/api/cart', ['product_variant_id' => $variant->id, 'quantity' => $quantity])->assertCreated();

        return [$product, $variant];
    }

    public function test_deleted_variant_does_not_hide_valid_items_or_change_stored_quantities(): void
    {
        $this->add(50, shop: Shop::factory()->create());
        [, $deleted] = $this->add(100, 2, Shop::factory()->create());
        $this->add(30, shop: Shop::factory()->create());
        $deleted->delete();

        $this->getJson('/api/cart')->assertOk()->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.items.0.available', true)
            ->assertJsonPath('data.items.1.variant', null)
            ->assertJsonPath('data.items.1.available', false)
            ->assertJsonPath('data.items.1.unavailable_reason', 'variant_deleted')
            ->assertJsonPath('data.items.1.quantity', 2)
            ->assertJsonPath('data.items.2.available', true)
            ->assertJsonPath('data.totals.subtotal', 80)
            ->assertJsonPath('data.totals.tax_amount', 8)
            ->assertJsonPath('data.totals.total', 88)
            ->assertJsonPath('data.totals.items_count', 4);
        $this->getJson('/api/cart/totals')->assertOk()->assertJsonPath('data.subtotal', 80)->assertJsonPath('data.items_count', 4);
        $this->assertDatabaseCount('cart_items', 3);
    }

    public static function unavailableStates(): array
    {
        return [
            'deleted product' => ['product_deleted'],
            'inactive product' => ['product_inactive'],
            'inactive variant' => ['variant_inactive'],
            'suspended shop' => ['shop_inactive'],
            'deleted shop' => ['shop_deleted'],
            'no stock' => ['out_of_stock'],
            'reserved stock' => ['insufficient_stock'],
        ];
    }

    #[DataProvider('unavailableStates')]
    public function test_unavailable_states_are_explicit_and_excluded_from_totals(string $state): void
    {
        $this->add(50);
        $shop = Shop::factory()->create();
        [$product, $variant] = $this->add(100, 3, $shop);
        match ($state) {
            'product_deleted' => $product->delete(),
            'product_inactive' => $product->update(['is_active' => false]),
            'variant_inactive' => $variant->update(['is_active' => false]),
            'shop_inactive' => $shop->update(['status' => 'suspended']),
            'shop_deleted' => $shop->delete(),
            'out_of_stock' => $variant->inventory->update(['quantity' => 0]),
            'insufficient_stock' => $variant->inventory->update(['reserved_quantity' => 18]),
        };

        $response = $this->getJson('/api/cart')->assertOk()->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.available', true)
            ->assertJsonPath('data.items.1.available', false)
            ->assertJsonPath('data.items.1.unavailable_reason', $state === 'shop_deleted' ? 'shop_inactive' : $state)
            ->assertJsonPath('data.items.1.quantity', 3)
            ->assertJsonPath('data.totals.subtotal', 50)->assertJsonPath('data.totals.total', 55)
            ->assertJsonPath('data.totals.items_count', 4);
        if ($state === 'insufficient_stock') {
            $response->assertJsonPath('data.items.1.available_quantity', 2);
        }
        $this->getJson('/api/cart/totals')->assertOk()->assertJsonPath('data.subtotal', 50);
        $this->assertDatabaseCount('cart_items', 2);

        $this->postJson('/api/checkout', [
            'shipping_method_id' => ShippingMethod::factory()->create()->id,
            'payment_method' => 'cod',
            'address' => ['full_name' => 'Buyer', 'address_line1' => 'Street 1', 'city' => 'PP', 'state' => 'PP', 'postal_code' => '12000'],
        ])->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertDatabaseCount('coupon_usages', 0);
    }

    public function test_stale_item_is_removable_by_cart_row_id_and_checkout_recovers(): void
    {
        [, $valid] = $this->add(50);
        [, $stale] = $this->add(100);
        $row = Cart::where('user_id', auth('sanctum')->id())->sole()->items()->where('product_variant_id', $stale->id)->sole();
        $stale->delete();
        $payload = [
            'shipping_method_id' => ShippingMethod::factory()->create(['price' => 5])->id,
            'payment_method' => 'cod',
            'address' => ['full_name' => 'Buyer', 'address_line1' => 'Street 1', 'city' => 'PP', 'state' => 'PP', 'postal_code' => '12000'],
        ];

        $this->postJson('/api/checkout', $payload)->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(0, $valid->inventory->reserved_quantity);
        $this->deleteJson('/api/cart/items/'.$row->id)->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.available', true);
        $this->postJson('/api/checkout', $payload)->assertCreated()->assertJsonPath('data.subtotal', 50)->assertJsonPath('data.total', 60);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_insufficient_stock_can_be_resolved_by_explicit_quantity_change(): void
    {
        [, $variant] = $this->add(10, 5);
        $row = Cart::where('user_id', auth('sanctum')->id())->sole()->items()->sole();
        $variant->inventory->update(['quantity' => 2]);

        $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.unavailable_reason', 'insufficient_stock')->assertJsonPath('data.totals.subtotal', 0);
        $this->putJson('/api/cart/items/'.$row->id, ['quantity' => 3])->assertUnprocessable();
        $this->assertSame(5, $row->fresh()->quantity);
        $this->putJson('/api/cart/items/'.$row->id, ['quantity' => 2])->assertOk()
            ->assertJsonPath('data.items.0.available', true)->assertJsonPath('data.totals.subtotal', 20);
    }

    public function test_guest_cart_can_read_and_remove_deleted_variant(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withHeader('X-Session-Id', 'recovery-guest');
        $this->add(50);
        [, $variant] = $this->add(100);
        $row = Cart::where('session_id', 'recovery-guest')->sole()->items()->where('product_variant_id', $variant->id)->sole();
        $variant->delete();

        $this->getJson('/api/cart')->assertOk()->assertJsonCount(2, 'data.items')->assertJsonPath('data.items.1.available', false)->assertJsonPath('data.totals.subtotal', 50);
        $this->withHeader('X-Session-Id', 'other-recovery-guest')->deleteJson('/api/cart/items/'.$row->id)->assertSuccessful();
        $this->assertDatabaseHas('cart_items', ['id' => $row->id]);
        $this->withHeader('X-Session-Id', 'recovery-guest')->deleteJson('/api/cart/items/'.$row->id)->assertOk()->assertJsonCount(1, 'data.items');
    }

    public function test_login_merge_preserves_account_stale_rows_and_valid_guest_items(): void
    {
        $user = auth('sanctum')->user();
        [, $accountStale] = $this->add(100);
        $accountStale->delete();
        $this->app['auth']->forgetGuards();
        $this->withHeader('X-Session-Id', 'recovery-login');
        $this->add(50);
        [, $guestStale] = $this->add(30);
        $guestStale->delete();

        $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($login->json('data.token'))->getJson('/api/cart')->assertOk()->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.available', false)->assertJsonPath('data.items.1.available', true)
            ->assertJsonPath('data.totals.subtotal', 50)->assertJsonPath('data.totals.items_count', 2);
        // Existing merge policy skips unavailable guest rows, then clears the guest cart.
        $this->assertSame(0, Cart::where('session_id', 'recovery-login')->sole()->items()->count());
    }

    public function test_coupon_eligibility_excludes_unavailable_lines_without_leaking_to_other_shops(): void
    {
        $shop = Shop::factory()->create();
        $this->add(50, shop: $shop);
        [, $stale] = $this->add(100, shop: $shop);
        $this->add(200, shop: Shop::factory()->create());
        $stale->update(['is_active' => false]);
        $coupon = Coupon::factory()->create(['shop_id' => $shop->id, 'value' => 10, 'min_order_amount' => 100, 'max_discount_amount' => null]);

        $this->postJson('/api/coupons/validate', ['code' => $coupon->code])->assertUnprocessable();
        $coupon->update(['min_order_amount' => 50]);
        $this->postJson('/api/coupons/validate', ['code' => $coupon->code])->assertOk()
            ->assertJsonPath('data.eligible_subtotal', 50)->assertJsonPath('data.discount_amount', 5)->assertJsonPath('subtotal', 250);
        $coupon->update(['shop_id' => null]);
        $this->postJson('/api/coupons/validate', ['code' => $coupon->code])->assertOk()
            ->assertJsonPath('data.eligible_subtotal', 250)->assertJsonPath('data.discount_amount', 25);
        $this->assertDatabaseCount('coupon_usages', 0);
    }
}
