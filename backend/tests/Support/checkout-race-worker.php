<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

if (! $app->environment('testing') || DB::getDriverName() !== 'mysql'
    || DB::getDatabaseName() !== 'ecommerce_f13_test'
    || config('database.connections.mysql.host') !== 'restore-db'
    || config('database.redis.cache.host') !== 'checkout-test-redis'
    || config('database.redis.cache.url')) {
    throw new RuntimeException('Requires the disposable F13 MySQL and Redis services.');
}

$payload = json_decode((string) getenv('F13_TEST_PAYLOAD'), true, 512, JSON_THROW_ON_ERROR);
$token = getenv('F13_TEST_TOKEN');
$session = getenv('F13_TEST_SESSION');
$key = $argv[1] ?? '';
$barrier = ($argv[2] ?? '') === 'cart';
config(['app.debug' => true]);

if ($barrier) {
    $announced = false;
    $locked = false;
    // Announce before executing the first owner lock, including when it blocks.
    DB::connection()->beforeExecuting(function ($sql) use (&$announced): void {
        if (! $announced && str_contains($sql, 'from `carts`') && str_contains($sql, 'for update')) {
            $announced = true;
            echo "locking\n";
            flush();
        }
    });
    DB::listen(function ($query) use (&$locked): void {
        if (! $locked && str_contains($query->sql, 'from `carts`') && str_contains($query->sql, 'for update')) {
            $locked = true;
            echo "locked\n";
            flush();
            if (trim((string) fgets(STDIN)) !== 'commit') {
                throw new RuntimeException('Expected release of the cart lock barrier.');
            }
        }
    });
}

echo "ready\n";
flush();
if (trim((string) fgets(STDIN)) !== 'go') {
    throw new RuntimeException('Expected concurrent request start.');
}
$headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '192.0.2.13'];
if ($token) {
    $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
}
if ($session) {
    $headers['HTTP_X_SESSION_ID'] = $session;
}
if ($key !== '') {
    $headers['HTTP_IDEMPOTENCY_KEY'] = $key;
}
$request = Request::create('/api/checkout', 'POST', [], [], [], $headers, json_encode($payload, JSON_THROW_ON_ERROR));
$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request);
$kernel->terminate($request, $response);
echo 'RESULT:'.json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true),
    'retry_after' => $response->headers->get('Retry-After')], JSON_THROW_ON_ERROR)."\n";
