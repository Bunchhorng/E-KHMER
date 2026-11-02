<?php

use App\Models\Coupon;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

if (! $app->environment('testing') || DB::getDriverName() !== 'mysql'
    || DB::getDatabaseName() !== 'ecommerce_f12_test'
    || config('database.connections.mysql.host') !== 'restore-db') {
    throw new RuntimeException('This worker requires the isolated F12 MySQL test database.');
}

$mode = $argv[1] ?? '';
$code = $argv[2] ?? '';
$token = getenv('F12_TEST_TOKEN');
if (! in_array($mode, ['create', 'update'], true) || $code === '' || ! $token
    || ($mode === 'update' && ! ctype_digit($argv[3] ?? ''))) {
    throw new InvalidArgumentException('Expected create/update, coupon code, optional ID, and a test token.');
}
config(['app.debug' => true]);

// The HTTP request reaches this barrier only after request validation and policy
// authorization passed, but before the actual INSERT/UPDATE hits the index.
$barrier = function (): void {
    echo "validated\n";
    flush();
    if (trim((string) fgets(STDIN)) !== 'go') {
        throw new RuntimeException('Expected the test runner to release the write barrier.');
    }
};
if ($mode === 'create') {
    Coupon::creating($barrier);
} else {
    Coupon::updating($barrier);
}

$request = Request::create('/api/admin/coupons'.($mode === 'update' ? '/'.$argv[3] : ''),
    $mode === 'create' ? 'POST' : 'PUT', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    ], json_encode(['code' => $code, 'type' => 'percentage', 'value' => 20], JSON_THROW_ON_ERROR));
$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request);
$kernel->terminate($request, $response);
echo 'RESULT:'.json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)], JSON_THROW_ON_ERROR)."\n";
