<?php

namespace Tests\Integration;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\OrderNumberService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Consumer\CheckoutGuest;

class OrderNumberConcurrencyTest extends TestCase
{
    use CheckoutGuest;

    private array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || DB::getDriverName() !== 'mysql'
            || DB::getDatabaseName() !== 'ecommerce_f14_test'
            || config('database.connections.mysql.host') !== 'restore-db'
            || config('cache.default') !== 'array') {
            $this->markTestSkipped('Requires ecommerce_f14_test on disposable restore-db with array cache.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        parent::tearDown();
    }

    public function test_child_number_conflict_recovers_using_the_real_mysql_unique_index(): void
    {
        $existing = Order::factory()->create();
        $parent = Order::factory()->create();
        $shop = Shop::factory()->create();
        $occupied = ShopOrder::create(['order_id' => $existing->id, 'shop_id' => $shop->id,
            'shop_order_number' => $parent->order_number.'-1']);

        $child = app(OrderNumberService::class)->createShop($parent, 1, ['shop_id' => $shop->id]);

        $this->assertSame($parent->id, $child->order_id);
        $this->assertSame($shop->id, $child->shop_id);
        $this->assertMatchesRegularExpression('/^'.preg_quote($parent->order_number, '/').'-1-[0-9]{12}$/', $child->shop_order_number);
        $this->assertSame($parent->order_number.'-1', $occupied->fresh()->shop_order_number);
        $this->assertSame(1, $parent->shopOrders()->count());
    }

    #[TestWith([2, 'checkout'])]
    #[TestWith([20, 'insert'])]
    public function test_simultaneous_creations_with_the_same_first_candidate_persist_distinct_numbers(int $count, string $mode): void
    {
        $run = Str::random(12);
        $candidate = 'SV-F14-'.$run;
        $workers = [];
        $users = [];
        $stocks = [];
        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->create();
            $users[] = $user->id;
            $payload = ['user_id' => $user->id, 'shipping_address' => $this->addressPayload(), 'subtotal' => 10, 'total' => 11];
            if ($mode === 'checkout') {
                $product = Product::factory()->withVariant(10, 100)->create(['shop_id' => Shop::factory()->create()->id]);
                $variant = $product->variants()->sole();
                $stocks[] = $variant->id;
                $cart = Cart::create(['user_id' => $user->id]);
                CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);
                $payload = ['shipping_method_id' => $this->seedShipping(), 'payment_method' => 'cod', 'address' => $this->addressPayload()];
            }
            $input = new InputStream;
            $process = new Process([PHP_BINARY, base_path('tests/Support/order-number-race-worker.php'), $mode, $candidate], base_path(), [
                'F14_TEST_TOKEN' => $user->createToken('F14 disposable test')->plainTextToken,
                'F14_TEST_KEY' => 'f14-'.$run.'-'.$i,
                'F14_TEST_PAYLOAD' => json_encode($payload, JSON_THROW_ON_ERROR),
            ], timeout: 90);
            $process->setInput($input);
            $process->start();
            $this->processes[] = $process;
            $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'ready')), $process->getErrorOutput());
            $workers[] = [$process, $input];
        }

        foreach ($workers as [$process, $input]) {
            $input->write("go\n");
        }
        // Checkouts overlap before owner locks; insert-only workers meet at the actual number allocation.
        foreach ($workers as [$process]) {
            $this->assertTrue($process->waitUntil(fn () => str_contains($process->getOutput(), 'barrier')), $process->getOutput().$process->getErrorOutput());
        }
        foreach ($workers as [$process, $input]) {
            $input->write("insert\n");
            $input->close();
        }

        $numbers = [];
        foreach ($workers as [$process]) {
            $this->assertSame(0, $process->wait(), $process->getErrorOutput().$process->getOutput());
            $output = $process->getOutput();
            $offset = strrpos($output, 'RESULT:');
            $this->assertNotFalse($offset, $output);
            $result = json_decode(substr($output, $offset + 7), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(201, $result['status'], $output);
            $this->assertArrayNotHasKey('exception', $result['body']);
            $numbers[] = $result['body']['data']['order_number'];
        }

        $this->assertCount($count, array_unique($numbers));
        $this->assertSame($count, Order::whereIn('user_id', $users)->count());
        $this->assertSame($count, Order::whereIn('user_id', $users)->distinct()->count('order_number'));
        $this->assertSame(1, Order::where('order_number', $candidate)->count());
        if ($mode === 'checkout') {
            $orders = Order::whereIn('user_id', $users)->pluck('id');
            foreach (['payments', 'shop_orders', 'shipments', 'order_items', 'checkout_attempts'] as $table) {
                $this->assertSame($count, DB::table($table)->whereIn('order_id', $orders)->count());
            }
            foreach (Inventory::whereIn('product_variant_id', $stocks)->get() as $stock) {
                $this->assertSame(100, $stock->quantity);
                $this->assertSame(2, $stock->reserved_quantity);
                $this->assertSame(1, $stock->transactions()->count());
            }
        }
    }
}
