<?php

use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || DB::getDriverName() !== 'mysql'
    || DB::getDatabaseName() !== 'ecommerce_f10_test'
    || config('database.connections.mysql.host') !== 'restore-db') {
    throw new RuntimeException('This worker requires the isolated F10 MySQL test database.');
}

$action = $argv[2] ?? '';
if (! in_array($action, ['approve', 'reject'], true) || ! ctype_digit($argv[1] ?? '')) {
    throw new InvalidArgumentException('Expected review ID and approve/reject.');
}
$review = Review::findOrFail((int) $argv[1]);
echo "ready\n";
flush();
$app->make(ReviewService::class)->{$action}($review);
echo "done\n";
