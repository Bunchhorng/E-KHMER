<?php

namespace Tests\Feature;

use App\Models\BakongPayment;
use App\Models\BusinessExpense;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use App\Policies\BusinessForecastPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BusinessForecastApiTest extends TestCase
{
    use RefreshDatabase;

    private function manager(Shop $shop, string $role = 'owner'): User
    {
        $user = User::factory()->create();
        $shop->users()->attach($user, ['role_in_shop' => $role, 'status' => 'active']);

        return $user;
    }

    private function sale(Shop $shop, string $date, string $cost = '40.00', array $attributes = []): ShopOrder
    {
        $order = Order::factory()->create([
            'placed_at' => $date, 'status' => 'delivered', 'payment_status' => 'paid', 'currency' => 'USD',
            'subtotal' => 100, 'discount_amount' => 10, 'tax_amount' => 9, 'shipping_amount' => 5, 'total' => 104,
            ...$attributes,
        ]);
        $allocation = ShopOrder::create([
            'order_id' => $order->id, 'shop_id' => $shop->id, 'shop_order_number' => $order->order_number.'-1',
            'status' => 'delivered', 'subtotal' => 100, 'discount_amount' => 10, 'tax_amount' => 9, 'shipping_amount' => 5, 'total' => 104,
        ]);
        $allocation->items()->create([
            'order_id' => $order->id, 'shop_id' => $shop->id, 'product_name' => 'Historical product',
            'quantity' => 2, 'unit_price' => 50, 'unit_cost' => $cost, 'line_total' => 100,
        ]);
        Payment::create(['order_id' => $order->id, 'method' => 'cod', 'status' => 'completed', 'amount' => 104, 'paid_at' => $date]);

        return $allocation;
    }

    public function test_sales_discount_costs_expenses_and_missing_months_come_from_real_records(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 12:00'));
        $shop = Shop::factory()->create();
        $owner = $this->manager($shop);
        $allocation = $this->sale($shop, '2026-06-15');
        $this->sale($shop, '2026-08-15');
        $this->sale($shop, '2026-09-15', '40.00', ['status' => 'cancelled']);
        $failed = $this->sale($shop, '2026-09-16');
        $failed->order->payment->update(['status' => 'failed']);
        $this->sale($shop, '2026-09-17', '40.00', ['currency' => 'KHR']);
        $this->sale(Shop::factory()->create(), '2026-09-18');
        PaymentTransaction::create(['payment_id' => $allocation->order->payment->id, 'type' => 'charge', 'status' => 'completed', 'amount' => 104]);
        BusinessExpense::factory()->create(['shop_id' => $shop->id, 'incurred_on' => '2026-06-16', 'amount' => 15]);

        $response = $this->actingAs($owner, 'sanctum')->getJson("/api/seller/shops/{$shop->id}/forecasts?from=2026-06-01&to=2026-10-26&metric=profit");

        $response->assertOk()->assertJsonPath('data.summary.revenue', 180)->assertJsonPath('data.summary.cogs', 160)
            ->assertJsonPath('data.summary.expense', 15)->assertJsonPath('data.summary.profit', 5)
            ->assertJsonPath('data.summary.orders', 2)->assertJsonPath('data.history.1.period', '2026-07')
            ->assertJsonPath('data.history.1.value', 0)->assertJsonPath('data.history.4.type', 'current')
            ->assertJsonPath('data.data_points', 4);
        $this->assertArrayNotHasKey('unit_cost', $allocation->items()->first()->toArray());
    }

    public function test_verified_refunds_remove_tax_once_and_only_recovered_stock_reverses_cost(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 12:00'));
        $shop = Shop::factory()->create();
        $owner = $this->manager($shop);
        $allocation = $this->sale($shop, '2026-08-15');
        $item = $allocation->items()->first();
        $return = OrderReturn::create(['order_id' => $allocation->order_id, 'order_item_id' => $item->id,
            'shop_id' => $shop->id, 'user_id' => $allocation->order->user_id, 'quantity' => 1, 'reason' => 'Return',
            'status' => 'refunded', 'restocked' => true, 'refund_amount' => 49.50]);
        PaymentRefund::create(['order_id' => $allocation->order_id, 'order_return_id' => $return->id,
            'amount' => 49.50, 'currency' => 'USD', 'reference' => 'cash-return', 'method' => 'cash_receipt']);

        $this->actingAs($owner, 'sanctum')->getJson("/api/seller/shops/{$shop->id}/forecasts?from=2026-08-01&to=2026-10-26")
            ->assertOk()->assertJsonPath('data.summary.revenue', 45)->assertJsonPath('data.summary.refunds', 45)
            ->assertJsonPath('data.summary.cogs', 40)->assertJsonPath('data.summary.profit', 5);
    }

    public function test_full_refund_without_restock_keeps_write_off_loss_and_does_not_count_an_order(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 12:00'));
        $shop = Shop::factory()->create();
        $allocation = $this->sale($shop, '2026-08-15', '40.00', ['payment_status' => 'refunded']);
        PaymentRefund::create(['order_id' => $allocation->order_id, 'amount' => 104,
            'currency' => 'USD', 'reference' => 'full-cash-return', 'method' => 'cash_receipt']);

        $this->actingAs($this->manager($shop), 'sanctum')->getJson("/api/seller/shops/{$shop->id}/forecasts")
            ->assertOk()->assertJsonPath('data.summary.revenue', 0)->assertJsonPath('data.summary.cogs', 80)
            ->assertJsonPath('data.summary.loss', -80)->assertJsonPath('data.summary.orders', 0);
    }

    public function test_unknown_historical_cost_stays_unknown_and_blocks_a_profit_prediction(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 12:00'));
        $shop = Shop::factory()->create();
        $sale = $this->sale($shop, '2026-09-15');
        $sale->items()->update(['unit_cost' => null]);

        $this->actingAs($this->manager($shop), 'sanctum')->getJson("/api/seller/shops/{$shop->id}/forecasts?from=2026-09-01")
            ->assertOk()->assertJsonPath('data.summary.profit', null)->assertJsonPath('data.summary.cogs', null)
            ->assertJsonPath('data.summary.cost_coverage', 0)->assertJsonPath('data.status', 'missing_costs')
            ->assertJsonCount(0, 'data.forecast');
    }

    #[TestWith(['owner'])]
    #[TestWith(['manager'])]
    public function test_active_managers_only_receive_their_shop_despite_forged_scope(string $role): void
    {
        $shop = Shop::factory()->create();
        $other = Shop::factory()->create();
        $this->sale($shop, now()->subMonth()->toDateString());
        $this->sale($other, now()->subMonth()->toDateString());
        $user = $this->manager($shop, $role);

        $this->actingAs($user, 'sanctum')->getJson("/api/seller/shops/{$shop->id}/forecasts?shop_id={$other->id}")
            ->assertOk()->assertJsonPath('data.scope.shop_id', $shop->id)->assertJsonPath('data.summary.revenue', 90);
        $this->getJson("/api/seller/shops/{$other->id}/forecasts")->assertForbidden();
        $this->getJson('/api/admin/forecasts')->assertForbidden();
    }

    public function test_customer_and_guest_cannot_access_private_forecasts(): void
    {
        $shop = Shop::factory()->create();
        $this->getJson("/api/seller/shops/{$shop->id}/forecasts")->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson("/api/seller/shops/{$shop->id}/forecasts")->assertForbidden();
    }

    #[TestWith(['staff', 'active', false])]
    #[TestWith(['manager', 'suspended', false])]
    #[TestWith(['owner', 'active', true])]
    #[TestWith(['manager', 'active', true])]
    public function test_policy_enforces_the_management_membership_matrix(string $role, string $status, bool $allowed): void
    {
        $shop = Shop::factory()->create();
        $user = User::factory()->create();
        $shop->users()->attach($user, ['role_in_shop' => $role, 'status' => $status]);
        $this->assertSame($allowed, (new BusinessForecastPolicy)->view($user, $shop));
    }

    public function test_super_admin_can_read_marketplace_and_select_an_existing_shop(): void
    {
        $shop = Shop::factory()->create();
        $this->sale($shop, now()->subMonth()->toDateString());
        $this->sale(Shop::factory()->create(), now()->subMonth()->toDateString());
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/forecasts')->assertOk()->assertJsonPath('data.summary.revenue', 180);
        $this->getJson('/api/admin/forecasts?shop_id='.$shop->id)->assertOk()->assertJsonPath('data.summary.revenue', 90);
        $this->assertTrue((new BusinessForecastPolicy)->view($admin));
    }

    #[TestWith(['horizon=0', 'horizon'])]
    #[TestWith(['horizon=13', 'horizon'])]
    #[TestWith(['period=weekly', 'period'])]
    #[TestWith(['metric=balance', 'metric'])]
    #[TestWith(['currency=EUR', 'currency'])]
    #[TestWith(['from=2026-09-01&to=2026-08-01', 'to'])]
    #[TestWith(['from=2010-01-01', 'from'])]
    #[TestWith(['to=2030-01-01', 'to'])]
    public function test_invalid_forecast_parameters_return_422(string $query, string $field): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 12:00'));
        $this->actingAs(User::factory()->create(['role' => 'super_admin']), 'sanctum')
            ->getJson('/api/admin/forecasts?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_expense_writes_are_shop_scoped_and_refresh_cached_financial_totals(): void
    {
        $shop = Shop::factory()->create();
        $other = Shop::factory()->create();
        $user = $this->manager($shop);
        $url = "/api/seller/shops/{$shop->id}";
        $this->actingAs($user, 'sanctum');
        $this->getJson($url.'/forecasts')->assertOk()->assertJsonPath('data.summary.expense', 0);
        $payload = ['shop_id' => $other->id, 'created_by' => 99999, 'amount' => 12.50, 'currency' => 'USD',
            'description' => 'Courier cost', 'category' => 'shipping', 'incurred_on' => now()->toDateString()];

        $created = $this->postJson($url.'/expenses', $payload)->assertCreated()->assertJsonPath('data.shop_id', $shop->id);
        $id = $created->json('data.id');
        $this->assertDatabaseHas('business_expenses', ['id' => $id, 'shop_id' => $shop->id, 'created_by' => $user->id]);
        $this->getJson($url.'/forecasts')->assertOk()->assertJsonPath('data.summary.expense', 12.5);
        $this->putJson($url.'/expenses/'.$id, [...$payload, 'amount' => 25])->assertOk()->assertJsonPath('data.amount', 25);
        $foreign = BusinessExpense::factory()->create(['shop_id' => $other->id]);
        $this->deleteJson($url.'/expenses/'.$foreign->id)->assertNotFound();
        $this->assertModelExists($foreign);
        $this->deleteJson($url.'/expenses/'.$id)->assertNoContent();
        $this->assertSoftDeleted('business_expenses', ['id' => $id]);
    }

    public function test_expenses_reject_negative_amounts_and_missing_accounting_details(): void
    {
        $shop = Shop::factory()->create();
        $this->actingAs($this->manager($shop), 'sanctum')->postJson("/api/seller/shops/{$shop->id}/expenses", ['amount' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors(['amount', 'currency', 'description', 'incurred_on', 'category']);
        $this->assertDatabaseCount('business_expenses', 0);
    }

    public function test_empty_history_is_not_padded_into_a_confident_forecast(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']), 'sanctum')->getJson('/api/admin/forecasts')
            ->assertOk()->assertJsonPath('data.status', 'insufficient_history')->assertJsonPath('data.confidence', 'unavailable')
            ->assertJsonCount(0, 'data.history')->assertJsonCount(0, 'data.forecast');
    }

    public function test_gateway_refunds_stay_with_their_shop_in_a_shared_parent_order(): void
    {
        $shop = Shop::factory()->create();
        $other = Shop::factory()->create();
        $allocation = $this->sale($shop, now()->subMonth()->toDateString());
        $second = ShopOrder::create(['order_id' => $allocation->order_id, 'shop_id' => $other->id,
            'shop_order_number' => $allocation->order->order_number.'-2', 'status' => 'delivered',
            'subtotal' => 200, 'discount_amount' => 20, 'tax_amount' => 18, 'shipping_amount' => 10, 'total' => 208]);
        $second->items()->create(['order_id' => $allocation->order_id, 'shop_id' => $other->id,
            'product_name' => 'Other shop goods', 'quantity' => 1, 'unit_price' => 200, 'unit_cost' => 50, 'line_total' => 200]);
        $allocation->order->update(['subtotal' => 300, 'discount_amount' => 30, 'tax_amount' => 27, 'shipping_amount' => 15, 'total' => 312, 'payment_status' => 'partially_refunded']);
        $allocation->order->payment->update(['amount' => 312]);
        $payment = BakongPayment::create(['order_id' => $allocation->order_id, 'shop_order_id' => $second->id,
            'shop_id' => $other->id, 'receiver_account' => 'shop@bank', 'amount' => 208, 'currency' => 'USD',
            'status' => 'refunded', 'expires_at' => now(), 'paid_at' => now()->subMonth()]);
        PaymentRefund::create(['order_id' => $allocation->order_id, 'bakong_payment_id' => $payment->id,
            'amount' => 208, 'currency' => 'USD', 'reference' => 'verified-other-shop-refund', 'method' => 'bakong_verified']);

        $this->actingAs($this->manager($shop), 'sanctum')->getJson("/api/seller/shops/{$shop->id}/forecasts")
            ->assertOk()->assertJsonPath('data.summary.revenue', 90)->assertJsonPath('data.summary.refunds', 0);
        $this->actingAs($this->manager($other), 'sanctum')->getJson("/api/seller/shops/{$other->id}/forecasts")
            ->assertOk()->assertJsonPath('data.summary.revenue', 0)->assertJsonPath('data.summary.profit', -50);
    }

    public function test_financial_loading_does_not_add_a_query_for_every_order(): void
    {
        $shop = Shop::factory()->create();
        for ($index = 0; $index < 20; $index++) {
            $this->sale($shop, now()->subMonth()->toDateString());
        }
        $this->actingAs($this->manager($shop), 'sanctum');
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->postJson("/api/seller/shops/{$shop->id}/forecasts/generate")->assertOk()->assertJsonPath('data.summary.orders', 20);

        $this->assertLessThan(30, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_forecast_steps_over_the_excluded_current_period_and_derives_signed_loss(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 12:00'));
        $shop = Shop::factory()->create();
        foreach ([6 => '40.00', 7 => '45.00', 8 => '50.00', 9 => '55.00'] as $month => $cost) {
            $this->sale($shop, '2026-'.$month.'-15', $cost);
        }

        $this->actingAs($this->manager($shop), 'sanctum')->getJson("/api/seller/shops/{$shop->id}/forecasts?from=2026-06-01&metric=loss&horizon=1")
            ->assertOk()->assertJsonPath('data.forecast.0.period', '2026-11')->assertJsonPath('data.forecast.0.value', -40)
            ->assertJsonPath('data.forecast_total', -40);
    }

    public function test_admin_expenses_need_an_explicit_shop_and_support_corrections(): void
    {
        $shop = Shop::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'super_admin']), 'sanctum');
        $payload = ['amount' => 30, 'currency' => 'USD', 'description' => 'Rent', 'category' => 'rent', 'incurred_on' => now()->toDateString()];
        $this->postJson('/api/admin/expenses', $payload)->assertUnprocessable()->assertJsonValidationErrors('shop_id');

        $created = $this->postJson('/api/admin/expenses', [...$payload, 'shop_id' => $shop->id])->assertCreated();
        $id = $created->json('data.id');
        $this->getJson('/api/admin/expenses')->assertOk()->assertJsonPath('meta.total', 1);
        $this->putJson('/api/admin/expenses/'.$id, [...$payload, 'amount' => 40])->assertOk()->assertJsonPath('data.amount', 40);
        $this->deleteJson('/api/admin/expenses/'.$id)->assertNoContent();
        $this->assertSoftDeleted('business_expenses', ['id' => $id]);
    }

    #[TestWith(['monthly', 3])]
    #[TestWith(['quarterly', 3])]
    #[TestWith(['yearly', 2])]
    public function test_period_views_exclude_the_current_period_and_generate_requested_horizons(string $period, int $horizon): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 12:00'));
        $shop = Shop::factory()->create();
        for ($year = 2023; $year <= 2026; $year++) {
            foreach ([1, 4, 7] as $month) {
                $this->sale($shop, sprintf('%d-%02d-15', $year, $month), '10.00');
            }
        }

        $response = $this->actingAs($this->manager($shop), 'sanctum')->getJson("/api/seller/shops/{$shop->id}/forecasts?from=2023-01-01&period={$period}&horizon={$horizon}");
        $response->assertOk()->assertJsonPath('data.status', 'available')->assertJsonCount($horizon, 'data.forecast')
            ->assertJsonPath('data.forecast.0.type', 'predicted');
        $this->assertIsString($response->json('data.history.0.period'));
        $this->assertSame('current', $response->json('data.history.'.(count($response->json('data.history')) - 1).'.type'));
    }
}
