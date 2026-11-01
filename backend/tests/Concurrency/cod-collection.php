<?php

/**
 * Real MySQL lock/uniqueness regression on the disposable restore-db service.
 * Creates a randomly named database, migrates it and drops only that database.
 * Run via Docker; see docs/F04-COD-COLLECTION-IMPLEMENTATION.md.
 */

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\CodCollectionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$worker = ($argv[1] ?? '') === '--worker';
$host = getenv('COD_MYSQL_HOST');
$password = getenv('COD_MYSQL_PASSWORD');
if ($host !== 'restore-db' || ! $password) {
    throw new RuntimeException('This check requires the disposable restore-db service and COD_MYSQL_PASSWORD.');
}
$database = $worker ? getenv('COD_CONCURRENCY_DATABASE') : 'cod_test_'.bin2hex(random_bytes(8));
if (! preg_match('/^cod_test_[a-f0-9]{16}$/', $database)) {
    throw new RuntimeException('Unsafe test database name.');
}
putenv('COD_CONCURRENCY_DATABASE='.$database);
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $host, 'DB_PORT' => '3306',
    'DB_DATABASE' => $database, 'DB_USERNAME' => 'root', 'DB_PASSWORD' => $password, 'DB_URL' => '',
    'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$server = $worker ? null : new PDO('mysql:host=restore-db;port=3306', 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (! $worker) {
    $server->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}

try {
    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== $database) {
        throw new RuntimeException('Refusing to run against a database other than the isolated test database.');
    }
    if ($worker) {
        echo "READY\n";
        fgets(STDIN);
        app(CodCollectionService::class)->collect(ShopOrder::findOrFail($argv[2]), User::findOrFail($argv[3]));
        echo "COLLECTED\n";
        exit(0);
    }
    if (Artisan::call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException('Isolated MySQL migrations failed: '.Artisan::output());
    }
    $fixture = function (): array {
        $seller = User::factory()->create();
        $order = Order::factory()->delivered()->create(['payment_status' => 'unpaid', 'subtotal' => 100, 'tax_amount' => 0, 'total' => 100]);
        Payment::create(['order_id' => $order->id, 'method' => 'cod', 'status' => 'pending', 'amount' => 100]);
        foreach ([40, 60] as $i => $amount) {
            $shop = Shop::factory()->create();
            $shop->users()->attach($seller, ['role_in_shop' => 'owner', 'status' => 'active']);
            $allocation = $order->shopOrders()->create(['shop_id' => $shop->id, 'shop_order_number' => $order->order_number.'-'.$i,
                'status' => 'delivered', 'subtotal' => $amount, 'discount_amount' => 0, 'tax_amount' => 0, 'shipping_amount' => 0, 'total' => $amount]);
            $allocation->shipment()->create(['order_id' => $order->id, 'status' => 'delivered', 'delivered_at' => now(), 'address_snapshot' => []]);
        }

        return [$order->load('shopOrders'), $seller];
    };
    $race = function (Order $order, User $seller, array $allocations): void {
        $processes = [];
        DB::beginTransaction();
        Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
        try {
            foreach ($allocations as $allocation) {
                $process = proc_open([PHP_BINARY, __FILE__, '--worker', (string) $allocation->id, (string) $seller->id],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                if (! is_resource($process)) {
                    throw new RuntimeException('Could not launch MySQL worker.');
                }
                stream_set_timeout($pipes[1], 30);
                $processes[] = [$process, $pipes];
                if (trim((string) fgets($pipes[1])) !== 'READY') {
                    throw new RuntimeException('MySQL worker did not become ready: '.stream_get_contents($pipes[2]));
                }
            }
            foreach ($processes as [, $pipes]) {
                fwrite($pipes[0], "GO\n");
                fclose($pipes[0]);
            }
            usleep(200000); // Both requests contend on the held InnoDB parent lock.
        } finally {
            DB::rollBack();
        }
        foreach ($processes as [$process, $pipes]) {
            $output = trim(stream_get_contents($pipes[1]));
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) !== 0 || $output !== 'COLLECTED') {
                throw new RuntimeException('MySQL worker failed: '.$errors.' '.$output);
            }
        }
    };
    [$order, $seller] = $fixture();
    $race($order, $seller, [$order->shopOrders[0], $order->shopOrders[0]]);
    if ($order->fresh()->payment_status !== 'partial' || PaymentTransaction::where('payment_id', $order->payment->id)->count() !== 1) {
        throw new RuntimeException('Concurrent duplicate collection was not safe.');
    }
    $race($order, $seller, [$order->shopOrders[0], $order->shopOrders[1]]);
    if ($order->fresh()->payment_status !== 'paid' || (float) $order->payment->transactions()->sum('amount') !== 100.0 || $order->payment->transactions()->count() !== 2) {
        throw new RuntimeException('Retry plus sibling collection did not reconcile.');
    }
    [$order, $seller] = $fixture();
    $race($order, $seller, [$order->shopOrders[0], $order->shopOrders[1]]);
    if ($order->fresh()->payment_status !== 'paid' || $order->payment->fresh()->status !== 'completed' || $order->payment->transactions()->count() !== 2) {
        throw new RuntimeException('Concurrent sibling collections did not aggregate.');
    }
    if (Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]) !== 0) {
        throw new RuntimeException('COD migration rollback failed: '.Artisan::output());
    }
    echo "PASS: MySQL duplicate collection, retry with sibling collection, concurrent sibling aggregation; amounts reconcile; migration and rollback succeed.\n";
} finally {
    if (! $worker) {
        DB::disconnect('mysql');
        $server->exec('DROP DATABASE `'.$database.'`');
    }
}
