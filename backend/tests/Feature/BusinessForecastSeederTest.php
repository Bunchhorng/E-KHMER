<?php

namespace Tests\Feature;

use App\Models\BusinessExpense;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\BusinessForecastSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class BusinessForecastSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_shops_have_costed_history_and_real_monthly_quarterly_and_yearly_forecasts(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 12:00'));
        $admin = User::factory()->create(['role' => 'super_admin']);
        $seller = User::factory()->create(['email' => 'seller@ekhmer.com']);
        Http::preventStrayRequests();
        Notification::fake();
        Mail::fake();

        $this->seed(BusinessForecastSeeder::class);

        $this->actingAs($admin, 'sanctum');
        $this->assertSame(3, Shop::where('code', 'like', 'DEMO-FC-%')->count());
        foreach (['GROWTH' => 'growing', 'STEADY' => 'stable', 'DECLINE' => 'declining'] as $code => $trend) {
            $shop = Shop::where('code', 'DEMO-FC-'.$code)->firstOrFail();
            $this->assertTrue($admin->ownsShop($shop));
            $this->assertTrue($seller->ownsShop($shop));
            $monthly = $this->getJson('/api/admin/forecasts?shop_id='.$shop->id.'&metric=profit&from=2022-01-01&to=2026-10-26')
                ->assertOk()->assertJsonPath('data.status', 'available')->assertJsonPath('data.data_points', 57)
                ->assertJsonPath('data.summary.cost_coverage', 100)->assertJsonPath('data.summary.missing_cost_items', 0)
                ->assertJsonPath('data.trend', $trend)->assertJsonPath('data.history.57.type', 'current')
                ->assertJsonCount(58, 'data.history')->assertJsonCount(3, 'data.forecast');
            if ($code === 'DECLINE') {
                $this->assertLessThan(0, $monthly->json('data.history.56.metrics.profit'));
                $this->assertLessThan(0, $monthly->json('data.forecast_total'));
            } else {
                $this->assertGreaterThan(0, $monthly->json('data.summary.profit'));
            }
            foreach (['quarterly', 'yearly'] as $period) {
                $this->getJson('/api/admin/forecasts?shop_id='.$shop->id.'&from=2022-01-01&to=2026-10-26&period='.$period.'&horizon=1')
                    ->assertOk()->assertJsonPath('data.status', 'available')->assertJsonCount(1, 'data.forecast');
            }
        }
        $this->assertSame(351, Order::where('note', BusinessForecastSeeder::SOURCE)->count());
        $this->assertSame(348, BusinessExpense::count());
        $this->assertSame(0, OrderItem::whereNull('unit_cost')->count());
        $this->assertSame(3, Order::where('status', 'cancelled')->count());
        $this->assertSame(3, Payment::where('status', 'failed')->count());
        foreach (PaymentRefund::with('returnRecord')->get() as $refund) {
            $order = Order::findOrFail($refund->order_id);
            $this->assertSame((float) $refund->amount, (float) $refund->returnRecord->refund_amount);
            $this->assertSame($order->user_id, $refund->returnRecord->user_id);
            $this->assertLessThanOrEqual((float) $order->total, (float) $refund->amount);
            $this->assertFalse($refund->returnRecord->restocked);
        }
        foreach (['placed_at', 'created_at'] as $date) {
            $this->assertSame(0, Order::where($date, '>', now())->count());
        }
        $this->assertDatabaseCount('inventories', 0);
        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_reseeding_preserves_existing_records_logins_sample_edits_and_soft_deletions(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 12:00'));
        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->createToken('existing-session');
        $realShop = Shop::factory()->create();
        $inventory = Inventory::factory()->create(['quantity' => 41]);
        $realOrder = Order::factory()->create(['note' => 'Existing purchase']);
        $realItem = $realOrder->items()->create([
            'shop_id' => $realShop->id, 'product_name' => 'Existing item',
            'unit_price' => 30, 'unit_cost' => null, 'quantity' => 1, 'line_total' => 30,
        ]);
        $realExpense = BusinessExpense::factory()->create(['shop_id' => $realShop->id, 'amount' => 87.50]);
        $beforeOrder = $realOrder->fresh()->getRawOriginal();

        $this->seed(BusinessForecastSeeder::class);
        $sample = BusinessExpense::where('description', 'like', BusinessForecastSeeder::SOURCE.'%')->firstOrFail();
        $sample->update(['amount' => 1.23]);
        $deleted = BusinessExpense::where('description', 'like', BusinessForecastSeeder::SOURCE.'%')->whereKeyNot($sample->id)->firstOrFail();
        $deleted->delete();
        $sampleOrder = Order::where('note', BusinessForecastSeeder::SOURCE)->firstOrFail();
        $sampleOrder->delete();
        $tables = ['users', 'shops', 'shop_users', 'orders', 'shop_orders', 'order_items', 'payments', 'payment_transactions', 'shipments', 'order_returns', 'payment_refunds', 'business_expenses'];
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        $this->seed(BusinessForecastSeeder::class);

        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame($beforeOrder, $realOrder->fresh()->getRawOriginal());
        $this->assertNull($realItem->fresh()->unit_cost);
        $this->assertSame('87.50', $realExpense->fresh()->amount);
        $this->assertSame('1.23', $sample->fresh()->amount);
        $this->assertSame(41, $inventory->fresh()->quantity);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertSoftDeleted($deleted);
        $this->assertSoftDeleted($sampleOrder);
    }

    public function test_demo_seed_is_rejected_in_production_without_writing_financial_records(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->artisan('db:seed', ['--class' => BusinessForecastSeeder::class, '--force' => true])->run();
            $this->fail('Production must not receive demo trading records.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forecast demo data may only be seeded in local or testing environments.', $exception->getMessage());
        }

        $this->assertDatabaseCount('shops', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_missing_admin_dependency_leaves_the_database_unchanged(): void
    {
        try {
            $this->seed(BusinessForecastSeeder::class);
            $this->fail('An existing admin is required for demo shop management.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Create a Super Admin or run UserSeeder before seeding forecast demo data.', $exception->getMessage());
        }

        $this->assertDatabaseCount('shops', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_reserved_shop_code_collision_rolls_back_all_demo_inserts(): void
    {
        User::factory()->create(['role' => 'super_admin']);
        $existing = Shop::factory()->create(['code' => 'DEMO-FC-GROWTH', 'description' => 'Existing merchant']);

        try {
            $this->seed(BusinessForecastSeeder::class);
            $this->fail('An unrelated shop must not receive demo financial records.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('belongs to an unrelated shop', $exception->getMessage());
        }

        $this->assertSame('Existing merchant', $existing->fresh()->description);
        $this->assertDatabaseCount('shops', 1);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('users', 1);
    }
}
