<?php

namespace Tests\Feature\Api;

use App\Models\BakongPayment;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\Shop;
use App\Models\User;
use App\Services\ReceiptBrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutQuoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(), 'sanctum');
        Notification::fake();
        Http::preventStrayRequests();
    }

    private function add(float $price = 100, int $quantity = 1, ?Shop $shop = null): Product
    {
        $shop ??= Shop::factory()->create();
        $product = Product::factory()->withVariant($price, 50)->create(['shop_id' => $shop->id]);
        if (! ShippingMethod::where('shop_id', $shop->id)->exists()) {
            ShippingMethod::factory()->create(['shop_id' => $shop->id, 'price' => 0]);
        }
        $this->postJson('/api/cart', ['product_variant_id' => $product->variants()->sole()->id, 'quantity' => $quantity])->assertCreated();

        return $product;
    }

    private function coupon(array $overrides = []): Coupon
    {
        return Coupon::factory()->create(array_replace([
            'code' => 'CAP5', 'type' => 'percentage', 'value' => 10,
            'max_discount_amount' => 5, 'min_order_amount' => null,
        ], $overrides));
    }

    private function quote(array $payload = []): array
    {
        return $this->postJson('/api/checkout/quote', $payload)->assertOk()->json('data');
    }

    private function checkout(array $quote, array $overrides = [], array $headers = [])
    {
        return $this->postJson('/api/checkout', array_replace([
            'shipping_methods' => array_column($quote['shipping_methods'], 'id', 'shop_id'),
            'coupon_code' => $quote['coupon']['code'] ?? null,
            'quote_fingerprint' => $quote['fingerprint'], 'payment_method' => 'cod',
            'address' => ['full_name' => 'Buyer', 'address_line1' => 'Street 1', 'city' => 'PP', 'state' => 'PP', 'postal_code' => '12000'],
        ], $overrides), $headers);
    }

    public static function discountCases(): array
    {
        return [
            'percentage below cap' => [100, 'percentage', 5, 20, 5, 5, 9.5, 104.5],
            'percentage above cap' => [100, 'percentage', 10, 5, 10, 5, 9.5, 104.5],
            'fixed ignores percentage cap by existing policy' => [100, 'fixed', 10, 5, 10, 10, 9, 99],
            'fixed limited to eligible subtotal' => [5, 'fixed', 10, null, 10, 5, 0, 0],
            'decimal quantity rounding' => [19.99, 'percentage', 10, null, 6, 6, 5.4, 59.37, 3],
        ];
    }

    #[DataProvider('discountCases')]
    public function test_quote_discount_tax_order_and_payment_reconcile(float $price, string $type, float $value, ?float $cap, float $raw, float $discount, float $tax, float $total, int $quantity = 1): void
    {
        $this->add($price, $quantity);
        $coupon = $this->coupon(['type' => $type, 'value' => $value, 'max_discount_amount' => $cap]);
        $quote = $this->quote(['coupon_code' => $coupon->code, 'discount_amount' => 999, 'tax_amount' => 0, 'shipping_amount' => 0, 'total' => 1]);
        $this->assertEquals(round($price * $quantity, 2), $quote['subtotal']);
        $this->assertEquals($raw, $quote['coupon']['raw_discount']);
        $this->assertEquals($discount, $quote['discount_amount']);
        $this->assertEquals($tax, $quote['tax_amount']);
        $this->assertEquals($total, $quote['total']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('coupon_usages', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(0, $coupon->fresh()->used_count);

        $response = $this->checkout($quote, ['subtotal' => 1, 'discount' => 99, 'tax' => 0, 'shipping' => 0, 'grand_total' => 1, 'payment_amount' => 1])->assertCreated();
        foreach (['subtotal', 'discount_amount', 'tax_amount', 'shipping_amount', 'total'] as $field) {
            $response->assertJsonPath('data.'.$field, $quote[$field]);
        }
        $response->assertJsonPath('data.payment.amount', $quote['total']);
        $order = Order::sole();
        foreach (['subtotal', 'discount_amount', 'tax_amount', 'shipping_amount', 'total'] as $field) {
            $this->assertSame((int) round($quote[$field] * 100), (int) round($order->shopOrders()->sum($field) * 100), $field);
        }
    }

    public function test_shop_and_global_caps_preserve_ownership_and_per_shop_shipping(): void
    {
        $a = $this->add(100);
        $b = $this->add(200);
        ShippingMethod::where('shop_id', $a->shop_id)->update(['price' => 3]);
        ShippingMethod::where('shop_id', $b->shop_id)->update(['price' => 2]);
        $coupon = $this->coupon(['shop_id' => $a->shop_id, 'value' => 20, 'max_discount_amount' => 15]);
        $quote = $this->quote(['coupon_code' => $coupon->code]);
        $this->assertEquals(100, $quote['eligible_subtotal']);
        $this->assertEquals(15, $quote['discount_amount']);
        $this->assertEquals(5, $quote['shipping_amount']);
        $this->assertEquals(318.5, $quote['total']);
        $this->checkout($quote)->assertCreated();
        $this->assertSame('15.00', Order::sole()->shopOrders()->where('shop_id', $a->shop_id)->sole()->discount_amount);
        $this->assertSame('0.00', Order::sole()->shopOrders()->where('shop_id', $b->shop_id)->sole()->discount_amount);

        $globalCoupon = $this->coupon(['code' => 'GLOBAL20', 'shop_id' => null, 'value' => 10, 'max_discount_amount' => 20]);
        $global = $this->quote(['coupon_code' => $globalCoupon->code]);
        $this->assertEquals(300, $global['eligible_subtotal']);
        $this->assertEquals(30, $global['coupon']['raw_discount']);
        $this->assertEquals(20, $global['discount_amount']);
        $this->assertEquals(313, $global['total']);
        $created = $this->checkout($global)->assertCreated();
        $order = Order::where('order_number', $created->json('data.order_number'))->sole();
        $this->assertSame(2000, (int) round($order->shopOrders()->sum('discount_amount') * 100));
        $this->assertSame($order->total, $order->payment->amount);
    }

    public function test_quantity_removal_coupon_removal_and_shipping_changes_requote(): void
    {
        $product = $this->add(50);
        $coupon = $this->coupon(['max_discount_amount' => 15]);
        $cart = Cart::where('user_id', auth('sanctum')->id())->sole();
        $item = $cart->items()->sole();
        $first = $this->quote(['coupon_code' => $coupon->code]);
        $this->assertEquals(5, $first['discount_amount']);
        $this->putJson('/api/cart/items/'.$item->id, ['quantity' => 4])->assertOk();
        $this->assertEquals(15, $this->quote(['coupon_code' => $coupon->code])['discount_amount']);
        $this->putJson('/api/cart/items/'.$item->id, ['quantity' => 1])->assertOk();
        $this->assertEquals(5, $this->quote(['coupon_code' => $coupon->code])['discount_amount']);
        $this->assertEquals(55, $this->quote()['total']);
        $express = ShippingMethod::factory()->create(['shop_id' => $product->shop_id, 'price' => 7.25]);
        $this->assertEquals(56.75, $this->quote(['coupon_code' => $coupon->code, 'shipping_methods' => [$product->shop_id => $express->id]])['total']);
        $this->deleteJson('/api/cart/items/'.$item->id)->assertOk();
        $empty = $this->quote();
        $this->assertEquals(0, $empty['total']);
        $this->assertFalse($empty['can_checkout']);
    }

    public function test_unavailable_lines_do_not_contribute_to_any_quote_amount_or_shipping(): void
    {
        $this->add(50);
        $deleted = $this->add(100);
        $deleted->variants()->sole()->delete();
        ShippingMethod::where('shop_id', $deleted->shop_id)->update(['price' => 99]);
        $coupon = $this->coupon(['max_discount_amount' => null]);
        $quote = $this->quote(['coupon_code' => $coupon->code]);
        $this->assertEquals(50, $quote['subtotal']);
        $this->assertEquals(5, $quote['discount_amount']);
        $this->assertEquals(0, $quote['shipping_amount']);
        $this->assertEquals(49.5, $quote['total']);
        $this->assertFalse($quote['can_checkout']);
        $this->assertCount(1, $quote['shipping_methods']);
        $this->checkout($quote)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public static function staleConditions(): array
    {
        return [['price'], ['shipping'], ['coupon_expired'], ['coupon_used'], ['coupon_cap']];
    }

    #[DataProvider('staleConditions')]
    public function test_changed_quote_or_invalid_coupon_cannot_create_an_unreviewed_order(string $condition): void
    {
        $product = $this->add();
        $coupon = $this->coupon(['usage_limit' => 1]);
        $quote = $this->quote(['coupon_code' => $coupon->code]);
        match ($condition) {
            'price' => $product->variants()->sole()->update(['price' => 120]),
            'shipping' => ShippingMethod::where('shop_id', $product->shop_id)->update(['price' => 8]),
            'coupon_expired' => $coupon->update(['expires_at' => now()->subSecond()]),
            'coupon_used' => $coupon->update(['used_count' => 1]),
            'coupon_cap' => $coupon->update(['max_discount_amount' => 2]),
        };
        $response = $this->checkout($quote);
        if (in_array($condition, ['coupon_expired', 'coupon_used'], true)) {
            $response->assertUnprocessable()->assertJsonValidationErrors('code');
        } else {
            $response->assertStatus(409)->assertJsonValidationErrors('quote');
            $new = $this->quote(['coupon_code' => $coupon->code]);
            $this->assertNotSame($quote['fingerprint'], $new['fingerprint']);
        }
        foreach (['orders', 'shop_orders', 'payments', 'coupon_usages', 'inventory_transactions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_quoted_checkout_retry_preserves_the_original_total_after_catalog_changes(): void
    {
        $product = $this->add();
        $quote = $this->quote();
        $headers = ['Idempotency-Key' => 'quoted-checkout-retry'];
        $first = $this->checkout($quote, headers: $headers)->assertCreated();
        $product->variants()->sole()->update(['price' => 150]);
        $this->checkout($quote, headers: $headers)->assertOk()->assertJsonPath('data.total', $first->json('data.total'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_quote_fingerprint_tampering_is_rejected_without_side_effects(): void
    {
        $this->add();
        $quote = $this->quote();
        $this->checkout($quote, ['quote_fingerprint' => str_repeat('a', 64)], ['Idempotency-Key' => 'tampered-quote-test'])
            ->assertStatus(409)->assertJsonValidationErrors('quote');
        foreach (['orders', 'payments', 'coupon_usages', 'inventory_transactions', 'checkout_attempts'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_guest_quote_requires_session_and_only_prices_its_own_cart(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/checkout/quote')->assertUnprocessable()->assertJsonValidationErrors('session');
        $this->withHeader('X-Session-Id', 'quote-guest');
        $this->add();
        $quote = $this->quote();
        $this->assertEquals(110, $quote['total']);
        $this->withHeader('X-Session-Id', 'other-quote-guest');
        $other = $this->quote(['subtotal' => 100]);
        $this->assertEquals(0, $other['total']);
        $this->assertFalse($other['can_checkout']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_receipt_keeps_persisted_amounts_after_coupon_edit_and_deletion(): void
    {
        $this->add();
        $coupon = $this->coupon();
        $this->checkout($this->quote(['coupon_code' => $coupon->code]))->assertCreated();
        $order = Order::sole()->load(['items', 'payment']);
        $render = fn () => view('reports.receipt', ['order' => $order, 'branding' => app(ReceiptBrandingService::class)->forOrder($order)])->render();
        $before = $render();
        foreach (['100.00', '5.00', '9.50', '104.50'] as $amount) {
            $this->assertStringContainsString($amount, $before);
        }
        $coupon->update(['value' => 90, 'max_discount_amount' => 90]);
        $coupon->delete();
        $this->assertSame($before, $render());
        $this->get('/api/orders/'.$order->order_number.'/receipt')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_bakong_qr_and_payment_amounts_use_persisted_shop_totals(): void
    {
        config(['ecommerce.bakong_token' => 'test-only']);
        foreach ([100, 50] as $index => $price) {
            $this->add($price, shop: Shop::factory()->create(['bakong_account_id' => 'seller'.$index.'@bank', 'bakong_merchant_name' => 'Seller '.$index, 'bakong_merchant_city' => 'Phnom Penh']));
        }
        $coupon = $this->coupon();
        $quote = $this->quote(['coupon_code' => $coupon->code]);
        $created = $this->checkout($quote, ['payment_method' => 'bakong'])->assertCreated();
        $order = Order::sole();
        $this->getJson('/api/checkout/'.$created->json('data.order_number').'/bakong')->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame((int) round($quote['total'] * 100), (int) round(BakongPayment::sum('amount') * 100));
        foreach (BakongPayment::with('shopOrder')->get() as $payment) {
            $this->assertSame($payment->shopOrder->total, $payment->amount);
            $this->assertStringContainsString(number_format((float) $payment->amount, 2, '.', ''), $payment->qr_payload);
        }
        $this->assertSame($order->total, $order->payment->amount);
    }
}
