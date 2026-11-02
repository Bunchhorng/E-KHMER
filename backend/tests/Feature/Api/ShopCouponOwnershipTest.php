<?php

namespace Tests\Feature\Api;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopCouponOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shopA;

    private Shop $shopB;

    private array $shipping;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->shopA = Shop::factory()->create();
        $this->shopB = Shop::factory()->create();
        $this->shipping = [];
        foreach ([$this->shopA, $this->shopB] as $index => $shop) {
            $this->shipping[$shop->id] = ShippingMethod::factory()->create([
                'shop_id' => $shop->id, 'price' => $index === 0 ? 2 : 5,
            ])->id;
        }
    }

    private function add(Shop $shop, float $price): Product
    {
        $product = Product::factory()->withVariant($price, 20)->create(['shop_id' => $shop->id]);
        $this->postJson('/api/cart', ['product_variant_id' => $product->variants()->sole()->id, 'quantity' => 1])->assertCreated();

        return $product;
    }

    private function coupon(array $overrides = []): Coupon
    {
        return Coupon::factory()->create(array_replace([
            'shop_id' => $this->shopA->id, 'code' => 'SHOPA20', 'type' => 'percentage',
            'value' => 20, 'min_order_amount' => 50, 'max_discount_amount' => 15,
        ], $overrides));
    }

    private function payload(Coupon $coupon): array
    {
        return ['shipping_methods' => $this->shipping, 'payment_method' => 'cod', 'coupon_code' => $coupon->code,
            'address' => ['full_name' => 'Buyer', 'address_line1' => 'Street 1', 'city' => 'PP', 'state' => 'PP', 'postal_code' => '12000']];
    }

    private function assertBalanced(Order $order): void
    {
        foreach (['subtotal', 'discount_amount', 'tax_amount', 'shipping_amount', 'total'] as $field) {
            $this->assertSame((int) round((float) $order->{$field} * 100), (int) round((float) $order->shopOrders()->sum($field) * 100), $field);
        }
    }

    public function test_same_shop_coupon_uses_server_cart_and_honors_minimum(): void
    {
        $this->add($this->shopA, 100);
        $coupon = $this->coupon(['value' => 10, 'min_order_amount' => 100, 'max_discount_amount' => null]);

        $this->postJson('/api/coupons/validate', ['code' => strtolower($coupon->code), 'subtotal' => 99999, 'eligible_subtotal' => 99999])
            ->assertOk()->assertJsonPath('data.eligible_subtotal', 100)->assertJsonPath('data.discount_amount', 10)->assertJsonPath('subtotal', 100);
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertDatabaseCount('coupon_usages', 0);
        $response = $this->postJson('/api/checkout', $this->payload($coupon))->assertCreated()->assertJsonPath('data.discount_amount', 10);
        $order = Order::where('order_number', $response->json('data.order_number'))->sole();
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertBalanced($order);
    }

    public function test_wrong_shop_only_cart_is_rejected_without_reservations_or_usage(): void
    {
        $product = $this->add($this->shopB, 100);
        $coupon = $this->coupon(['type' => 'fixed', 'value' => 50, 'min_order_amount' => null]);
        $forged = ['code' => $coupon->code, 'subtotal' => 1000, 'shop_id' => $this->shopA->id, 'eligible_subtotal' => 1000, 'discount_amount' => 50];

        $this->postJson('/api/coupons/validate', $forged)->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'This coupon does not apply to products in your cart.');
        $this->postJson('/api/checkout', $this->payload($coupon), ['Idempotency-Key' => 'wrong-shop-checkout'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        foreach (['orders', 'shop_orders', 'order_items', 'payments', 'coupon_usages', 'checkout_attempts'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertSame(0, $product->variants()->sole()->inventory->reserved_quantity);
    }

    public function test_mixed_shop_cap_and_discount_tax_shipping_allocations_match_parent(): void
    {
        $this->add($this->shopA, 100);
        $this->add($this->shopB, 200);
        $coupon = $this->coupon();

        $this->postJson('/api/coupons/validate', ['code' => $coupon->code])->assertOk()
            ->assertJsonPath('data.eligible_subtotal', 100)->assertJsonPath('data.discount_amount', 15);
        $response = $this->postJson('/api/checkout', $this->payload($coupon))->assertCreated()
            ->assertJsonPath('data.subtotal', 300)->assertJsonPath('data.discount_amount', 15)
            ->assertJsonPath('data.tax_amount', 28.5)->assertJsonPath('data.shipping_amount', 7)->assertJsonPath('data.total', 320.5);
        $order = Order::where('order_number', $response->json('data.order_number'))->sole();
        $a = $order->shopOrders()->where('shop_id', $this->shopA->id)->sole();
        $b = $order->shopOrders()->where('shop_id', $this->shopB->id)->sole();
        $this->assertSame('15.00', $a->discount_amount);
        $this->assertSame('8.50', $a->tax_amount);
        $this->assertSame('0.00', $b->discount_amount);
        $this->assertSame('20.00', $b->tax_amount);
        $this->assertBalanced($order);
    }

    public function test_minimum_spend_cannot_be_satisfied_by_other_shops_or_forged_subtotal(): void
    {
        $this->add($this->shopA, 80);
        $this->add($this->shopB, 120);
        $coupon = $this->coupon(['min_order_amount' => 100]);

        $this->postJson('/api/coupons/validate', ['code' => $coupon->code, 'subtotal' => 10000])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/checkout', $this->payload($coupon))->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('coupon_usages', 0);
        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_fixed_discount_never_exceeds_eligible_shop_subtotal(): void
    {
        $this->add($this->shopA, 30);
        $this->add($this->shopB, 200);
        $coupon = $this->coupon(['type' => 'fixed', 'value' => 50, 'min_order_amount' => null]);

        $response = $this->postJson('/api/checkout', $this->payload($coupon))->assertCreated()->assertJsonPath('data.discount_amount', 30);
        $order = Order::where('order_number', $response->json('data.order_number'))->sole();
        $this->assertSame('30.00', $order->shopOrders()->where('shop_id', $this->shopA->id)->sole()->discount_amount);
        $this->assertSame('0.00', $order->shopOrders()->where('shop_id', $this->shopB->id)->sole()->discount_amount);
        $this->assertBalanced($order);
    }

    public function test_platform_coupon_preserves_discounting_across_all_shops(): void
    {
        $this->add($this->shopA, 100);
        $this->add($this->shopB, 200);
        $coupon = $this->coupon(['shop_id' => null, 'value' => 10, 'min_order_amount' => 300, 'max_discount_amount' => null]);

        $this->postJson('/api/coupons/validate', ['code' => $coupon->code, 'subtotal' => 1])->assertOk()
            ->assertJsonPath('data.eligible_subtotal', 300)->assertJsonPath('data.discount_amount', 30);
        $response = $this->postJson('/api/checkout', $this->payload($coupon))->assertCreated()->assertJsonPath('data.discount_amount', 30);
        $order = Order::where('order_number', $response->json('data.order_number'))->sole();
        $this->assertSame('10.00', $order->shopOrders()->where('shop_id', $this->shopA->id)->sole()->discount_amount);
        $this->assertSame('20.00', $order->shopOrders()->where('shop_id', $this->shopB->id)->sole()->discount_amount);
        $this->assertBalanced($order);
    }

    public function test_retry_consumes_shop_coupon_once_and_preserves_snapshots(): void
    {
        $product = $this->add($this->shopA, 100);
        $coupon = $this->coupon(['usage_limit' => 1]);
        $headers = ['Idempotency-Key' => 'shop-coupon-retry'];
        $first = $this->postJson('/api/checkout', $this->payload($coupon), $headers)->assertCreated();
        $product->update(['shop_id' => $this->shopB->id]);

        $this->postJson('/api/checkout', $this->payload($coupon), $headers)->assertOk()
            ->assertJsonPath('data.order_number', $first->json('data.order_number'))->assertJsonPath('data.discount_amount', 15);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('coupon_usages', 1);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_stock_failure_rolls_back_every_coupon_and_order_side_effect(): void
    {
        $product = $this->add($this->shopA, 100);
        $coupon = $this->coupon();
        $product->variants()->sole()->inventory->update(['quantity' => 0]);

        $this->postJson('/api/checkout', $this->payload($coupon))->assertUnprocessable();
        foreach (['orders', 'shop_orders', 'order_items', 'coupon_usages', 'payments'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_failure_after_usage_insert_rolls_back_stock_and_all_order_records(): void
    {
        $product = $this->add($this->shopA, 100);
        $coupon = $this->coupon();
        CouponUsage::created(function (): void {
            throw ValidationException::withMessages(['payment' => 'Checkout could not be completed.']);
        });

        $this->postJson('/api/checkout', $this->payload($coupon), ['Idempotency-Key' => 'rollback-coupon-checkout'])->assertUnprocessable();
        foreach (['orders', 'shop_orders', 'order_items', 'payments', 'shipments', 'inventory_transactions', 'coupon_usages', 'checkout_attempts'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertSame(0, $product->variants()->sole()->inventory->reserved_quantity);
    }

    public function test_rounding_across_three_shops_preserves_every_cent_without_negative_allocations(): void
    {
        $third = Shop::factory()->create();
        $this->shipping[$third->id] = ShippingMethod::factory()->create(['shop_id' => $third->id, 'price' => 0])->id;
        foreach ([$this->shopA, $this->shopB, $third] as $shop) {
            $this->add($shop, 0.05);
        }
        $coupon = $this->coupon(['shop_id' => null, 'type' => 'fixed', 'value' => 0.04, 'min_order_amount' => null]);

        $response = $this->postJson('/api/checkout', $this->payload($coupon))->assertCreated()
            ->assertJsonPath('data.discount_amount', 0.04)->assertJsonPath('data.tax_amount', 0.01);
        $order = Order::where('order_number', $response->json('data.order_number'))->sole();
        foreach ($order->shopOrders()->get() as $allocation) {
            $this->assertGreaterThanOrEqual(0, (float) $allocation->discount_amount);
            $this->assertGreaterThanOrEqual(0, (float) $allocation->tax_amount);
            $this->assertLessThanOrEqual((float) $allocation->subtotal, (float) $allocation->discount_amount);
        }
        $this->assertBalanced($order);
    }

    public function test_guest_quote_uses_only_the_current_session_cart(): void
    {
        $this->app['auth']->forgetGuards();
        $product = Product::factory()->withVariant(100, 20)->create(['shop_id' => $this->shopA->id]);
        $coupon = $this->coupon(['per_user_limit' => null]);
        $this->withHeader('X-Session-Id', 'coupon-guest-owner')
            ->postJson('/api/cart', ['product_variant_id' => $product->variants()->sole()->id, 'quantity' => 1])->assertCreated();
        $this->postJson('/api/coupons/validate', ['code' => $coupon->code])->assertOk()
            ->assertJsonPath('data.eligible_subtotal', 100)->assertJsonPath('data.discount_amount', 15);
        $this->withHeader('X-Session-Id', 'different-coupon-guest')
            ->postJson('/api/coupons/validate', ['code' => $coupon->code, 'subtotal' => 100])->assertUnprocessable();
        $this->assertSame(0, $coupon->fresh()->used_count);
    }
}
