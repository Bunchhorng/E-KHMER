<?php

namespace Tests\Feature\Api;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Consumer\CheckoutGuest;
use Tests\TestCase;

class CheckoutProtectionTest extends TestCase
{
    use CheckoutGuest, RefreshDatabase;

    private function ready(bool $guest = false, int $stock = 100): array
    {
        Notification::fake();
        if (! $guest) {
            $this->authAs(User::factory()->create());
        }
        $this->withHeader('X-Session-Id', 'f13-guest');
        $this->productWithStock($stock, 10);
        $this->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 1])->assertCreated();

        return ['shipping_method_id' => $this->seedShipping(), 'payment_method' => 'card',
            'email' => 'buyer@example.test', 'address' => $this->addressPayload()];
    }

    public function test_twenty_new_attempts_allow_three_holds_then_quota_then_rate_limit_without_side_effects(): void
    {
        $payload = $this->ready();
        $statuses = [];
        for ($i = 0; $i < 20; $i++) {
            $statuses[] = $this->postJson('/api/checkout', $payload, ['Idempotency-Key' => 'burst-key-'.$i])->status();
        }
        $this->assertSame([...array_fill(0, 3, 201), ...array_fill(0, 5, 409), ...array_fill(0, 12, 429)], $statuses);
        $this->assertDatabaseCount('orders', 3);
        $this->assertDatabaseCount('payments', 3);
        $this->assertDatabaseCount('checkout_attempts', 3);
        $this->assertDatabaseCount('inventory_transactions', 3);
        $this->assertSame(3, Inventory::sole()->reserved_quantity);
        $this->assertSame(100, Inventory::sole()->quantity);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_exact_retry_recovers_original_order_at_both_limits(bool $guest): void
    {
        $payload = $this->ready($guest);
        config(['ecommerce.checkout.owner_attempts' => 1, 'ecommerce.checkout.ip_attempts' => 1, 'ecommerce.checkout.active_limit' => 1]);
        $headers = ['Idempotency-Key' => 'exact-retry-key'];
        $first = $this->postJson('/api/checkout', $payload, $headers)->assertCreated();
        $this->postJson('/api/checkout', $payload, ['Idempotency-Key' => 'other-retry-key'])->assertTooManyRequests();
        $this->postJson('/api/checkout', $payload, $headers)->assertOk()
            ->assertJsonPath('data.order_number', $first->json('data.order_number'))
            ->assertJsonPath('reservation_expires_at', $first->json('reservation_expires_at'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, Inventory::sole()->reserved_quantity);
    }

    public function test_changed_or_invalid_payload_with_existing_key_cannot_bypass_rate_limit(): void
    {
        $payload = $this->ready();
        config(['ecommerce.checkout.owner_attempts' => 1]);
        $headers = ['Idempotency-Key' => 'changed-retry-key'];
        $this->postJson('/api/checkout', $payload, $headers)->assertCreated();
        $this->postJson('/api/checkout', [...$payload, 'note' => 'changed'], $headers)->assertTooManyRequests();
        $this->postJson('/api/checkout', ['address' => []], $headers)->assertTooManyRequests();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_malformed_requests_are_limited_before_validation_and_never_reserve_stock(): void
    {
        $this->ready(true);
        config(['ecommerce.checkout.owner_attempts' => 2]);
        $this->postJson('/api/checkout', [])->assertUnprocessable();
        $this->postJson('/api/checkout', [])->assertUnprocessable();
        $this->postJson('/api/checkout', [])->assertTooManyRequests()->assertJsonPath('code', 'CHECKOUT_RATE_LIMIT')->assertHeader('Retry-After');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(0, Inventory::sole()->reserved_quantity);
    }

    public function test_guest_cannot_use_a_missing_or_oversized_session_and_attempts_still_count(): void
    {
        $payload = $this->ready(true);
        config(['ecommerce.checkout.owner_attempts' => 1]);
        $this->withHeader('X-Session-Id', str_repeat('x', 256))->postJson('/api/checkout', $payload)->assertUnprocessable()->assertJsonValidationErrors('session');
        $this->withHeader('X-Session-Id', '')->postJson('/api/checkout', $payload)->assertTooManyRequests();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_ip_limit_catches_rotated_guest_sessions_but_leaves_cart_reads_and_quotes_available(): void
    {
        $payload = $this->ready(true);
        config(['ecommerce.checkout.ip_attempts' => 2]);
        foreach (['session-a', 'session-b'] as $session) {
            $this->withHeader('X-Session-Id', $session)->postJson('/api/checkout', [])->assertUnprocessable();
        }
        $this->withHeader('X-Session-Id', 'f13-guest')->postJson('/api/checkout', $payload)->assertTooManyRequests();
        $this->getJson('/api/cart')->assertOk()->assertJsonCount(1, 'data.items');
        $this->postJson('/api/checkout/quote')->assertOk();
        $this->assertDatabaseCount('orders', 0);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_distinct_owners_on_one_ip_have_separate_budgets_and_idempotency(bool $guest): void
    {
        $payload = $this->ready($guest);
        config(['ecommerce.checkout.owner_attempts' => 1]);
        $headers = ['Idempotency-Key' => 'shared-key-string'];
        $a = $this->postJson('/api/checkout', $payload, $headers)->assertCreated()->json('data.order_number');
        if ($guest) {
            $this->withHeader('X-Session-Id', 'other-guest');
        } else {
            $this->authAs(User::factory()->create());
        }
        $this->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 1])->assertCreated();
        $b = $this->postJson('/api/checkout', $payload, $headers)->assertCreated()->json('data.order_number');
        $this->assertNotSame($a, $b);
        $this->assertDatabaseCount('checkout_attempts', 2);
        $this->assertSame(2, Inventory::sole()->reserved_quantity);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_slow_new_checkouts_still_hit_active_limit_and_cart_changes_do_not_duplicate_old_hold(bool $guest): void
    {
        $payload = $this->ready($guest);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/checkout', $payload, ['Idempotency-Key' => 'slow-key-'.$i])->assertCreated();
            $this->travel(2)->minutes();
        }
        CartItem::sole()->update(['quantity' => 2]);
        $this->postJson('/api/checkout', $payload, ['Idempotency-Key' => 'changed-cart-key'])->assertStatus(409)->assertJsonPath('code', 'ACTIVE_CHECKOUT_LIMIT');
        $this->assertDatabaseCount('orders', 3);
        $this->assertSame(3, Inventory::sole()->reserved_quantity);
    }

    public function test_blocked_checkout_cannot_use_coupon_emit_low_stock_or_write_payment_or_ledger_rows(): void
    {
        $payload = $this->ready(stock: 10);
        User::factory()->admin()->create();
        config(['ecommerce.checkout.active_limit' => 1]);
        $this->postJson('/api/checkout', $payload)->assertCreated();
        $stock = Inventory::sole();
        $stock->update(['low_stock_threshold' => 8]); // A second hold would cross the low-stock threshold.
        $coupon = Coupon::factory()->create(['code' => 'F13-SAFE', 'type' => 'fixed', 'value' => 1]);
        Notification::fake();
        $this->postJson('/api/checkout', [...$payload, 'coupon_code' => $coupon->code], ['Idempotency-Key' => 'blocked-coupon-key'])
            ->assertStatus(409)->assertJsonPath('code', 'ACTIVE_CHECKOUT_LIMIT');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('coupon_usages', 0);
        $this->assertDatabaseCount('checkout_attempts', 0);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertSame(1, $stock->fresh()->reserved_quantity);
        $this->assertNull($stock->fresh()->low_stock_notified_at);
        Notification::assertNothingSent();
    }

    public function test_cancel_frees_quota_and_releases_stock_and_coupon_exactly_once(): void
    {
        $payload = $this->ready();
        config(['ecommerce.checkout.active_limit' => 1]);
        $coupon = Coupon::factory()->create(['code' => 'F13-CANCEL', 'type' => 'fixed', 'value' => 1]);
        $number = $this->postJson('/api/checkout', [...$payload, 'coupon_code' => $coupon->code])->assertCreated()->json('data.order_number');
        $this->postJson("/api/checkout/{$number}/cancel")->assertOk();
        $this->postJson("/api/checkout/{$number}/cancel")->assertOk();
        $this->assertSame(0, Inventory::sole()->reserved_quantity);
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->postJson('/api/checkout', $payload)->assertCreated();
    }

    public function test_expired_holds_do_not_count_and_existing_cleanup_is_idempotent_at_expiry_boundary(): void
    {
        $this->freezeTime();
        $payload = $this->ready();
        config(['ecommerce.checkout.active_limit' => 1]);
        $first = $this->postJson('/api/checkout', $payload)->assertCreated()->json('data.order_number');
        $this->travel(15)->minutes();
        $this->postJson("/api/checkout/{$first}/confirm")->assertUnprocessable()->assertJsonValidationErrors('payment');
        $this->postJson('/api/checkout', $payload)->assertCreated();
        $service = app(CheckoutService::class);
        $this->assertSame(1, $service->expireStaleReservations());
        $this->assertSame(0, $service->expireStaleReservations());
        $this->assertSame(1, Inventory::sole()->reserved_quantity);
        $this->assertDatabaseCount('inventory_transactions', 3);
    }

    #[TestWith(['card', 'paid'])]
    #[TestWith(['cod', 'unpaid'])]
    public function test_confirmed_orders_free_quota_including_uncollected_cod(string $method, string $paymentStatus): void
    {
        $payload = $this->ready();
        config(['ecommerce.checkout.active_limit' => 1, 'ecommerce.payment_mode' => 'sandbox']);
        for ($i = 0; $i < 4; $i++) {
            if ($i > 0) {
                $this->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 1])->assertCreated();
            }
            $number = $this->postJson('/api/checkout', [...$payload, 'payment_method' => $method])->assertCreated()->json('data.order_number');
            $this->postJson("/api/checkout/{$number}/confirm")->assertOk()->assertJsonPath('data.payment_status', $paymentStatus);
        }
        $this->assertSame(0, Inventory::sole()->reserved_quantity);
        $this->assertSame(96, Inventory::sole()->quantity);
    }

    #[TestWith(['failed'])]
    #[TestWith(['refunded'])]
    public function test_failed_or_refunded_parent_orders_are_excluded(string $status): void
    {
        $payload = $this->ready();
        config(['ecommerce.checkout.active_limit' => 1]);
        $this->postJson('/api/checkout', $payload)->assertCreated();
        Order::sole()->update(['payment_status' => $status]);
        $this->postJson('/api/checkout', $payload)->assertCreated();
    }

    public function test_multi_shop_and_five_line_checkout_uses_one_parent_quota_slot_and_reuses_khqr(): void
    {
        $payload = $this->ready();
        config(['ecommerce.checkout.active_limit' => 2, 'ecommerce.bakong_token' => 'test-only']);
        $shops = Shop::factory()->count(2)->create(['bakong_account_id' => 'seller@bank', 'bakong_merchant_name' => 'Seller', 'bakong_merchant_city' => 'Phnom Penh']);
        $this->product->update(['shop_id' => $shops[0]->id]);
        $this->product->variants()->update(['shop_id' => $shops[0]->id]);
        for ($i = 0; $i < 4; $i++) {
            $product = Product::factory()->withVariant(10, 100)->create(['shop_id' => $shops[$i % 2]->id]);
            $this->postJson('/api/cart', ['product_variant_id' => $product->variants()->sole()->id, 'quantity' => 1])->assertCreated();
        }
        $number = $this->postJson('/api/checkout', [...$payload, 'payment_method' => 'bakong'])->assertCreated()->json('data.order_number');
        $first = $this->getJson("/api/checkout/{$number}/bakong")->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->getJson("/api/checkout/{$number}/bakong")->assertOk()->assertJsonPath('data', $first);
        $this->assertDatabaseCount('bakong_payments', 2);
        $this->postJson('/api/checkout', $payload)->assertCreated();
        $this->postJson('/api/checkout', $payload)->assertStatus(409)->assertJsonPath('code', 'ACTIVE_CHECKOUT_LIMIT');
        $this->assertDatabaseCount('orders', 2);
        $this->assertSame(10, (int) Inventory::sum('reserved_quantity'));
    }

    #[TestWith(['address', []])]
    #[TestWith(['shipping_method_id', 999999])]
    #[TestWith(['coupon_code', 'DOES-NOT-EXIST'])]
    public function test_invalid_checkout_details_create_no_holds(string $field, mixed $value): void
    {
        $payload = $this->ready(true);
        $this->postJson('/api/checkout', [...$payload, $field => $value])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(0, Inventory::sole()->reserved_quantity);
    }

    public function test_large_quantity_is_rejected_by_authoritative_stock_validation(): void
    {
        $payload = $this->ready();
        CartItem::sole()->update(['quantity' => 1000000]);
        $this->postJson('/api/checkout', $payload)->assertUnprocessable();
        $this->assertSame(0, Inventory::sole()->reserved_quantity);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_reservation_failure_rolls_back_earlier_line_ledger_and_low_stock_marker(): void
    {
        $payload = $this->ready(stock: 6);
        $product = Product::factory()->withVariant(10, 6)->create();
        $secondId = $product->variants()->sole()->id;
        $this->postJson('/api/cart', ['product_variant_id' => $secondId, 'quantity' => 1])->assertCreated();
        $this->app->instance(InventoryService::class, new class($secondId) extends InventoryService
        {
            public function __construct(private int $failId) {}

            public function reserve(int $variantId, int $quantity): bool
            {
                return $variantId === $this->failId ? false : parent::reserve($variantId, $quantity);
            }
        });
        $this->postJson('/api/checkout', $payload, ['Idempotency-Key' => 'failed-reserve-key'])->assertUnprocessable();
        $this->assertSame(0, (int) Inventory::sum('reserved_quantity'));
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertDatabaseCount('checkout_attempts', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertNull(Inventory::where('product_variant_id', $this->variantId)->sole()->low_stock_notified_at);
        Notification::assertNothingSent();
    }

    public function test_unavailable_shared_limiter_rejects_new_checkout_without_mutating_stock(): void
    {
        $payload = $this->ready();
        Cache::shouldReceive('store')->once()->andReturnUsing(fn () => throw new \RuntimeException('private connection details'));
        $this->postJson('/api/checkout', $payload)->assertServiceUnavailable()->assertJsonPath('code', 'CHECKOUT_TEMPORARILY_UNAVAILABLE')->assertDontSee('private connection');
        $this->assertSame(0, Inventory::sole()->reserved_quantity);
        $this->assertDatabaseCount('orders', 0);
    }
}
