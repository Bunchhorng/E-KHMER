<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentRefund;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_seed_restores_the_existing_data_and_authentication_records(): void
    {
        $user = User::factory()->create();
        $user->createToken('existing-login');
        $this->app->bind(UserSeeder::class, fn () => new class extends UserSeeder
        {
            public function run(): void
            {
                throw new RuntimeException('Simulated seed failure.');
            }
        });

        try {
            $this->seed();
            $this->fail('The seed should fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated seed failure.', $exception->getMessage());
        }

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_reseeding_replaces_every_business_table_with_twenty_related_records(): void
    {
        $this->seed();
        $this->assertBusinessTableCounts();

        $oldAdmin = User::where('email', 'admin@ekhmer.dev')->firstOrFail();
        $oldAdmin->createToken('old-demo-login');
        DB::table('password_reset_tokens')->insert([
            'email' => $oldAdmin->email, 'token' => 'old-reset-token', 'created_at' => now(),
        ]);
        DB::table('sessions')->insert([
            'id' => 'old-session', 'user_id' => $oldAdmin->id,
            'payload' => '', 'last_activity' => now()->timestamp,
        ]);
        Product::firstOrFail()->delete();

        $this->seed();
        $this->assertBusinessTableCounts();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertDatabaseCount('sessions', 0);
        $this->assertSame(20, Product::active()->count());
        $this->assertSame(1, User::where('role', User::ROLE_SUPER_ADMIN)->count());

        foreach (Order::with(['items', 'shopOrders', 'payment.transactions', 'shipments', 'coupon'])->get() as $order) {
            $item = $order->items->sole();
            $allocation = $order->shopOrders->sole();
            $shipment = $order->shipments->sole();
            $this->assertSame($allocation->id, $item->shop_order_id);
            $this->assertSame($allocation->id, $shipment->shop_order_id);
            $this->assertSame($item->shop_id, $allocation->shop_id);
            $this->assertSame((float) $order->subtotal, (float) $item->line_total);
            $this->assertSame((float) $order->total, (float) $allocation->total);
            $this->assertSame((float) $order->total, (float) $order->payment->amount);
            $this->assertSame((float) $order->total, (float) $order->payment->transactions->sole()->amount);
            $this->assertSame(1, $order->coupon->used_count);
            $this->assertLessThanOrEqual(now(), $shipment->delivered_at);
        }

        foreach (Review::with(['order.items', 'product'])->get() as $review) {
            $this->assertTrue($review->verified);
            $this->assertSame(Order::STATUS_DELIVERED, $review->order->status);
            $this->assertSame($review->user_id, $review->order->user_id);
            $this->assertSame($review->product_id, $review->order->items->sole()->product_id);
            $this->assertSame($review->shop_id, $review->product->shop_id);
            $approved = $review->status === Review::STATUS_APPROVED;
            $this->assertEquals($approved ? $review->rating : 0, $review->product->rating_avg);
            $this->assertSame($approved ? 1 : 0, $review->product->rating_count);
        }

        foreach (PaymentRefund::all() as $refund) {
            $order = Order::findOrFail($refund->order_id);
            $return = DB::table('order_returns')->find($refund->order_return_id);
            $payment = DB::table('bakong_payments')->find($refund->bakong_payment_id);
            $this->assertSame($order->id, $return->order_id);
            $this->assertSame($order->id, $payment->order_id);
            $this->assertSame($order->user_id, $return->user_id);
            $this->assertSame($return->shop_id, $payment->shop_id);
            $this->assertSame('refunded', $return->status);
            $this->assertSame((float) $return->refund_amount, (float) $refund->amount);
            $this->assertLessThan((float) $order->total, (float) $refund->amount);
            $this->assertSame('partially_refunded', $order->payment_status);
        }

        // SQLite and MySQL expose FK metadata through Laravel's schema API.
        foreach (DatabaseSeeder::TABLES as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (count($foreignKey['columns']) !== 1) {
                    continue;
                }
                $column = $foreignKey['columns'][0];
                $parent = $foreignKey['foreign_table'];
                $parentColumn = $foreignKey['foreign_columns'][0];
                $orphans = DB::table($table)->whereNotNull($column)
                    ->whereNotIn($column, DB::table($parent)->select($parentColumn))->count();
                $this->assertSame(0, $orphans, $table.'.'.$column.' must reference an existing record.');
            }
        }
    }

    private function assertBusinessTableCounts(): void
    {
        $frameworkTables = [
            'migrations', 'sessions', 'password_reset_tokens', 'personal_access_tokens',
            'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        ];
        $tableNames = array_map(fn (string $table) => Str::afterLast($table, '.'), Schema::getTableListing());
        $tables = array_values(array_diff($tableNames, $frameworkTables));
        $this->assertEqualsCanonicalizing(DatabaseSeeder::TABLES, $tables);
        foreach ($tables as $table) {
            $this->assertDatabaseCount($table, 20);
        }
    }
}
