<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\BakongPayment;
use App\Models\CheckoutAttempt;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusNotification;
use App\Services\CheckoutService;
use App\Services\CodCollectionService;
use App\Services\ShopOrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private array $address = [
        'full_name' => 'Guest Buyer', 'phone' => '+855 12 345 678',
        'address_line1' => 'Street 1', 'city' => 'Phnom Penh', 'state' => 'Phnom Penh',
        'postal_code' => '12000', 'country' => 'KH',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Http::preventStrayRequests();
        config(['ecommerce.payment_mode' => 'production']);
        $this->withHeader('X-Session-Id', 'guest-checkout-owner');
    }

    private function cart(int $shops = 1): array
    {
        for ($i = 0; $i < $shops; $i++) {
            $shop = Shop::factory()->create(['bakong_account_id' => "seller{$i}@bank", 'bakong_merchant_name' => "Seller {$i}", 'bakong_merchant_city' => 'Phnom Penh']);
            $product = Product::factory()->withVariant(100, 10)->create(['shop_id' => $shop->id]);
            ShippingMethod::factory()->create(['shop_id' => $shop->id, 'price' => 5]);
            $this->postJson('/api/cart', ['product_variant_id' => $product->variants()->sole()->id, 'quantity' => 1])->assertCreated();
        }

        $quote = $this->postJson('/api/checkout/quote')->assertOk()->json('data');

        return ['payment_method' => 'cod', 'email' => 'guest@example.test', 'address' => $this->address,
            'shipping_methods' => array_column($quote['shipping_methods'], 'id', 'shop_id'), 'quote_fingerprint' => $quote['fingerprint']];
    }

    private function order(array $payload): Order
    {
        $number = $this->postJson('/api/checkout', $payload)->assertCreated()->json('data.order_number');

        return Order::where('order_number', $number)->sole();
    }

    public function test_guest_cod_snapshots_contact_and_address_without_creating_an_account_or_saved_address(): void
    {
        $payload = $this->cart();
        User::factory()->create(['email' => $payload['email']]);
        $order = $this->order($payload);
        $this->assertNull($order->user_id);
        $this->assertSame($payload['email'], $order->email);
        $this->assertSame($this->address['full_name'], $order->customer_name);
        $this->assertSame($this->address['phone'], $order->phone);
        foreach ($this->address as $key => $value) {
            $this->assertSame($value, $order->shipping_address[$key]);
            $this->assertSame($value, $order->shipments()->sole()->address_snapshot[$key]);
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('addresses', 0);
        $inventory = $order->items()->sole()->variant->inventory;
        $this->assertEquals(1, $inventory->reserved_quantity);
        $this->postJson("/api/checkout/{$order->order_number}/confirm")->assertOk()->assertJsonPath('data.payment_status', 'unpaid')->assertJsonPath('data.payment.status', 'pending');
        $this->assertEquals(0, $inventory->fresh()->reserved_quantity);
        $this->assertEquals(9, $inventory->fresh()->quantity);
        $this->getJson('/api/cart')->assertOk()->assertJsonCount(0, 'data.items');
        Notification::assertSentOnDemand(OrderPlacedNotification::class, fn ($notification, $channels, $recipient) => $channels === ['mail'] && array_key_exists($payload['email'], $recipient->routes['mail']));
    }

    public function test_guest_validation_rejects_missing_contact_and_address_before_reserving_stock(): void
    {
        // This validation matrix intentionally submits more malformed requests
        // than the production burst budget; limiter behavior has its own tests.
        config(['ecommerce.checkout.owner_attempts' => 20]);
        $payload = $this->cart();
        foreach (['', 'not-an-email'] as $email) {
            $this->postJson('/api/checkout', array_replace($payload, ['email' => $email]))->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->postJson('/api/checkout', array_replace($payload, ['address' => null]))->assertUnprocessable()->assertJsonValidationErrors('address');
        foreach (['full_name', 'phone', 'address_line1', 'city', 'state', 'postal_code', 'country'] as $field) {
            $invalid = $payload;
            unset($invalid['address'][$field]);
            $this->postJson('/api/checkout', $invalid)->assertUnprocessable()->assertJsonValidationErrors("address.{$field}");
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('inventories', ['reserved_quantity' => 0]);
    }

    public function test_guest_cannot_bypass_inline_address_validation_with_any_saved_address_id(): void
    {
        $payload = $this->cart();
        $address = Address::factory()->create();
        unset($payload['address']);
        $this->postJson('/api/checkout', [...$payload, 'address_id' => $address->id])->assertUnprocessable()->assertJsonValidationErrors(['address_id', 'address']);
        $this->postJson('/api/checkout', [...$payload, 'address_id' => $address->id, 'address' => $this->address])->assertUnprocessable()->assertJsonValidationErrors('address_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_guest_multishop_quote_and_coupon_totals_match_order_allocations_and_payments(): void
    {
        $payload = $this->cart(2);
        $shop = Shop::firstOrFail();
        Coupon::factory()->create(['code' => 'GUESTCAP', 'shop_id' => $shop->id, 'per_user_limit' => null, 'type' => 'percentage', 'value' => 50, 'max_discount_amount' => 5]);
        $quote = $this->postJson('/api/checkout/quote', ['coupon_code' => 'GUESTCAP', 'shipping_methods' => $payload['shipping_methods']])->assertOk()->assertJsonPath('data.discount_amount', 5)->json('data');
        $order = $this->order([...$payload, 'coupon_code' => 'GUESTCAP', 'quote_fingerprint' => $quote['fingerprint']]);
        $this->assertCount(2, $order->shopOrders);
        $this->assertEquals(224.5, $order->total);
        $this->assertEquals($quote['total'], $order->payment->amount);
        $this->assertEquals($order->total, $order->shopOrders->sum('total'));
        $this->assertEquals(5, $order->shopOrders->where('shop_id', $shop->id)->sole()->discount_amount);
        $this->assertEquals(0, $order->shopOrders->where('shop_id', '!=', $shop->id)->sole()->discount_amount);
        $this->assertEquals(1, $order->coupon->used_count);
    }

    public function test_guest_cannot_bypass_coupon_per_customer_limits_in_quote_or_order_creation(): void
    {
        $payload = $this->cart();
        Coupon::factory()->create(['code' => 'MEMBERONLY', 'per_user_limit' => 1]);
        $this->postJson('/api/checkout/quote', ['coupon_code' => 'MEMBERONLY'])->assertUnprocessable()->assertJsonPath('errors.code.0', 'Sign in to use this coupon. It has a per-customer usage limit.');
        $this->postJson('/api/checkout', [...$payload, 'coupon_code' => 'MEMBERONLY'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_guest_retry_is_owner_scoped_and_reserves_only_once_even_after_confirmation(): void
    {
        $payload = $this->cart();
        $headers = ['Idempotency-Key' => 'guest-idempotency-123'];
        $number = $this->postJson('/api/checkout', $payload, $headers)->assertCreated()->json('data.order_number');
        $this->postJson('/api/checkout', $payload, $headers)->assertOk()->assertJsonPath('data.order_number', $number);
        $this->postJson('/api/checkout', [...$payload, 'email' => 'changed@example.test'], $headers)->assertUnprocessable()->assertJsonValidationErrors('idempotency');
        $this->postJson("/api/checkout/{$number}/confirm")->assertOk();
        $this->postJson('/api/checkout', $payload, $headers)->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->withHeader('X-Session-Id', 'other-guest')->postJson('/api/checkout', $payload, $headers)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('checkout_attempts', 1);
    }

    public function test_guest_order_and_payment_access_require_the_original_session(): void
    {
        $order = $this->order($this->cart());
        $number = $order->order_number;
        $this->getJson("/api/orders/guest/{$number}")->assertOk()->assertJsonMissingPath('data.id');
        $this->getJson("/api/orders/{$number}")->assertUnauthorized();
        $this->withHeader('X-Session-Id', 'different-guest');
        $this->getJson("/api/orders/guest/{$number}")->assertNotFound();
        $this->getJson("/api/orders/guest/{$order->id}")->assertNotFound();
        $this->postJson("/api/checkout/{$number}/confirm")->assertNotFound();
        $this->postJson("/api/checkout/{$number}/cancel")->assertNotFound();
        $this->getJson("/api/checkout/{$number}/bakong")->assertNotFound();
        $this->postJson("/api/checkout/{$number}/bakong/verify")->assertNotFound();
        $this->actingAs(User::factory()->create(['email' => $order->email]), 'sanctum');
        $this->getJson("/api/orders/{$number}")->assertNotFound();
        $this->postJson("/api/checkout/{$number}/confirm")->assertNotFound();
    }

    public function test_authenticated_checkout_keeps_saved_address_ownership_and_snapshot_isolation(): void
    {
        $payload = $this->cart();
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $otherAddress = Address::factory()->create();
        $this->actingAs($buyer, 'sanctum');
        $product = Product::firstOrFail();
        $this->postJson('/api/cart', ['product_variant_id' => $product->variants()->sole()->id, 'quantity' => 1])->assertCreated();
        unset($payload['address'], $payload['quote_fingerprint']);
        $this->postJson('/api/checkout', [...$payload, 'address_id' => $otherAddress->id])->assertNotFound();
        $order = $this->order([...$payload, 'address_id' => $address->id]);
        $this->assertSame($buyer->id, $order->user_id);
        $snapshot = $order->shipping_address;
        $address->update(['address_line1' => 'Changed saved address']);
        $this->assertSame($snapshot, $order->fresh()->shipping_address);
    }

    public function test_guest_coupon_rules_and_delivery_ownership_are_revalidated_without_order_side_effects(): void
    {
        $payload = $this->cart();
        foreach ([['expires_at' => now()->subMinute()], ['usage_limit' => 1, 'used_count' => 1], ['min_order_amount' => 101], ['shop_id' => Shop::factory()->create()->id]] as $overrides) {
            $coupon = Coupon::factory()->create([...$overrides, 'per_user_limit' => null]);
            $this->postJson('/api/checkout', [...$payload, 'coupon_code' => $coupon->code])->assertUnprocessable()->assertJsonValidationErrors('code');
        }
        $wrongMethod = ShippingMethod::factory()->create(['shop_id' => Shop::factory()->create()->id]);
        $this->postJson('/api/checkout', [...$payload, 'shipping_methods' => [Shop::firstOrFail()->id => $wrongMethod->id]])->assertUnprocessable()->assertJsonValidationErrors('shipping_methods');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('coupon_usages', 0);
        $this->assertDatabaseHas('inventories', ['reserved_quantity' => 0]);
    }

    public function test_registration_merges_the_multishop_guest_cart_once_and_keeps_items_ready_for_checkout(): void
    {
        $this->cart(2);
        $response = $this->postJson('/api/auth/register', [
            'name' => 'New Buyer', 'email' => 'newbuyer@example.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated();
        $this->withToken($response->json('data.token'))->getJson('/api/cart')->assertOk()->assertJsonCount(2, 'data.items');
        $this->getJson('/api/cart')->assertOk()->assertJsonCount(2, 'data.items');
        $this->assertDatabaseCount('cart_items', 2);
        $this->postJson('/api/checkout/quote')->assertOk()->assertJsonPath('data.can_checkout', true)->assertJsonCount(2, 'data.shipping_methods');
    }

    public function test_admin_explicitly_configures_guest_coupon_eligibility_and_preserves_account_limits(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $payload = ['code' => 'GUESTPUBLIC', 'type' => 'percentage', 'value' => 10, 'per_user_limit' => null];
        $couponId = $this->postJson('/api/admin/coupons', $payload)->assertCreated()->assertJsonPath('data.per_user_limit', null)->json('data.id');
        $this->assertNull(Coupon::findOrFail($couponId)->per_user_limit);
        $this->putJson("/api/admin/coupons/{$couponId}", [...$payload, 'per_user_limit' => 2])->assertOk()->assertJsonPath('data.per_user_limit', 2);
        $this->assertEquals(2, Coupon::findOrFail($couponId)->per_user_limit);
    }

    public function test_expired_or_missing_guest_session_cannot_create_empty_orders_or_attempts(): void
    {
        $payload = $this->cart();
        $this->withHeader('X-Session-Id', 'expired-session')->postJson('/api/checkout', $payload)->assertUnprocessable();
        $this->withHeader('X-Session-Id', '')->postJson('/api/checkout', $payload, ['Idempotency-Key' => 'missing-session-key'])->assertUnprocessable()->assertJsonValidationErrors('session');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, CheckoutAttempt::count());
    }

    public function test_guest_bakong_is_session_protected_and_requires_verified_bank_payment(): void
    {
        config(['ecommerce.bakong_token' => 'test-only']);
        $order = $this->order([...$this->cart(2), 'payment_method' => 'bakong']);
        $this->getJson("/api/checkout/{$order->order_number}/bakong")->assertOk()->assertJsonCount(2, 'data');
        $this->postJson("/api/checkout/{$order->order_number}/confirm")->assertUnprocessable();
        Http::fake(function ($request) {
            $payment = BakongPayment::where('md5', $request['md5'])->sole();

            return Http::response(['responseCode' => 0, 'data' => [
                'hash' => hash('sha256', 'guest-payment-'.$payment->id), 'fromAccountId' => 'buyer@bank',
                'toAccountId' => $payment->receiver_account, 'currency' => $payment->currency, 'amount' => (float) $payment->amount,
            ]]);
        });
        $this->postJson("/api/checkout/{$order->order_number}/bakong/verify")->assertOk()->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.status', 'confirmed');
        $this->postJson("/api/checkout/{$order->order_number}/bakong/verify")->assertOk();
        $this->assertDatabaseCount('payment_transactions', 1);
        foreach ($order->items as $item) {
            $this->assertEquals(9, $item->variant->inventory->quantity);
        }
    }

    public function test_guest_bakong_has_a_clear_error_when_not_configured(): void
    {
        config(['ecommerce.bakong_token' => null]);
        $this->postJson('/api/checkout', [...$this->cart(), 'payment_method' => 'bakong'])->assertUnprocessable()->assertJsonPath('errors.payment.0', 'Bakong is not configured yet. Choose cash on delivery.');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_guest_cod_remains_unpaid_until_seller_collection_after_delivery(): void
    {
        $order = $this->order($this->cart());
        $this->postJson("/api/checkout/{$order->order_number}/confirm")->assertOk();
        $allocation = $order->shopOrders()->sole();
        $seller = User::factory()->create();
        $allocation->shop->users()->attach($seller, ['role_in_shop' => 'owner', 'status' => 'active']);
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            app(ShopOrderFulfillmentService::class)->transition($allocation, $status, $seller->id, null, ['carrier' => 'Local', 'tracking_number' => 'GUEST-TRACK']);
        }
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        app(CodCollectionService::class)->collect($allocation, $seller);
        $this->assertSame('paid', $order->fresh()->payment_status);
        Notification::assertSentOnDemand(OrderStatusNotification::class);
    }

    public function test_guest_reservation_expiry_releases_stock_and_keeps_address_snapshot(): void
    {
        $order = $this->order($this->cart());
        $this->travel(16)->minutes();
        app(CheckoutService::class)->expireStaleReservations();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame($this->address['address_line1'], $order->fresh()->shipping_address['address_line1']);
        $this->assertEquals(0, $order->items()->sole()->variant->inventory->reserved_quantity);
    }

    public function test_guest_mail_uses_mail_only_and_the_public_session_protected_tracking_page(): void
    {
        $order = $this->order($this->cart());
        $recipient = new AnonymousNotifiable;
        foreach ([new OrderPlacedNotification($order), new OrderStatusNotification($order, 'shipped')] as $notification) {
            $this->assertSame(['mail'], $notification->via($recipient));
            $mail = $notification->toMail($recipient);
            $this->assertStringEndsWith('/order/tracking/'.$order->order_number, $mail->actionUrl);
            $this->assertStringContainsString($order->customer_name, $mail->greeting);
        }
        Setting::set('emailOrderNotifications', '0');
        $this->assertSame([], (new OrderPlacedNotification($order))->via($recipient));
        $this->assertSame([], (new OrderStatusNotification($order, 'shipped'))->via($recipient));
    }
}
