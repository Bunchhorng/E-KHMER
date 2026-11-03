<?php

namespace Tests\Feature\Api;

use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Services\OrderNumberGenerator;
use App\Services\OrderNumberService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery;
use PDOException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Consumer\CheckoutGuest;
use Tests\TestCase;

class OrderNumberCollisionTest extends TestCase
{
    use CheckoutGuest, RefreshDatabase;

    private function ready(bool $guest = false, int $shops = 1): array
    {
        Notification::fake();
        $user = $guest ? null : User::factory()->create();
        if ($user) {
            $this->authAs($user);
        }
        $this->withHeader('X-Session-Id', 'f14-guest');
        for ($i = 0; $i < $shops; $i++) {
            $product = Product::factory()->withVariant(100, 10)->create(['shop_id' => Shop::factory()->create()->id]);
            $this->postJson('/api/cart', ['product_variant_id' => $product->variants()->sole()->id, 'quantity' => 2])->assertCreated();
        }
        $coupon = Coupon::factory()->create(['type' => 'fixed', 'value' => 10, 'min_order_amount' => 0, 'per_user_limit' => null]);
        $payload = ['shipping_method_id' => $this->seedShipping(), 'payment_method' => 'cod',
            'coupon_code' => $coupon->code, 'email' => 'buyer@example.test', 'address' => $this->addressPayload()];

        return [$payload, $coupon, $user];
    }

    private function assertSuccessfulCheckout(Coupon $coupon, int $shops = 1): void
    {
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_transactions', 0);
        $this->assertDatabaseCount('bakong_payments', 0);
        $this->assertDatabaseCount('order_items', $shops);
        $this->assertDatabaseCount('shop_orders', $shops);
        $this->assertDatabaseCount('shipments', $shops);
        $this->assertDatabaseCount('tracking_events', $shops + 1);
        $this->assertDatabaseCount('inventory_transactions', $shops);
        $this->assertDatabaseCount('coupon_usages', 1);
        $this->assertSame(1, $coupon->fresh()->used_count);
        foreach (Inventory::all() as $stock) {
            $this->assertSame(10, $stock->quantity);
            $this->assertSame(2, $stock->reserved_quantity);
        }
    }

    private function assertRolledBack(Coupon $coupon, int $existingShops = 0, int $shops = 1): void
    {
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('shop_orders', $existingShops);
        foreach (['payments', 'payment_transactions', 'bakong_payments', 'order_items', 'shipments',
            'tracking_events', 'inventory_transactions', 'coupon_usages', 'checkout_attempts'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('cart_items', $shops);
        $this->assertSame(0, $coupon->fresh()->used_count);
        foreach (Inventory::all() as $stock) {
            $this->assertSame(10, $stock->quantity);
            $this->assertSame(0, $stock->reserved_quantity);
        }
        Notification::assertNothingSent();
    }

    public function test_normal_checkout_persists_only_a_server_generated_number(): void
    {
        [$payload, $coupon] = $this->ready();

        $response = $this->postJson('/api/checkout', [...$payload, 'order_number' => 'CLIENT-CHOSEN'])->assertCreated();

        $this->assertMatchesRegularExpression('/^SV-[0-9]{4}-[0-9]{12}$/', $response->json('data.order_number'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSuccessfulCheckout($coupon);
        Notification::assertNothingSent();
    }

    #[TestWith([1, false])]
    #[TestWith([2, false])]
    #[TestWith([4, false])]
    #[TestWith([1, true])]
    public function test_collisions_including_soft_deleted_numbers_recover_without_duplicate_side_effects(int $collisions, bool $deleted): void
    {
        [$payload, $coupon, $user] = $this->ready();
        $existing = Order::factory()->create(['order_number' => 'SV-2031-000000000001']);
        if ($deleted) {
            $existing->delete();
        }
        $final = 'SV-2031-000000000002';
        $this->partialMock(OrderNumberGenerator::class)->shouldReceive('next')->times($collisions + 1)
            ->andReturn(...[...array_fill(0, $collisions, $existing->order_number), $final]);
        config(['ecommerce.checkout.owner_attempts' => 1, 'ecommerce.checkout.ip_attempts' => 1, 'ecommerce.checkout.active_limit' => 1]);
        $headers = ['Idempotency-Key' => 'f14-idempotent-retry'];

        $first = $this->postJson('/api/checkout', $payload, $headers)->assertCreated()->assertJsonPath('data.order_number', $final);
        $this->postJson('/api/checkout', $payload, $headers)->assertOk()
            ->assertJsonPath('data.order_number', $final)->assertJsonPath('reservation_expires_at', $first->json('reservation_expires_at'));

        $this->assertSame(2, Order::withTrashed()->count());
        $this->assertDatabaseCount('checkout_attempts', 1);
        $this->assertSuccessfulCheckout($coupon);
        Notification::assertNothingSent();
        $this->getJson('/api/orders/'.$final)->assertOk()->assertJsonPath('data.order_number', $final);
        $this->get('/api/orders/'.$final.'/receipt')->assertOk()->assertDownload('receipt-'.$final.'.pdf');
        $this->postJson('/api/checkout/'.$final.'/confirm')->assertOk()->assertJsonPath('data.order_number', $final);
        $this->postJson('/api/checkout', $payload, $headers)->assertOk()->assertJsonPath('data.order_number', $final);
        Notification::assertSentToTimes($user, OrderPlacedNotification::class, 1);
    }

    #[TestWith([false, false])]
    #[TestWith([true, false])]
    #[TestWith([false, true])]
    #[TestWith([false, false, false])]
    public function test_exhaustion_returns_503_and_rolls_back_checkout_and_idempotency(bool $guest, bool $production, bool $keyed = true): void
    {
        [$payload, $coupon] = $this->ready($guest);
        $existing = Order::factory()->create();
        $this->partialMock(OrderNumberGenerator::class)->shouldReceive('next')->times(5)->andReturn($existing->order_number);
        Log::spy();
        if ($production) {
            $this->app->detectEnvironment(fn () => 'production');
            config(['app.debug' => true]);
        }

        $this->postJson('/api/checkout', $payload, $keyed ? ['Idempotency-Key' => 'f14-exhaustion-key'] : [])->assertStatus(503)
            ->assertExactJson(['code' => 'ORDER_CREATION_UNAVAILABLE', 'message' => "We couldn't create your order. Please try again."]);

        $this->assertRolledBack($coupon);
        Log::shouldHaveReceived('info')->with('order_number_collision', Mockery::type('array'))->times(5);
        Log::shouldHaveReceived('error')->once()->with('order_number_retry_exhausted', Mockery::on(fn (array $context) => $context['attempt_count'] === 5 && $context['generator_strategy'] === OrderNumberGenerator::STRATEGY
            && Str::isUuid($context['checkout_reference'])
            && $context['idempotency_reference'] === ($keyed ? hash('sha256', 'f14-exhaustion-key') : null) && ! isset($context['email'])));
    }

    public function test_parent_collision_preserves_multiple_shop_allocations_and_totals(): void
    {
        [$payload, $coupon] = $this->ready(shops: 3);
        $existing = Order::factory()->create();
        $final = 'SV-2031-000000000003';
        $this->partialMock(OrderNumberGenerator::class)->shouldReceive('next')->twice()->andReturn($existing->order_number, $final);

        $this->postJson('/api/checkout', $payload)->assertCreated()->assertJsonPath('data.order_number', $final);

        $order = Order::where('order_number', $final)->sole();
        $this->assertSame([$final.'-1', $final.'-2', $final.'-3'], $order->shopOrders()->orderBy('id')->pluck('shop_order_number')->all());
        foreach (['subtotal', 'discount_amount', 'tax_amount', 'shipping_amount', 'total'] as $field) {
            $this->assertSame((int) round((float) $order->{$field} * 100), (int) round((float) $order->shopOrders()->sum($field) * 100));
        }
        foreach ($order->items as $item) {
            $this->assertDatabaseHas('shop_orders', ['id' => $item->shop_order_id, 'order_id' => $order->id, 'shop_id' => $item->shop_id]);
        }
        $this->assertSuccessfulCheckout($coupon, 3);
        Notification::assertNothingSent();
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_child_number_conflict_recovers_or_rolls_back_the_entire_checkout(bool $exhaust): void
    {
        [$payload, $coupon] = $this->ready(shops: $exhaust ? 2 : 1);
        $existing = Order::factory()->create();
        $final = 'SV-2031-000000000004';
        $child = ShopOrder::create(['order_id' => $existing->id, 'shop_id' => Shop::factory()->create()->id,
            'shop_order_number' => $final.'-'.($exhaust ? 2 : 1)]);
        $generator = $this->partialMock(OrderNumberGenerator::class);
        $generator->shouldReceive('next')->once()->andReturn($final);
        if ($exhaust) {
            $generator->shouldReceive('forShop')->with($final, 2, Mockery::type('int'))->times(5)->andReturn($child->shop_order_number);
        }

        $response = $this->postJson('/api/checkout', $payload, ['Idempotency-Key' => 'f14-child-conflict']);

        if ($exhaust) {
            $response->assertStatus(503)->assertJsonPath('code', 'ORDER_CREATION_UNAVAILABLE');
            $this->assertRolledBack($coupon, 1, 2);
        } else {
            $response->assertCreated()->assertJsonPath('data.order_number', $final);
            $order = Order::where('order_number', $final)->sole();
            $this->assertMatchesRegularExpression('/^SV-2031-000000000004-1-[0-9]{12}$/', $order->shopOrders()->sole()->shop_order_number);
            $this->assertDatabaseCount('shop_orders', 2);
            $this->assertDatabaseCount('payments', 1);
            $this->assertDatabaseCount('inventory_transactions', 1);
            $this->assertSame(2, Inventory::sole()->reserved_quantity);
            $this->assertSame(1, $coupon->fresh()->used_count);
            Notification::assertNothingSent();
        }
    }

    public function test_guest_collision_recovery_replays_the_same_number_and_keeps_session_ownership(): void
    {
        [$payload, $coupon] = $this->ready(guest: true);
        $existing = Order::factory()->create();
        $final = 'SV-2031-000000000006';
        $this->partialMock(OrderNumberGenerator::class)->shouldReceive('next')->twice()->andReturn($existing->order_number, $final);
        config(['ecommerce.checkout.owner_attempts' => 1, 'ecommerce.checkout.active_limit' => 1]);
        $headers = ['Idempotency-Key' => 'f14-guest-retry'];

        $this->postJson('/api/checkout', $payload, $headers)->assertCreated()->assertJsonPath('data.order_number', $final);
        $this->postJson('/api/checkout', $payload, $headers)->assertOk()->assertJsonPath('data.order_number', $final);

        $this->assertSuccessfulCheckout($coupon);
        $this->getJson('/api/orders/guest/'.$final)->assertOk()->assertJsonPath('data.order_number', $final);
        $this->withHeader('X-Session-Id', 'another-guest')->getJson('/api/orders/guest/'.$final)->assertNotFound();
        Notification::assertNothingSent();
    }

    #[TestWith(['foreign_key', 1452])]
    #[TestWith(['disconnected', 2006])]
    #[TestWith(['deadlock', 1213])]
    #[TestWith(['invalid_column', 1054])]
    #[TestWith(['truncation', 1406])]
    #[TestWith(['other_unique', 1062])]
    public function test_unrelated_database_errors_are_reported_without_number_retries(string $type, int $code): void
    {
        [$payload] = $this->ready();
        $driverError = new PDOException('Forced unrelated '.$type);
        $driverError->errorInfo = ['23000', $code, $driverError->getMessage()];
        $failure = $type === 'other_unique'
            ? (new UniqueConstraintViolationException(DB::getDefaultConnection(), 'insert into orders', [], $driverError))->setIndex('orders_external_reference_unique')
            : new QueryException(DB::getDefaultConnection(), 'insert into orders', [], $driverError);
        $this->partialMock(OrderNumberGenerator::class)->shouldReceive('next')->once()->andReturn('SV-2031-000000000005');
        DB::connection()->beforeExecuting(function (string $sql) use ($failure): void {
            if (preg_match('/^insert into [`"]orders[`"]/', $sql)) {
                throw $failure;
            }
        });
        Exceptions::fake();

        $this->postJson('/api/checkout', $payload)->assertStatus(500);

        Exceptions::assertReported($failure::class);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(0, Inventory::sole()->reserved_quantity);
    }

    public function test_duplicate_parent_shop_pair_is_not_treated_as_a_number_collision(): void
    {
        $parent = Order::factory()->create();
        $shop = Shop::factory()->create();
        ShopOrder::create(['order_id' => $parent->id, 'shop_id' => $shop->id, 'shop_order_number' => 'EXISTING-CHILD']);
        $this->partialMock(OrderNumberGenerator::class)->shouldReceive('forShop')->once()->andReturn('NEW-CHILD');

        $this->expectException(UniqueConstraintViolationException::class);

        app(OrderNumberService::class)->createShop($parent, 1, ['shop_id' => $shop->id]);
    }
}
