<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\PaymentRefund;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use App\Models\Wishlist;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoMarketplaceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_seed_restores_the_existing_data_and_authentication_records(): void
    {
        Storage::fake('public');

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

    public function test_production_environment_seeds_only_essential_settings_and_rejects_demo_seeding(): void
    {
        Storage::fake('public');
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('db:seed', ['--force' => true])->run();

        $this->assertGreaterThanOrEqual(11, Setting::count());
        $this->assertDatabaseMissing('settings', ['key' => 'demo.name']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('shops', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('orders', 0);

        try {
            $this->artisan('db:seed', ['--class' => DemoMarketplaceSeeder::class, '--force' => true])->run();
            $this->fail('Direct demo seeding must be rejected in production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('local or testing environments', $exception->getMessage());
        }
    }

    public function test_cambodian_marketplace_seed_is_idempotent_preserves_existing_records_and_satisfies_invariants(): void
    {
        Storage::fake('public');

        // Pre-existing custom user, admin setting override, and login token must survive seeding.
        $existingCustomer = User::factory()->create(['email' => 'custom.buyer@ekhmer.test']);
        $existingCustomer->createToken('active-session');
        Setting::create(['key' => 'storeName', 'value' => 'E-KHMER Custom Override']);

        $this->seed();

        // Preserve existing records & overrides.
        $this->assertSame('E-KHMER Custom Override', Setting::get('storeName'));
        $this->assertDatabaseHas('users', ['id' => $existingCustomer->id, 'email' => 'custom.buyer@ekhmer.test']);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Core marketplace dataset counts.
        $this->assertSame(15, Category::count());
        $this->assertSame(22, Brand::count());
        $this->assertSame(18, Shop::count());
        $this->assertSame(14, Shop::active()->count());
        $this->assertSame(250, Product::withTrashed()->count());
        $this->assertSame(245, Product::where('is_active', true)->count());
        $this->assertGreaterThanOrEqual(400, ProductVariant::count());
        $this->assertGreaterThanOrEqual(50, User::where('role', User::ROLE_CUSTOMER)->count());
        $this->assertSame(1, User::where('role', User::ROLE_SUPER_ADMIN)->count());
        $this->assertGreaterThanOrEqual(30, Order::count());
        $this->assertGreaterThan(0, ShopOrder::count());
        $this->assertGreaterThan(0, Cart::count());
        $this->assertGreaterThan(0, Wishlist::count());

        // Documented accounts & Cambodian regional coverage.
        $this->assertDatabaseHas('users', ['email' => 'admin@ekhmer.com', 'role' => User::ROLE_SUPER_ADMIN]);
        $this->assertDatabaseHas('users', ['email' => 'customer@ekhmer.com', 'role' => User::ROLE_CUSTOMER]);
        $this->assertDatabaseHas('users', ['email' => 'seller@ekhmer.com', 'role' => User::ROLE_CUSTOMER]);
        $this->assertGreaterThanOrEqual(5, Shop::distinct('province')->count('province'));

        // Order statuses, COD vs Bakong, multi-shop orders, and return workflows.
        foreach ([Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_CANCELLED] as $status) {
            $this->assertTrue(Order::where('status', $status)->exists(), "Missing seeded order status: {$status}");
        }
        $this->assertTrue(Order::has('shopOrders', '>', 1)->exists(), 'Expected multi-shop demo orders.');
        $this->assertTrue(Order::whereHas('payment', fn ($q) => $q->where('method', 'cod'))->exists());
        $this->assertTrue(Order::whereHas('payment', fn ($q) => $q->where('method', 'bakong'))->exists());
        $this->assertTrue(ShopOrder::all()->contains(fn (ShopOrder $so) => $so->canCollectCod()), 'Expected an unpaid delivered COD allocation ready for collection testing.');
        foreach (['requested', 'approved', 'received', 'refunded'] as $retStatus) {
            $this->assertTrue(OrderReturn::where('status', $retStatus)->exists(), "Missing seeded return status: {$retStatus}");
        }

        // Order & allocation financial consistency + verified review alignment.
        foreach (Order::with(['shopOrders', 'payment'])->get() as $order) {
            if ($order->shopOrders->isNotEmpty()) {
                $this->assertEqualsWithDelta((float) $order->subtotal, (float) $order->shopOrders->sum('subtotal'), 0.02);
                $this->assertEqualsWithDelta((float) $order->total, (float) $order->shopOrders->sum('total'), 0.02);
            }
            $this->assertEqualsWithDelta((float) $order->total, (float) $order->payment->amount, 0.02);
        }

        foreach (Review::with(['order.items', 'product'])->get() as $review) {
            $this->assertTrue($review->verified);
            $this->assertSame(Order::STATUS_DELIVERED, $review->order->status);
            $this->assertSame($review->user_id, $review->order->user_id);
            $this->assertTrue($review->order->items->pluck('product_id')->contains($review->product_id));
            $this->assertSame($review->shop_id, $review->product->shop_id);
        }

        foreach (PaymentRefund::all() as $refund) {
            $return = OrderReturn::findOrFail($refund->order_return_id);
            $this->assertSame('refunded', $return->status);
            $this->assertEqualsWithDelta((float) $return->refund_amount, (float) $refund->amount, 0.01);
        }

        // Every seeded product image, category image, brand logo, and shop media file exists on disk.
        foreach (ProductImage::take(25)->get() as $img) {
            $relative = ltrim((string) (parse_url($img->image_path, PHP_URL_PATH) ?? ''), '/');
            $relative = Str::after($relative, 'storage/');
            Storage::disk('public')->assertExists($relative);
        }
        foreach (Shop::all() as $shop) {
            $logoPath = Str::after(ltrim((string) parse_url((string) $shop->logo, PHP_URL_PATH), '/'), 'storage/');
            $bannerPath = Str::after(ltrim((string) parse_url((string) $shop->banner, PHP_URL_PATH), '/'), 'storage/');
            Storage::disk('public')->assertExists($logoPath);
            Storage::disk('public')->assertExists($bannerPath);
        }

        // Verify idempotency: running seed a second time creates zero duplicates.
        $countsBefore = [];
        foreach (DatabaseSeeder::TABLES as $table) {
            $countsBefore[$table] = DB::table($table)->count();
        }
        $this->seed();
        foreach ($countsBefore as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "Table {$table} changed count after re-seeding.");
        }

        // Zero orphaned foreign keys across every table.
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
}
