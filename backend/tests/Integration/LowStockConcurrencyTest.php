<?php

namespace Tests\Integration;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Opt-in, isolated MySQL only; never refreshes the development database. */
class LowStockConcurrencyTest extends TestCase
{
    private array $products = [];

    private array $users = [];

    private array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql' || DB::getDatabaseName() !== 'ecommerce_f11_test'
            || config('database.connections.mysql.host') !== 'restore-db') {
            $this->markTestSkipped('Requires ecommerce_f11_test on the disposable restore-db service.');
        }
        Setting::set('emailLowStockAlerts', '0');
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        Product::withTrashed()->whereIn('id', $this->products)->forceDelete();
        foreach (User::whereIn('id', $this->users)->get() as $user) {
            $user->notifications()->delete();
            $user->delete();
        }
        parent::tearDown();
    }

    private function stock(int $quantity): Inventory
    {
        $product = Product::factory()->withVariant(10, $quantity)->create();
        $this->products[] = $product->id;

        return $product->variants()->sole()->inventory;
    }

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->users[] = $admin->id;

        return $admin;
    }

    private function worker(Inventory $stock, string $action): Process
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/low-stock-worker.php'), (string) $stock->id, $action], base_path(), timeout: 30);
        $this->processes[] = $process;
        $process->start();
        $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'ready')), $process->getErrorOutput());

        return $process;
    }

    public function test_simultaneous_reservations_emit_one_event_and_other_inventory_is_not_blocked(): void
    {
        $admin = $this->admin();
        $stock = $this->stock(6);
        $other = $this->stock(10);
        DB::beginTransaction();
        Inventory::whereKey($stock->id)->lockForUpdate()->firstOrFail();
        $a = $this->worker($stock, 'reserve');
        $b = $this->worker($stock, 'reserve');
        $independent = $this->worker($other, 'reserve');
        $this->assertSame(0, $independent->wait(), $independent->getErrorOutput());
        $this->assertTrue($a->isRunning());
        $this->assertTrue($b->isRunning());
        DB::commit();
        $this->assertSame(0, $a->wait(), $a->getErrorOutput());
        $this->assertSame(0, $b->wait(), $b->getErrorOutput());

        $this->assertSame(4, $stock->fresh()->available_quantity);
        $this->assertSame(2, $stock->transactions()->where('type', 'reserve')->count());
        $this->assertSame(1, $admin->notifications()->where('type', LowStockNotification::class)->count());
        $this->assertNotNull($stock->fresh()->low_stock_notified_at);
    }

    public function test_competing_direct_checks_use_current_locked_rows_and_recovery_rearms(): void
    {
        $admin = $this->admin();
        $stock = $this->stock(4);
        DB::beginTransaction();
        Inventory::whereKey($stock->id)->lockForUpdate()->firstOrFail();
        $a = $this->worker($stock, 'check');
        $b = $this->worker($stock, 'check');
        $this->assertTrue($a->isRunning());
        $this->assertTrue($b->isRunning());
        DB::commit();
        $this->assertSame(0, $a->wait(), $a->getErrorOutput());
        $this->assertSame(0, $b->wait(), $b->getErrorOutput());
        $this->assertSame(1, $admin->notifications()->where('type', LowStockNotification::class)->count());

        app(InventoryService::class)->adjust((int) $stock->product_variant_id, 10);
        $this->assertNull($stock->fresh()->low_stock_notified_at);
        app(InventoryService::class)->adjust((int) $stock->product_variant_id, 4);
        $this->assertSame(2, $admin->notifications()->where('type', LowStockNotification::class)->count());
    }
}
