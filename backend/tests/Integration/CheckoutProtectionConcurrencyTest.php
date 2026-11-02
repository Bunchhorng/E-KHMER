<?php

namespace Tests\Integration;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutProtection;
use App\Services\CheckoutService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Consumer\CheckoutGuest;

/** Opt-in real HTTP kernels; never run against development MySQL or Redis. */
class CheckoutProtectionConcurrencyTest extends TestCase
{
    use CheckoutGuest;

    private array $workers = [];

    protected function setUp(): void
    {
        // Bypass Tests\TestCase's unconditional cache flush until isolation is
        // verified, so even a mistaken invocation cannot flush development Redis.
        parent::setUp();
        if (! app()->environment('testing') || DB::getDriverName() !== 'mysql'
            || DB::getDatabaseName() !== 'ecommerce_f13_test'
            || config('database.connections.mysql.host') !== 'restore-db'
            || config('database.redis.cache.host') !== 'checkout-test-redis'
            || config('database.redis.cache.url')
            || config('ecommerce.checkout.limiter_store') !== 'redis'
            || config('cache.default') !== 'array') {
            $this->markTestSkipped('Requires disposable F13 MySQL and Redis with array as the default cache.');
        }
        config(['ecommerce.payment_mode' => 'sandbox']);
        // Only the disposable service is flushed; child HTTP kernels share it.
        Cache::store('redis')->flush();
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as [$process]) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        // Fixtures remain only in the explicitly isolated tmpfs database until
        // its service is stopped; no deletes can reach developer/customer data.
        parent::tearDown();
    }

    private function fixture(bool $guest = false): array
    {
        $user = $guest ? null : User::factory()->create();
        $session = 'f13-'.Str::random(16);
        $cart = Cart::create($guest ? ['session_id' => $session] : ['user_id' => $user->id]);
        $this->productWithStock(100, 10);
        CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $this->variantId, 'quantity' => 1]);
        $payload = ['shipping_method_id' => $this->seedShipping(), 'payment_method' => 'card',
            'email' => 'f13@example.test', 'address' => $this->addressPayload()];

        return [$user, $session, $cart, $payload];
    }

    private function worker(?User $user, string $session, array $payload, string $key = '', bool $barrier = false): array
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/checkout-race-worker.php'), $key, $barrier ? 'cart' : 'none'],
            base_path(), ['F13_TEST_TOKEN' => $user?->createToken('F13 disposable test')->plainTextToken ?? '',
                'F13_TEST_SESSION' => $session, 'F13_TEST_PAYLOAD' => json_encode($payload, JSON_THROW_ON_ERROR)], timeout: 45);
        $input = new InputStream;
        $process->setInput($input);
        $process->start();
        $this->workers[] = [$process, $input];
        $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'ready')), $process->getErrorOutput());

        return [$process, $input];
    }

    private function finish(array $workers, bool $barrier = false): array
    {
        foreach ($workers as [$process, $input]) {
            $input->write("go\n");
        }
        if ($barrier) {
            foreach ($workers as [$process]) {
                $this->assertTrue($process->waitUntil(fn () => str_contains($process->getOutput(), 'locking')), $process->getOutput().$process->getErrorOutput());
            }
            // One worker holds the owner row while the other has attempted it.
            foreach ($workers as [$process, $input]) {
                $input->write("commit\n");
            }
        }
        $results = [];
        foreach ($workers as [$process, $input]) {
            $input->close();
            $this->assertSame(0, $process->wait(), $process->getErrorOutput().$process->getOutput());
            $output = $process->getOutput();
            $offset = strrpos($output, 'RESULT:');
            $this->assertNotFalse($offset, $output);
            $result = json_decode(substr($output, $offset + 7), true, 512, JSON_THROW_ON_ERROR);
            $this->assertArrayNotHasKey('exception', $result['body'], $output);
            $this->assertArrayNotHasKey('trace', $result['body']);
            $results[] = $result;
        }

        return $results;
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_two_requests_compete_for_last_owner_quota_slot(bool $guest): void
    {
        [$user, $session, $cart, $payload] = $this->fixture($guest);
        for ($i = 0; $i < 2; $i++) {
            app(CheckoutService::class)->begin([...$payload, 'cart' => $cart,
                'session_token' => $guest ? 'guest_'.md5($session) : null], $user);
        }
        $coupon = Coupon::factory()->create(['code' => 'F13-'.Str::upper(Str::random(10)), 'type' => 'fixed', 'value' => 1, 'per_user_limit' => null]);
        $payload['coupon_code'] = $coupon->code;
        $a = $this->worker($user, $session, $payload, 'quota-key-a', true);
        $b = $this->worker($user, $session, $payload, 'quota-key-b', true);
        $results = $this->finish([$a, $b], true);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([201, 409], $statuses, json_encode($results));
        $this->assertSame('ACTIVE_CHECKOUT_LIMIT', collect($results)->firstWhere('status', 409)['body']['code']);
        $this->assertSame(3, Order::whereHas('items', fn ($q) => $q->where('product_variant_id', $this->variantId))->count());
        $stock = Inventory::where('product_variant_id', $this->variantId)->sole();
        $this->assertSame(3, $stock->reserved_quantity);
        $this->assertSame(3, $stock->transactions()->count());
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertSame(1, $coupon->usages()->count());
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_concurrent_same_key_returns_one_order_and_one_hold(bool $guest): void
    {
        [$user, $session, $cart, $payload] = $this->fixture($guest);
        $a = $this->worker($user, $session, $payload, 'same-concurrent-key', true);
        $b = $this->worker($user, $session, $payload, 'same-concurrent-key', true);
        $results = $this->finish([$a, $b], true);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 201], $statuses);
        $this->assertSame($results[0]['body']['data']['order_number'], $results[1]['body']['data']['order_number']);
        // MySQL normalizes JSON object key order on storage; compare the entire
        // resource semantically while retaining ordered item array comparisons.
        $this->assertEquals($results[0]['body']['data'], $results[1]['body']['data']);
        $stock = Inventory::where('product_variant_id', $this->variantId)->sole();
        $this->assertSame(1, $stock->reserved_quantity);
        $this->assertSame(1, $stock->transactions()->count());
    }

    public function test_redis_owner_counter_is_shared_between_separate_http_processes(): void
    {
        [$user, $session, $cart, $payload] = $this->fixture();
        // The real framework limiter preloads the budget; new PHP processes must
        // observe it, while an exact committed retry must still be recoverable.
        $first = $this->finish([$this->worker($user, $session, $payload, 'recoverable-key')])[0];
        $this->assertSame(201, $first['status']);
        $limiter = new RateLimiter(Cache::store('redis'));
        for ($i = 0; $i < 7; $i++) {
            $limiter->hit('checkout:user:'.$user->id, 60);
        }
        $blocked = $this->finish([$this->worker($user, $session, $payload, 'new-blocked-key')])[0];
        $this->assertSame(429, $blocked['status']);
        $this->assertSame('CHECKOUT_RATE_LIMIT', $blocked['body']['code']);
        $this->assertGreaterThan(0, (int) $blocked['retry_after']);
        $retry = $this->finish([$this->worker($user, $session, $payload, 'recoverable-key')])[0];
        $this->assertSame(200, $retry['status']);
        $this->assertSame($first['body']['data']['order_number'], $retry['body']['data']['order_number']);
        $this->assertSame(1, Inventory::where('product_variant_id', $this->variantId)->sole()->reserved_quantity);
    }

    public function test_redis_ip_counter_blocks_a_new_guest_identity_across_processes(): void
    {
        [$user, $session, $cart, $payload] = $this->fixture(true);
        $limiter = new RateLimiter(Cache::store('redis'));
        $ipKey = 'checkout:ip:'.hash('sha256', '192.0.2.13');
        for ($i = 0; $i < 120; $i++) {
            $limiter->hit($ipKey, 60);
        }
        $result = $this->finish([$this->worker(null, $session, $payload)])[0];
        $this->assertSame(429, $result['status']);
        $this->assertSame(0, Inventory::where('product_variant_id', $this->variantId)->sole()->reserved_quantity);
        $this->assertSame(0, $cart->items()->sole()->variant->inventory->transactions()->count());
        $limiter->clear($ipKey); // Expiration/reset admits the next request.
        $result = $this->finish([$this->worker(null, $session, $payload)])[0];
        $this->assertSame(201, $result['status']);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_parallel_http_requests_share_the_last_redis_owner_attempt(bool $guest): void
    {
        [$user, $session, $cart, $payload] = $this->fixture($guest);
        $limiter = new RateLimiter(Cache::store('redis'));
        $key = $user ? 'checkout:user:'.$user->id : 'checkout:guest:'.CheckoutProtection::ownerKey(null, $session);
        for ($i = 0; $i < 7; $i++) {
            $limiter->hit($key, 60);
        }
        $a = $this->worker($user, $session, $payload, 'last-attempt-a');
        $b = $this->worker($user, $session, $payload, 'last-attempt-b');
        $results = $this->finish([$a, $b]);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([201, 429], $statuses);
        $this->assertSame(1, Inventory::where('product_variant_id', $this->variantId)->sole()->reserved_quantity);
    }

    public function test_guest_session_case_aliases_cannot_bypass_quota_or_owner_budget_on_mysql(): void
    {
        [$user, $session, $cart, $payload] = $this->fixture(true);
        $variants = [$session, strtoupper($session), strtolower($session)];
        foreach ($variants as $alias) {
            $result = $this->finish([$this->worker(null, $alias, $payload)])[0];
            $this->assertSame(201, $result['status']);
        }
        $result = $this->finish([$this->worker(null, strtoupper($session), $payload)])[0];
        $this->assertSame(409, $result['status']);
        $limiter = new RateLimiter(Cache::store('redis'));
        $this->assertSame(4, (int) $limiter->attempts('checkout:guest:'.CheckoutProtection::ownerKey(null, $session)));
        $this->assertSame(3, Inventory::where('product_variant_id', $this->variantId)->sole()->reserved_quantity);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_different_owners_cannot_reserve_the_same_last_unit(bool $guest): void
    {
        [$user, $session, $cart, $payload] = $this->fixture($guest);
        $stock = Inventory::where('product_variant_id', $this->variantId)->sole();
        $stock->update(['quantity' => 1]);
        $other = $guest ? null : User::factory()->create();
        $otherSession = 'other-'.Str::random(16);
        $otherCart = Cart::create($guest ? ['session_id' => $otherSession] : ['user_id' => $other->id]);
        CartItem::create(['cart_id' => $otherCart->id, 'product_variant_id' => $this->variantId, 'quantity' => 1]);
        $a = $this->worker($user, $session, $payload, '', true);
        $b = $this->worker($other, $otherSession, $payload, '', true);
        $results = $this->finish([$a, $b], true);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([201, 422], $statuses);
        $this->assertSame(1, $stock->fresh()->reserved_quantity);
        $this->assertSame(1, $stock->fresh()->quantity);
        $this->assertSame(1, $stock->transactions()->count());
    }
}
