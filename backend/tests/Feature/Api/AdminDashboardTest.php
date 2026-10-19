<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_attention_queues_include_old_unresolved_work_and_exclude_resolved_or_deleted_orders(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));
        $customer = User::factory()->create();
        $product = Product::factory()->withVariant()->create();
        $partial = $this->sale($product, $customer, '2026-09-01', 60, Order::STATUS_PENDING, 'partial');
        $refund = $this->sale($product, $customer, '2026-09-01', 40, Order::STATUS_CANCELLED, 'refund_pending');
        $paid = $this->sale($product, $customer, '2026-09-01', 25, Order::STATUS_DELIVERED, Order::PAYMENT_PAID);
        $failed = Order::factory()->create(['placed_at' => '2026-09-01']);
        $deleted = Order::factory()->create(['payment_status' => 'partial']);
        foreach ([[$partial, 'pending'], [$refund, 'refund_pending'], [$paid, 'completed'], [$failed, 'failed'], [$deleted, 'failed']] as [$order, $status]) {
            Payment::create(['order_id' => $order->id, 'method' => 'bakong', 'status' => $status, 'amount' => $order->total, 'provider_data' => ['private' => 'do-not-expose']]);
        }
        $deleted->delete();
        $pending = Review::create(['user_id' => $customer->id, 'product_id' => $product->id, 'order_id' => $paid->id, 'rating' => 4, 'title' => 'Awaiting moderation', 'status' => 'pending', 'created_at' => '2026-09-01']);
        Review::create(['user_id' => $failed->user_id, 'product_id' => $product->id, 'order_id' => $failed->id, 'rating' => 5, 'status' => 'approved']);
        OrderReturn::create(['order_id' => $paid->id, 'order_item_id' => $paid->items()->firstOrFail()->id, 'user_id' => $customer->id, 'quantity' => 1, 'reason' => 'Return example', 'refund_amount' => 10, 'status' => 'requested']);
        SupportRequest::create(['email' => 'help@example.test', 'subject' => 'Open request', 'message' => 'Please help.', 'status' => 'open']);
        SupportRequest::create(['email' => 'answered@example.test', 'subject' => 'Resolved request', 'message' => 'Already answered.', 'status' => 'answered']);

        $response = $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/admin/dashboard/overview?range=today');
        $response->assertOk()->assertJsonPath('data.period.revenue', 0)
            ->assertJsonPath('data.attention.pending_reviews', 1)
            ->assertJsonPath('data.attention.payment_exceptions', 3)
            ->assertJsonPath('data.attention.open_returns', 1)
            ->assertJsonPath('data.attention.open_support', 1)
            ->assertJsonCount(1, 'data.pending_reviews')
            ->assertJsonPath('data.pending_reviews.0.id', $pending->id)
            ->assertJsonCount(3, 'data.payment_exceptions')
            ->assertJsonMissingPath('data.payment_exceptions.0.provider_data');
        $this->assertEqualsCanonicalizing(['partial', 'refund_pending', 'failed'], array_column($response->json('data.payment_exceptions'), 'status'));

        $this->getJson('/api/admin/payments?attention=1')->assertOk()->assertJsonPath('meta.total', 3)
            ->assertJsonMissingPath('data.0.provider_data');
        $this->getJson('/api/admin/payments')->assertOk()->assertJsonPath('meta.total', 5);
    }

    public function test_settings_expose_actual_checkout_currency_and_reject_unsupported_currency_updates(): void
    {
        Setting::set('currency', 'EUR');
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/admin/settings')->assertOk()->assertJsonPath('data.currency', 'USD');
        foreach (['EUR', 'KHR'] as $currency) {
            $this->putJson('/api/admin/settings', ['currency' => $currency])->assertUnprocessable()->assertJsonValidationErrors('currency');
        }
        $this->putJson('/api/admin/settings', ['currency' => 'USD'])->assertOk()->assertJsonPath('data.currency', 'USD');
    }

    public function test_merchandising_report_includes_only_paid_sales_for_its_dates(): void
    {
        $product = Product::factory()->withVariant()->create();
        $customer = User::factory()->create();
        $this->sale($product, $customer, '2026-10-01', 50, Order::STATUS_DELIVERED, Order::PAYMENT_PAID);
        $this->sale($product, $customer, '2026-10-02', 75, Order::STATUS_PENDING, Order::PAYMENT_UNPAID);
        $this->sale($product, $customer, '2026-09-01', 100, Order::STATUS_DELIVERED, Order::PAYMENT_PAID);
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/admin/reports/summary?from=2026-10-01&to=2026-10-02')->assertOk()
            ->assertJsonPath('data.top_selling_products.0.revenue', 50)
            ->assertJsonPath('data.sales_by_category.0.revenue', 50);
        $this->getJson('/api/admin/reports/summary?to=2026-10-02')->assertOk()
            ->assertJsonPath('data.top_selling_products.0.revenue', 150);
        $this->getJson('/api/admin/reports/summary?from=invalid')->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->getJson('/api/admin/reports/summary?from=2026-10-02&to=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_revenue_and_product_sales_only_include_paid_live_orders_in_the_selected_period(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 12:00:00'));
        $product = Product::factory()->withVariant()->create();
        $customer = User::factory()->create();
        $paid = $this->sale($product, $customer, '2026-10-06', 120, Order::STATUS_DELIVERED, Order::PAYMENT_PAID);
        $paid->items()->firstOrFail()->update(['unit_price' => 45, 'line_total' => 90]);
        $paid->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $product->variants()->firstOrFail()->id,
            'product_name' => $product->name,
            'sku' => $product->sku.'-SECOND',
            'unit_price' => 30,
            'quantity' => 1,
            'line_total' => 30,
        ]);
        $this->sale($product, $customer, '2026-10-06', 200, Order::STATUS_PENDING, Order::PAYMENT_UNPAID);
        $this->sale($product, $customer, '2026-10-06', 300, Order::STATUS_CANCELLED, Order::PAYMENT_PAID);
        $this->sale($product, $customer, '2026-10-06', 400, Order::STATUS_REFUNDED, Order::PAYMENT_REFUNDED);
        $this->sale($product, $customer, '2026-10-06', 500, Order::STATUS_DELIVERED, Order::PAYMENT_PAID)->delete();
        $this->sale($product, $customer, '2026-09-01', 80, Order::STATUS_DELIVERED, Order::PAYMENT_PAID);
        Payment::create(['order_id' => $paid->id, 'method' => 'card', 'status' => Payment::STATUS_COMPLETED, 'amount' => 120]);

        $response = $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/admin/dashboard/overview?range=custom&from=2026-10-06&to=2026-10-07');

        $response->assertOk()
            ->assertJsonPath('data.metrics.total_revenue', 200)
            ->assertJsonPath('data.period.revenue', 120)
            ->assertJsonPath('data.period.orders_count', 4)
            ->assertJsonPath('data.period.average_order_value', 120)
            ->assertJsonPath('data.revenue_trend.0.revenue', 120)
            ->assertJsonPath('data.revenue_trend.1.revenue', 0)
            ->assertJsonPath('data.orders_trend.0.orders', 4)
            ->assertJsonPath('data.top_selling_products.0.total_qty', 3)
            ->assertJsonPath('data.top_selling_products.0.revenue', 120)
            ->assertJsonPath('data.sales_by_category.0.revenue', 120)
            ->assertJsonPath('data.sales_by_category.0.order_count', 1)
            ->assertJsonCount(1, 'data.payment_status_distribution')
            ->assertJsonCount(4, 'data.recent_orders');
    }

    public function test_period_comparison_uses_the_previous_equal_length_period(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 12:00:00'));
        $customer = User::factory()->create(['created_at' => '2026-09-01']);
        Order::factory()->for($customer)->delivered()->create(['placed_at' => '2026-10-06', 'total' => 150]);
        Order::factory()->for($customer)->delivered()->create(['placed_at' => '2026-10-04', 'total' => 100]);
        User::factory()->create(['created_at' => '2026-10-06']);
        User::factory()->create(['created_at' => '2026-10-04']);

        $response = $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/admin/dashboard/overview?range=custom&from=2026-10-06&to=2026-10-07');

        $response->assertOk()->assertJsonPath('data.period.revenue_delta', 50)
            ->assertJsonPath('data.period.orders_delta', 0)
            ->assertJsonPath('data.period.customers_delta', 0)
            ->assertJsonPath('data.period.customers_count', 1);
    }

    public function test_recent_orders_include_customer_snapshots_and_items_without_exposing_authentication_data(): void
    {
        $this->freezeTime();
        $product = Product::factory()->withVariant()->create();
        $customer = User::factory()->create(['name' => 'Registered customer']);
        $order = $this->sale($product, $customer, now()->toDateString(), 75, Order::STATUS_PENDING, Order::PAYMENT_UNPAID);
        $order->update(['customer_name' => 'Checkout name', 'email' => 'checkout@example.test']);

        $response = $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/admin/dashboard/overview');

        $response->assertOk()->assertJsonPath('data.recent_orders.0.id', $order->id)
            ->assertJsonPath('data.recent_orders.0.items_count', 2)
            ->assertJsonPath('data.recent_orders.0.customer_name', 'Checkout name')
            ->assertJsonPath('data.recent_orders.0.email', 'checkout@example.test')
            ->assertJsonPath('data.recent_orders.0.user.name', 'Registered customer')
            ->assertJsonMissingPath('data.recent_orders.0.user.password')
            ->assertJsonMissingPath('data.recent_orders.0.user.remember_token');
    }

    public function test_low_stock_alerts_use_available_stock_and_exclude_inactive_products_and_variants(): void
    {
        $this->freezeTime();
        $low = Product::factory()->withVariant(stock: 10)->create();
        $low->variants()->firstOrFail()->inventory()->update(['reserved_quantity' => 8]);
        $empty = Product::factory()->withVariant(stock: 0)->create();
        Product::factory()->inactive()->withVariant(stock: 0)->create();
        $inactiveVariant = Product::factory()->withVariant(stock: 0)->create();
        $inactiveVariant->variants()->update(['is_active' => false]);

        $response = $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/admin/dashboard/overview');

        $response->assertOk()->assertJsonPath('data.metrics.low_stock_products', 2)
            ->assertJsonPath('data.metrics.out_of_stock_products', 1)
            ->assertJsonCount(2, 'data.low_stock')
            ->assertJsonPath('data.low_stock.0.product_id', $empty->id)
            ->assertJsonPath('data.low_stock.1.available_quantity', 2);
    }

    #[DataProvider('invalidRanges')]
    public function test_invalid_ranges_return_422_with_field_errors(string $query, array $fields): void
    {
        $response = $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/admin/dashboard/overview?'.$query);

        $response->assertUnprocessable()->assertJsonValidationErrors($fields);
    }

    public static function invalidRanges(): array
    {
        return [
            'unknown range' => ['range=forever', ['range']],
            'missing dates' => ['range=custom', ['from', 'to']],
            'malformed date' => ['range=custom&from=not-a-date&to=2026-10-07', ['from']],
            'reversed range' => ['range=custom&from=2026-10-07&to=2026-10-01', ['to']],
            'unbounded range' => ['range=custom&from=2020-01-01&to=2026-10-07', ['to']],
        ];
    }

    #[DataProvider('presetRanges')]
    public function test_preset_ranges_return_expected_dates_and_daily_series(string $range, string $from, string $to, int $days): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 12:00:00'));

        $response = $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/admin/dashboard/overview?range='.$range);

        $response->assertOk()->assertJsonPath('data.period.from', $from)
            ->assertJsonPath('data.period.to', $to)
            ->assertJsonCount($days, 'data.revenue_trend')
            ->assertJsonCount($days, 'data.orders_trend');
    }

    public static function presetRanges(): array
    {
        return [
            'today' => ['today', '2026-10-07', '2026-10-07', 1],
            'yesterday' => ['yesterday', '2026-10-06', '2026-10-06', 1],
            'last seven days' => ['7', '2026-10-01', '2026-10-07', 7],
            'last thirty days' => ['30', '2026-09-08', '2026-10-07', 30],
            'this month' => ['this_month', '2026-10-01', '2026-10-07', 7],
            'last month' => ['last_month', '2026-09-01', '2026-09-30', 30],
        ];
    }

    public function test_empty_dashboard_returns_zero_totals_and_no_comparison_baseline(): void
    {
        $this->freezeTime();

        $response = $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/admin/dashboard/overview');

        $response->assertOk()->assertJsonPath('data.period.revenue', 0)
            ->assertJsonPath('data.period.average_order_value', 0)
            ->assertJsonPath('data.period.revenue_delta', null)
            ->assertJsonCount(0, 'data.recent_orders');
    }

    private function sale(Product $product, User $customer, string $date, float $amount, string $status, string $paymentStatus): Order
    {
        $order = Order::factory()->for($customer)->create(['placed_at' => $date, 'total' => $amount, 'status' => $status, 'payment_status' => $paymentStatus]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $product->variants()->firstOrFail()->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => $amount / 2,
            'quantity' => 2,
            'line_total' => $amount,
        ]);

        return $order;
    }
}
