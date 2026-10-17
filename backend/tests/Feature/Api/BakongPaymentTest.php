<?php

namespace Tests\Feature\Api;

use App\Models\BakongPayment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\Shop;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BakongPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Notification::fake();
        config(['ecommerce.bakong_token' => 'test-only', 'ecommerce.payment_mode' => 'production']);
    }

    private function begin(int $shops = 1): Order
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        for ($i = 0; $i < $shops; $i++) {
            $shop = Shop::factory()->create(['bakong_account_id' => "seller{$i}@bank", 'bakong_merchant_name' => "Seller {$i}", 'bakong_merchant_city' => 'Phnom Penh']);
            $product = Product::factory()->withVariant(10, 20)->create(['shop_id' => $shop->id]);
            $this->postJson('/api/cart', ['product_variant_id' => $product->variants()->first()->id, 'quantity' => 1])->assertCreated();
        }
        $response = $this->postJson('/api/checkout', ['payment_method' => 'bakong',
            'shipping_method_id' => ShippingMethod::factory()->create(['price' => 0])->id,
            'address' => ['full_name' => 'Buyer', 'address_line1' => 'Street 1', 'city' => 'Phnom Penh', 'state' => 'Phnom Penh', 'postal_code' => '12000', 'country' => 'KH']])->assertCreated();
        $order = Order::where('order_number', $response->json('data.order_number'))->sole();
        $this->getJson("/api/checkout/{$order->order_number}/bakong")->assertOk()->assertJsonCount($shops, 'data');

        return $order;
    }

    private function transfer(BakongPayment $payment, string $hash = 'a'): array
    {
        return ['responseCode' => 0, 'data' => ['hash' => str_repeat($hash, 64), 'fromAccountId' => 'buyer@bank',
            'toAccountId' => $payment->receiver_account, 'currency' => $payment->currency, 'amount' => (float) $payment->amount]];
    }

    public function test_payment_is_verified_by_server_and_repeated_checks_do_not_deduct_stock_again(): void
    {
        $order = $this->begin();
        $payment = BakongPayment::sole();
        $variant = $order->items()->first()->variant;
        Http::fake(['*check_transaction_by_md5' => Http::response($this->transfer($payment))]);
        $url = "/api/checkout/{$order->order_number}/bakong/verify";
        $this->postJson($url)->assertOk()->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.status', 'confirmed');
        $this->postJson($url)->assertOk();
        $this->assertEquals(19, $variant->inventory()->first()->quantity);
        $this->assertEquals(0, $variant->inventory()->first()->reserved_quantity);
        $this->assertDatabaseCount('payment_transactions', 1);
        Http::assertSent(fn ($request) => $request['md5'] === $payment->md5 && $request->hasHeader('Authorization', 'Bearer test-only'));
    }

    public function test_clients_cannot_self_confirm_a_bakong_payment_even_in_sandbox(): void
    {
        $order = $this->begin();
        config(['ecommerce.payment_mode' => 'sandbox']);
        $this->postJson("/api/checkout/{$order->order_number}/confirm", ['transaction_id' => 'made-up'])->assertUnprocessable();
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_wrong_receiver_amount_and_currency_do_not_mark_an_order_paid(): void
    {
        $order = $this->begin();
        $payment = BakongPayment::sole();
        foreach ([['toAccountId' => 'attacker@bank'], ['amount' => 0.01], ['currency' => 'KHR'], ['hash' => 'bad']] as $bad) {
            $response = $this->transfer($payment);
            $response['data'] = array_replace($response['data'], $bad);
            Http::fake(['*' => Http::response($response)]);
            $this->postJson("/api/checkout/{$order->order_number}/bakong/verify")->assertUnprocessable();
        }
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_multi_shop_checkout_requires_every_seller_payment(): void
    {
        $order = $this->begin(2);
        $payments = BakongPayment::orderBy('id')->get();
        $secondPaid = false;
        Http::fake(function ($request) use ($payments, &$secondPaid) {
            if ($request['md5'] === $payments[0]->md5) {
                return Http::response($this->transfer($payments[0]));
            }

            return $secondPaid ? Http::response($this->transfer($payments[1], 'b')) : Http::response(['responseCode' => 1, 'errorCode' => 1, 'data' => null]);
        });
        $url = "/api/checkout/{$order->order_number}/bakong/verify";
        $this->postJson($url)->assertOk()->assertJsonPath('data.payment_status', 'partial')->assertJsonPath('data.status', 'pending');
        $secondPaid = true;
        $this->postJson($url)->assertOk()->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_expiry_releases_stock_but_tracks_partial_payment_as_refund_required(): void
    {
        $order = $this->begin(2);
        $payments = BakongPayment::orderBy('id')->get();
        Http::fake(function ($request) use ($payments) {
            return $request['md5'] === $payments[0]->md5 ? Http::response($this->transfer($payments[0])) : Http::response(['responseCode' => 1, 'errorCode' => 1]);
        });
        $this->postJson("/api/checkout/{$order->order_number}/bakong/verify")->assertOk();
        $this->travel(16)->minutes();
        app(CheckoutService::class)->expireStaleReservations();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('refund_pending', $order->fresh()->payment_status);
        $this->assertSame('refund_required', $payments[0]->fresh()->status);
        foreach ($order->items as $item) {
            $this->assertEquals(0, $item->variant->inventory->reserved_quantity);
        }
    }

    public function test_payment_information_and_verification_are_scoped_to_the_order_owner(): void
    {
        $order = $this->begin();
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->getJson("/api/checkout/{$order->order_number}/bakong")->assertNotFound();
        $this->postJson("/api/checkout/{$order->order_number}/bakong/verify")->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_cancelled_payment_refund_requires_matching_transfer_and_order_specific_memo(): void
    {
        $order = $this->begin(2);
        $payment = BakongPayment::orderBy('id')->firstOrFail();
        $bankRefund = null;
        Http::fake(function ($request) use ($payment, &$bankRefund) {
            if (isset($request['hash'])) {
                return Http::response($bankRefund);
            }

            return $request['md5'] === $payment->md5 ? Http::response($this->transfer($payment)) : Http::response(['responseCode' => 1, 'errorCode' => 1]);
        });
        $this->postJson("/api/checkout/{$order->order_number}/bakong/verify")->assertOk();
        $this->travel(16)->minutes();
        app(CheckoutService::class)->expireStaleReservations();
        $seller = User::factory()->create();
        $seller->shops()->attach($payment->shop_id, ['role_in_shop' => 'owner', 'status' => 'active']);
        $refund = ['responseCode' => 0, 'data' => ['hash' => str_repeat('c', 64), 'fromAccountId' => $payment->receiver_account,
            'toAccountId' => 'buyer@bank', 'amount' => (float) $payment->amount, 'currency' => 'USD',
            'description' => RefundService::memo($order)]];
        $bankRefund = $refund;
        $url = "/api/seller/shops/{$payment->shop_id}/payments/{$payment->id}/refund";
        $this->actingAs($seller, 'sanctum');
        foreach ([['toAccountId' => 'other@bank'], ['amount' => 0.01], ['description' => 'old unrelated transfer'], ['hash' => str_repeat('d', 64)]] as $bad) {
            $badResponse = $refund;
            $badResponse['data'] = array_replace($refund['data'], $bad);
            $bankRefund = $badResponse;
            $this->postJson($url, ['reference' => str_repeat('c', 64)])->assertUnprocessable();
            $this->assertDatabaseCount('payment_refunds', 0);
        }
        $bankRefund = $refund;
        $this->postJson($url, ['reference' => str_repeat('c', 64)])->assertOk();
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->postJson($url, ['reference' => str_repeat('c', 64)])->assertUnprocessable();
        $this->assertDatabaseCount('payment_refunds', 1);
    }

    public function test_reconciliation_tracks_late_money_without_reviving_a_cancelled_order(): void
    {
        $order = $this->begin();
        $payment = BakongPayment::sole();
        $this->travel(16)->minutes();
        app(CheckoutService::class)->expireStaleReservations();
        Http::fake(['*check_transaction_by_md5' => Http::response($this->transfer($payment))]);
        $this->artisan('payments:reconcile --limit=25')->assertSuccessful();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('refund_pending', $order->fresh()->payment_status);
        $this->assertSame('refund_required', $payment->fresh()->status);
        $inventory = $order->items()->first()->variant->inventory;
        $this->assertEquals(20, $inventory->quantity);
        $this->assertEquals(0, $inventory->reserved_quantity);
    }

    public function test_gateway_failure_never_marks_an_order_paid(): void
    {
        $order = $this->begin();
        Http::fake(['*' => Http::response([], 503)]);
        $this->postJson("/api/checkout/{$order->order_number}/bakong/verify")->assertUnprocessable();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_qr_requests_are_stable_and_have_distinct_identifiers_for_each_shop(): void
    {
        $order = $this->begin(2);
        $first = BakongPayment::pluck('md5')->all();
        $this->getJson("/api/checkout/{$order->order_number}/bakong")->assertOk();
        $this->assertSame($first, BakongPayment::pluck('md5')->all());
        $this->assertCount(2, array_unique($first));
        foreach (BakongPayment::all() as $payment) {
            $this->assertSame(md5($payment->qr_payload), $payment->md5);
            $this->assertStringStartsWith('000201010212', $payment->qr_payload);
            $this->assertStringContainsString($payment->receiver_account, $payment->qr_payload);
        }
    }
}
