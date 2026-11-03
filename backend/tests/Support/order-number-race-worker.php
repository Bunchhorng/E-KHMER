<?php

use App\Services\OrderNumberGenerator;
use App\Services\OrderNumberService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

if (! $app->environment('testing') || DB::getDriverName() !== 'mysql'
    || DB::getDatabaseName() !== 'ecommerce_f14_test'
    || config('database.connections.mysql.host') !== 'restore-db'
    || config('cache.default') !== 'array') {
    throw new RuntimeException('Requires the disposable F14 database and array cache.');
}

$candidate = $argv[2];
$app->instance(OrderNumberGenerator::class, new class($candidate, $argv[1] === 'insert') extends OrderNumberGenerator
{
    private bool $first = true;

    public function __construct(private string $candidate, private bool $insertBarrier) {}

    public function next(): string
    {
        if (! $this->first) {
            return parent::next();
        }
        $this->first = false;
        if ($this->insertBarrier) {
            echo "barrier\n";
            flush();
            if (trim((string) fgets(STDIN)) !== 'insert') {
                throw new RuntimeException('Expected insert barrier release.');
            }
        }

        return $this->candidate;
    }
});

if ($argv[1] === 'checkout') {
    $announced = false;
    DB::connection()->beforeExecuting(function (string $sql) use (&$announced): void {
        if (! $announced && str_contains($sql, 'from `carts`') && str_contains($sql, 'for update')) {
            $announced = true;
            echo "barrier\n";
            flush();
            if (trim((string) fgets(STDIN)) !== 'insert') {
                throw new RuntimeException('Expected checkout transaction barrier release.');
            }
        }
    });
}

echo "ready\n";
flush();
if (trim((string) fgets(STDIN)) !== 'go') {
    throw new RuntimeException('Expected concurrent start.');
}
$payload = json_decode((string) getenv('F14_TEST_PAYLOAD'), true, 512, JSON_THROW_ON_ERROR);
if ($argv[1] === 'insert') {
    $order = $app->make(OrderNumberService::class)->createParent($payload);
    $result = ['status' => 201, 'body' => ['data' => ['order_number' => $order->order_number]]];
} else {
    $request = Request::create('/api/checkout', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.getenv('F14_TEST_TOKEN'),
        'HTTP_IDEMPOTENCY_KEY' => getenv('F14_TEST_KEY'), 'REMOTE_ADDR' => '192.0.2.14',
    ], json_encode($payload, JSON_THROW_ON_ERROR));
    $kernel = $app->make(Kernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    $result = ['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)];
}
echo 'RESULT:'.json_encode($result, JSON_THROW_ON_ERROR)."\n";
