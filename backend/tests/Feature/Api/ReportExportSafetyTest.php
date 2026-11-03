<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportExportSafetyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('exportUrls')]
    public function test_exports_reject_malformed_dates_with_validation_errors(string $url): void
    {
        $this->asAdmin()->getJson($url.'?from=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from');
    }

    #[DataProvider('exportUrls')]
    public function test_exports_reject_reversed_date_ranges(string $url): void
    {
        $this->asAdmin()->getJson($url.'?from=2026-11-30&to=2026-11-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_exports_reject_unknown_status_and_sort_inputs(): void
    {
        $this->asAdmin()->getJson('/api/admin/reports/orders.csv?status=hacked')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->asAdmin()->getJson('/api/admin/reports/orders.csv?sort_by=DROP%20TABLE&sort_direction=sideways')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort_by', 'sort_direction']);
    }

    public function test_orders_csv_streams_formula_safe_customer_values(): void
    {
        Order::factory()->create([
            'customer_name' => "=SUM(1,1) អតិថិជន",
            'email' => 'buyer@example.test',
            'placed_at' => '2026-11-01 12:00:00',
        ]);

        $response = $this->asAdmin()->get('/api/admin/reports/orders.csv?from=2026-11-01&to=2026-11-01');

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('order_number,placed_at,customer_name', $response->streamedContent());
        $this->assertStringContainsString("'=SUM(1,1) អតិថិជន", $response->streamedContent());
    }

    public function test_payments_csv_keyset_iteration_exports_each_payment_once(): void
    {
        $orders = Order::factory()->count(2)->create(['placed_at' => '2026-11-01 12:00:00']);
        foreach ($orders as $order) {
            Payment::query()->create([
                'order_id' => $order->id,
                'method' => 'bakong',
                'status' => Payment::STATUS_COMPLETED,
                'transaction_id' => '=unsafe-transaction',
                'amount' => '10.00',
                'paid_at' => '2026-11-01 12:30:00',
            ]);
        }

        $response = $this->asAdmin()->get('/api/admin/reports/payments.csv?from=2026-11-01&to=2026-11-01');

        $response->assertOk();
        $this->assertSame(3, substr_count($response->streamedContent(), "\n"));
        $this->assertStringContainsString("'=unsafe-transaction", $response->streamedContent());
    }

    public function test_pdf_exports_are_bounded_by_row_count_and_date_span(): void
    {
        Order::factory()->count(1001)->create(['placed_at' => '2026-11-01 12:00:00']);

        $this->asAdmin()->getJson('/api/admin/reports/orders.pdf?from=2026-11-01&to=2026-11-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('export');
        $this->asAdmin()->getJson('/api/admin/reports/orders.pdf?from=2026-01-01&to=2026-05-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
        $this->asAdmin()->getJson('/api/admin/reports/orders.csv?from=2025-01-01&to=2026-01-02')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_orders_csv_streams_a_large_fixture_without_loading_a_response_collection(): void
    {
        Order::factory()->count(1500)->create(['placed_at' => '2026-11-01 12:00:00']);

        $response = $this->asAdmin()->get('/api/admin/reports/orders.csv?from=2026-11-01&to=2026-11-01');

        $response->assertOk();
        $this->assertSame(1501, substr_count($response->streamedContent(), "\n"));
    }

    public function test_pdf_renders_when_the_export_is_within_the_row_limit(): void
    {
        Order::factory()->count(2)->create(['placed_at' => '2026-11-01 12:00:00']);

        $this->asAdmin()->get('/api/admin/reports/orders.pdf?from=2026-11-01&to=2026-11-01')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public static function exportUrls(): array
    {
        return [
            'orders csv' => ['/api/admin/reports/orders.csv'],
            'orders pdf' => ['/api/admin/reports/orders.pdf'],
            'products csv' => ['/api/admin/reports/products.csv'],
            'payments csv' => ['/api/admin/reports/payments.csv'],
        ];
    }

    private function asAdmin(): self
    {
        return $this->actingAs(User::factory()->admin()->create(), 'sanctum');
    }
}
