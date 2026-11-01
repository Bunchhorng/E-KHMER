<?php

namespace Tests\Feature\Api;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\Shop;
use App\Models\User;
use App\Services\Analytics\FinancialMetricService;
use App\Services\CheckoutService;
use App\Services\CodCollectionService;
use App\Services\OrderService;
use App\Services\ShopOrderFulfillmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CodCollectionTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $seller;

    private function checkout(int $shops = 1, ?string $couponCode = null): Order
    {
        Notification::fake();
        Http::preventStrayRequests();
        $this->buyer = User::factory()->create();
        $this->seller = User::factory()->create();
        $this->actingAs($this->buyer, 'sanctum');
        for ($i = 0; $i < $shops; $i++) {
            $shop = Shop::factory()->create();
            $shop->users()->attach($this->seller, ['role_in_shop' => 'owner', 'status' => 'active']);
            $product = Product::factory()->withVariant($i === 0 ? 40 : 60, 10)->create(['shop_id' => $shop->id]);
            ShippingMethod::factory()->create(['shop_id' => $shop->id, 'price' => 0]);
            $this->postJson('/api/cart', ['product_variant_id' => $product->variants()->sole()->id, 'quantity' => 1])->assertCreated();
        }
        $quote = $this->postJson('/api/checkout/quote', ['coupon_code' => $couponCode])->assertOk()->json('data');
        $number = $this->postJson('/api/checkout', [
            'shipping_methods' => array_column($quote['shipping_methods'], 'id', 'shop_id'),
            'quote_fingerprint' => $quote['fingerprint'], 'payment_method' => 'cod',
            'coupon_code' => $couponCode,
            'address' => ['full_name' => 'Buyer', 'address_line1' => 'Street 1', 'city' => 'PP', 'state' => 'PP', 'postal_code' => '12000'],
        ])->assertCreated()->assertJsonPath('data.payment.status', 'pending')->assertJsonPath('data.payment_status', 'unpaid')->json('data.order_number');
        $this->postJson("/api/checkout/{$number}/confirm")->assertOk()->assertJsonPath('data.payment.status', 'pending')->assertJsonPath('data.payment_status', 'unpaid');

        return Order::where('order_number', $number)->with(['payment', 'shopOrders'])->sole();
    }

    private function deliver(Order $order, int $index = 0): void
    {
        $allocation = $order->shopOrders[$index];
        $service = app(ShopOrderFulfillmentService::class);
        foreach (['processing', 'shipped', 'delivered'] as $stage) {
            $service->transition($allocation, $stage, $this->seller->id, null, ['carrier' => 'Local', 'tracking_number' => 'TRACK-'.$allocation->id]);
        }
    }

    private function url(Order $order, int $index = 0): string
    {
        $allocation = $order->shopOrders[$index];

        return "/api/seller/shops/{$allocation->shop_id}/payments/{$allocation->id}/collect-cod";
    }

    public function test_checkout_and_delivery_do_not_claim_cash_was_received(): void
    {
        $order = $this->checkout();
        $this->assertDatabaseCount('payment_transactions', 0);
        $this->deliver($order);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->payment->fresh()->status);
        $this->assertNull($order->payment->fresh()->paid_at);
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_seller_collects_saved_amount_once_with_actor_and_separate_timestamp(): void
    {
        $order = $this->checkout();
        $created = $order->payment->created_at;
        $this->deliver($order);
        $this->travel(2)->hours();
        $this->actingAs($this->seller, 'sanctum')->postJson($this->url($order), ['amount' => 1, 'created_by' => $this->buyer->id])
            ->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.amount', 44)->assertJsonPath('data.collected_by', $this->seller->name)->assertJsonPath('data.can_collect', false);
        $transaction = PaymentTransaction::sole();
        $this->assertEquals(44, $transaction->amount);
        $this->assertSame($this->seller->id, $transaction->meta['created_by']);
        $this->assertSame('cash_on_delivery', $transaction->meta['provider']);
        $this->assertSame('COD-'.$order->payment->id.'-'.$order->shopOrders[0]->id, $transaction->reference);
        $this->assertTrue($order->payment->fresh()->created_at->equalTo($created));
        $this->assertTrue($order->payment->fresh()->paid_at->greaterThan($created));
        $this->assertSame('completed', $order->payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        // Simulate a committed request whose response was lost, then a retry with stale models.
        app(CodCollectionService::class)->collect($order->shopOrders[0], $this->seller);
        $this->postJson($this->url($order))->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertDatabaseCount('payment_transactions', 1);
    }

    public function test_shop_allocations_and_parent_aggregate_without_paying_another_shop(): void
    {
        $order = $this->checkout(2);
        $this->deliver($order, 0);
        $this->actingAs($this->seller, 'sanctum')->postJson($this->url($order))->assertOk();
        $this->assertSame('partial', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->payment->fresh()->status);
        $this->assertSame('paid', $order->shopOrders[0]->fresh()->codPaymentStatus());
        $this->assertSame('unpaid', $order->shopOrders[1]->fresh()->codPaymentStatus());
        $this->postJson($this->url($order))->assertOk();
        $this->deliver($order, 1);
        $this->postJson($this->url($order, 1))->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('completed', $order->payment->fresh()->status);
        $this->assertEquals(110, PaymentTransaction::sum('amount'));
        $this->assertEquals($order->total, PaymentTransaction::sum('amount'));
        $this->assertDatabaseCount('payment_transactions', 2);
    }

    public static function methods(): array
    {
        return [['bakong'], ['card'], ['paypal']];
    }

    #[DataProvider('methods')]
    public function test_online_methods_cannot_use_cash_collection(string $method): void
    {
        $order = $this->checkout();
        $this->deliver($order);
        $order->payment->update(['method' => $method]);
        $this->actingAs($this->seller, 'sanctum')->postJson($this->url($order))->assertUnprocessable()->assertJsonValidationErrors('payment');
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_customer_wrong_shop_staff_and_suspended_manager_are_denied(): void
    {
        $order = $this->checkout(2);
        $this->deliver($order);
        $this->actingAs($this->buyer, 'sanctum')->postJson($this->url($order))->assertForbidden();
        $allocation = $order->shopOrders[0];
        $other = User::factory()->create();
        $other->shops()->attach($order->shopOrders[1]->shop_id, ['role_in_shop' => 'owner', 'status' => 'active']);
        $this->actingAs($other, 'sanctum')->postJson($this->url($order))->assertForbidden();
        $this->postJson("/api/seller/shops/{$order->shopOrders[1]->shop_id}/payments/{$allocation->id}/collect-cod")->assertNotFound();
        foreach ([['staff', 'active'], ['manager', 'suspended']] as [$role, $status]) {
            $this->buyer->shops()->sync([$allocation->shop_id => ['role_in_shop' => $role, 'status' => $status]]);
            $this->actingAs($this->buyer, 'sanctum')->postJson($this->url($order))->assertForbidden();
        }
        $this->postJson("/api/admin/payments/{$order->payment->id}/shop-orders/{$allocation->id}/collect-cod")->assertForbidden();
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_active_manager_and_super_admin_can_collect_and_admin_is_audited(): void
    {
        $order = $this->checkout(2);
        $this->deliver($order);
        $this->deliver($order, 1);
        $allocation = $order->shopOrders[0];
        $this->buyer->shops()->attach($allocation->shop_id, ['role_in_shop' => 'manager', 'status' => 'active']);
        $this->actingAs($this->buyer, 'sanctum')->postJson($this->url($order))->assertOk();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/payments/{$order->payment->id}/shop-orders/{$order->shopOrders[1]->id}/collect-cod")
            ->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.cod_allocations.1.collected_by', $admin->name);
        $this->assertSame('super_admin', PaymentTransaction::latest('id')->first()->meta['actor_role']);
    }

    public function test_collection_requires_delivery_and_cancellation_disables_payment(): void
    {
        $order = $this->checkout();
        $this->actingAs($this->seller, 'sanctum')->postJson($this->url($order))->assertUnprocessable();
        app(OrderService::class)->cancelOwn($order, $this->buyer->id);
        $this->assertSame('failed', $order->payment->fresh()->status);
        $this->postJson($this->url($order))->assertUnprocessable();
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_expired_pending_checkout_cancels_cod_payment(): void
    {
        $order = $this->checkout();
        $order->update(['status' => 'pending']);
        app(CheckoutService::class)->release($order);
        $this->assertSame('failed', $order->payment->fresh()->status);
        $this->actingAs($this->seller, 'sanctum')->postJson($this->url($order))->assertUnprocessable();
    }

    public function test_delivered_unpaid_return_is_rejected_then_collected_shop_can_return_while_sibling_is_unpaid(): void
    {
        $order = $this->checkout(2);
        $this->deliver($order);
        $item = $order->shopOrders[0]->items()->sole();
        $payload = ['order_item_id' => $item->id, 'quantity' => 1, 'reason' => 'The item is damaged.'];
        $this->actingAs($this->buyer, 'sanctum')->postJson('/api/returns', $payload)->assertUnprocessable();
        $this->actingAs($this->seller, 'sanctum')->postJson($this->url($order))->assertOk();
        $id = $this->actingAs($this->buyer, 'sanctum')->postJson('/api/returns', $payload)->assertCreated()->json('data.id');
        $url = "/api/seller/shops/{$order->shopOrders[0]->shop_id}/returns/{$id}";
        $this->actingAs($this->seller, 'sanctum')->putJson($url, ['status' => 'approved'])->assertOk();
        $this->putJson($url, ['status' => 'received'])->assertOk();
        $this->putJson($url, ['status' => 'refunded', 'reference' => 'CASH-REFUND-44'])->assertOk();
        $this->assertSame('refunded', $order->shopOrders[0]->fresh()->codPaymentStatus());
        $this->postJson($this->url($order))->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->assertSame('partial', $order->fresh()->payment_status);
        $this->deliver($order, 1);
        $this->postJson($this->url($order, 1))->assertOk();
        $this->assertSame('partially_refunded', $order->fresh()->payment_status);
        $this->assertDatabaseCount('payment_transactions', 2);
        $this->assertDatabaseHas('payment_refunds', ['amount' => 44, 'method' => 'cash_receipt']);
    }

    public function test_reports_and_analytics_include_only_collected_shops_in_original_placement_month(): void
    {
        $order = $this->checkout(2);
        $order->update(['placed_at' => now()->subMonthNoOverflow()]);
        $this->deliver($order);
        $this->deliver($order, 1);
        $admin = User::factory()->admin()->create();
        $metrics = app(FinancialMetricService::class);
        $month = $order->placed_at->copy()->startOfMonth();
        $revenue = fn () => $metrics->monthly(null, $month, $month->copy()->endOfMonth(), 'USD')['buckets'][$month->format('Y-m')]['revenue'];
        $this->assertSame(0, $revenue());
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/reports/summary')->assertOk()->assertJsonPath('data.revenue', 0);
        app(CodCollectionService::class)->collect($order->shopOrders[0], $this->seller);
        $this->getJson('/api/admin/reports/summary')->assertOk()->assertJsonPath('data.revenue', 44);
        $this->assertSame(4000, $revenue()); // Analytics excludes tax and delivery by its existing definition.
        app(CodCollectionService::class)->collect($order->shopOrders[1], $this->seller);
        $this->getJson('/api/admin/reports/summary')->assertOk()->assertJsonPath('data.revenue', 110);
        $this->assertSame(10000, $revenue());
        $this->assertSame(0, $metrics->monthly(null, now()->startOfMonth(), now()->endOfMonth(), 'USD')['buckets'][now()->format('Y-m')]['revenue']);
    }

    public function test_failure_after_transaction_insert_rolls_back_all_financial_changes(): void
    {
        $order = $this->checkout();
        $this->deliver($order);
        $this->mock(CodCollectionService::class, function ($mock) {
            $mock->makePartial()->shouldReceive('synchronize')->once()->andThrow(new \RuntimeException('Injected aggregation failure'));
        });
        try {
            app(CodCollectionService::class)->collect($order->shopOrders[0], $this->seller);
            $this->fail('Expected the injected failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected aggregation failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('payment_transactions', 0);
        $this->assertSame('pending', $order->payment->fresh()->status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_database_constraint_blocks_duplicate_shop_collection_even_outside_service(): void
    {
        $order = $this->checkout();
        $this->deliver($order);
        app(CodCollectionService::class)->collect($order->shopOrders[0], $this->seller);
        try {
            DB::transaction(fn () => PaymentTransaction::create(PaymentTransaction::sole()->only(['payment_id', 'shop_order_id', 'type', 'status', 'amount', 'reference', 'meta'])));
            $this->fail('Expected the unique business constraint.');
        } catch (QueryException) {
            $this->assertDatabaseCount('payment_transactions', 1);
        }
    }

    public function test_collected_cash_cannot_be_refunded_by_generic_order_transition(): void
    {
        $order = $this->checkout();
        $this->deliver($order);
        app(CodCollectionService::class)->collect($order->shopOrders[0], $this->seller);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/orders/{$order->id}/transition", ['status' => 'refunded'])->assertUnprocessable();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseCount('payment_refunds', 0);
    }

    public function test_capped_coupon_collection_uses_immutable_discounted_total_after_catalog_changes(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'CODCAP', 'type' => 'percentage', 'value' => 50, 'max_discount_amount' => 5, 'min_order_amount' => null]);
        $order = $this->checkout(1, $coupon->code);
        $this->assertEquals(38.5, $order->total);
        $this->deliver($order);
        $coupon->delete();
        Product::first()->update(['price' => 999]);
        $this->actingAs($this->seller, 'sanctum')->postJson($this->url($order), ['amount' => 1])->assertOk()->assertJsonPath('data.amount', 38.5);
        $this->assertEquals(38.5, PaymentTransaction::sole()->amount);
        $this->assertEquals($order->total, $order->payment->fresh()->amount);
        $this->assertEquals(5, $order->shopOrders[0]->fresh()->discount_amount);
    }

    public static function terminalPayments(): array
    {
        return [['refunded', 'refunded'], ['completed', 'paid'], ['failed', 'failed'], ['refund_pending', 'refund_pending']];
    }

    #[DataProvider('terminalPayments')]
    public function test_terminal_financial_states_cannot_be_reopened_without_collection_evidence(string $paymentStatus, string $orderStatus): void
    {
        $order = $this->checkout();
        $this->deliver($order);
        $order->payment->update(['status' => $paymentStatus]);
        $order->update(['payment_status' => $orderStatus]);
        $this->actingAs($this->seller, 'sanctum')->postJson($this->url($order))->assertUnprocessable();
        $this->assertDatabaseCount('payment_transactions', 0);
        $this->assertSame($paymentStatus, $order->payment->fresh()->status);
    }

    public function test_fully_discounted_cod_can_settle_zero_balance_once(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'FREECOD', 'type' => 'fixed', 'value' => 100, 'min_order_amount' => null]);
        $order = $this->checkout(1, $coupon->code);
        $this->assertEquals(0, $order->total);
        $this->deliver($order);
        $this->actingAs($this->seller, 'sanctum')->postJson($this->url($order))->assertOk()->assertJsonPath('data.amount', 0);
        $this->postJson($this->url($order))->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertEquals(0, PaymentTransaction::sole()->amount);
    }
}
