<?php

use App\Models\Inventory;
use App\Services\InventoryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || DB::getDriverName() !== 'mysql'
    || DB::getDatabaseName() !== 'ecommerce_f11_test'
    || config('database.connections.mysql.host') !== 'restore-db') {
    throw new RuntimeException('This worker requires the isolated F11 MySQL test database.');
}

$action = $argv[2] ?? '';
if (! in_array($action, ['reserve', 'check'], true) || ! ctype_digit($argv[1] ?? '')) {
    throw new InvalidArgumentException('Expected inventory ID and reserve/check.');
}
$inventory = Inventory::findOrFail((int) $argv[1]);
echo "ready\n";
flush();
$service = $app->make(InventoryService::class);
if ($action === 'reserve') {
    if (! $service->reserve((int) $inventory->product_variant_id, 1)) {
        throw new RuntimeException('Expected reservation to succeed.');
    }
} else {
    $service->checkLowStock($inventory);
}
echo "done\n";
