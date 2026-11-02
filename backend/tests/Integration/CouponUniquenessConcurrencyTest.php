<?php

namespace Tests\Integration;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Opt-in: real HTTP kernels and writes in the disposable F12 MySQL database. */
class CouponUniquenessConcurrencyTest extends TestCase
{
    private array $codes = [];

    private array $users = [];

    private array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || DB::getDriverName() !== 'mysql'
            || DB::getDatabaseName() !== 'ecommerce_f12_test'
            || config('database.connections.mysql.host') !== 'restore-db') {
            $this->markTestSkipped('Requires ecommerce_f12_test on the disposable restore-db service.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        Coupon::whereIn('code', $this->codes)->delete();
        foreach (User::whereIn('id', $this->users)->get() as $user) {
            $user->tokens()->delete();
            $user->delete();
        }
        parent::tearDown();
    }

    private function code(): string
    {
        $code = 'F12-'.Str::upper(Str::random(12));
        $this->codes[] = $code;

        return $code;
    }

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->users[] = $admin->id;

        return $admin;
    }

    private function worker(string $token, string $mode, string $code, ?int $id = null): array
    {
        $input = new InputStream;
        $process = new Process([PHP_BINARY, base_path('tests/Support/coupon-race-worker.php'), $mode, $code, (string) $id],
            base_path(), ['F12_TEST_TOKEN' => $token], timeout: 30);
        $process->setInput($input);
        $this->processes[] = $process;
        $process->start();
        $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'validated')),
            $process->getErrorOutput().' '.$process->getOutput());

        return [$process, $input];
    }

    private function finish(array $workers): array
    {
        foreach ($workers as [$process, $input]) {
            $input->write("go\n");
            $input->close();
        }
        $results = [];
        foreach ($workers as [$process]) {
            $this->assertSame(0, $process->wait(), $process->getErrorOutput());
            $output = $process->getOutput();
            $offset = strrpos($output, 'RESULT:');
            $this->assertNotFalse($offset, $output);
            $result = json_decode(substr($output, $offset + 7), true, 512, JSON_THROW_ON_ERROR);
            $this->assertArrayNotHasKey('exception', $result['body']);
            $this->assertArrayNotHasKey('trace', $result['body']);
            $this->assertStringNotContainsString('SQLSTATE', json_encode($result));
            $this->assertStringNotContainsString('coupons_code_unique', json_encode($result));
            $results[] = $result;
        }

        return $results;
    }

    public function test_two_prevalidated_creates_return_one_201_and_one_safe_422(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('F12 concurrency test')->plainTextToken;
        $code = $this->code();
        $a = $this->worker($token, 'create', $code);
        $b = $this->worker($token, 'create', ' '.strtolower($code).' ');

        $results = $this->finish([$a, $b]);

        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([201, 422], $statuses);
        $loser = collect($results)->firstWhere('status', 422);
        $this->assertSame(['The coupon code has already been taken.'], $loser['body']['errors']['code']);
        $this->assertSame(1, Coupon::where('code', $code)->count());
        $this->assertDatabaseCount('coupon_usages', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_two_prevalidated_updates_return_one_200_and_one_safe_422(): void
    {
        $token = $this->admin()->createToken('F12 concurrency test')->plainTextToken;
        $a = Coupon::factory()->create(['code' => $this->code()]);
        $b = Coupon::factory()->create(['code' => $this->code()]);
        $code = $this->code();
        $workerA = $this->worker($token, 'update', $code, $a->id);
        $workerB = $this->worker($token, 'update', $code, $b->id);

        $results = $this->finish([$workerA, $workerB]);

        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 422], $statuses);
        $this->assertSame(1, Coupon::where('code', $code)->count());
        $this->assertSame(1, Coupon::whereIn('code', [$a->code, $b->code])->count());
        $this->assertSame(['The coupon code has already been taken.'], collect($results)->firstWhere('status', 422)['body']['errors']['code']);
    }

    public function test_mysql_collation_rejects_a_legacy_lowercase_code_before_insert(): void
    {
        $admin = $this->admin();
        $code = $this->code();
        Coupon::factory()->create(['code' => strtolower($code)]);
        $attempts = 0;
        Coupon::creating(function () use (&$attempts): void {
            $attempts++;
        });

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/coupons', [
            'code' => $code, 'type' => 'fixed', 'value' => 10,
        ])->assertUnprocessable()->assertJsonPath('errors.code.0', 'The coupon code has already been taken.');

        $this->assertSame(0, $attempts);
        $this->assertSame(1, Coupon::where('code', $code)->count());
    }
}
