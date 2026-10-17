<?php

use App\Jobs\OperationsHeartbeat;
use App\Models\BakongPayment;
use App\Models\Order;
use App\Services\BakongService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('payments:reconcile {--limit=25}', function (BakongService $bakong, CheckoutService $checkout) {
    if (! config('ecommerce.bakong_token')) {
        $this->comment('Bakong is not configured; reconciliation skipped.');

        return 0;
    }
    $ids = BakongPayment::where('status', 'pending')->where('created_at', '>', now()->subDays(7))
        ->orderBy('updated_at')->limit(max(1, min((int) $this->option('limit'), 100)))->pluck('order_id')->unique();
    $failed = 0;
    foreach ($ids as $id) {
        try {
            if ($order = Order::find($id)) {
                $bakong->verify($order, $checkout);
            }
        } catch (Throwable $exception) {
            report($exception);
            $failed++;
        } finally {
            BakongPayment::where('order_id', $id)->where('status', 'pending')->update(['updated_at' => now()]);
        }
    }
    $this->info('Reconciled '.$ids->count().' orders; '.$failed.' require another attempt.');

    return $failed ? 1 : 0;
})->purpose('Verify unsettled Bakong payments, including transfers received after cancellation.');

Artisan::command('ops:health {--require-heartbeats}', function () {
    try {
        DB::connection()->getPdo();
        Cache::put('ops:probe', 'ok', 10);
        if (Cache::get('ops:probe') !== 'ok') {
            throw new RuntimeException('Cache is unavailable.');
        }
        if ($this->option('require-heartbeats')) {
            foreach (['scheduler', 'worker'] as $service) {
                if ((int) Cache::get('ops:'.$service.'_heartbeat', 0) < now()->subMinutes(5)->timestamp) {
                    throw new RuntimeException($service.' heartbeat is stale.');
                }
            }
        }
        $this->info('Database, cache, and requested service checks passed.');

        return 0;
    } catch (Throwable $exception) {
        $this->error($exception->getMessage());

        return 1;
    }
})->purpose('Check database, cache, scheduler, and queue-worker readiness.');

Schedule::call(function () {
    Cache::put('ops:scheduler_heartbeat', now()->timestamp, 300);
    OperationsHeartbeat::dispatch();
})->everyMinute();
Schedule::command('payments:reconcile --limit=25')->everyMinute()->withoutOverlapping(10);

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Run via `php artisan schedule:work` (foreground, container friendly) or
| the system cron entry `* * * * * php artisan schedule:run >> /dev/null 2>&1`.
|
*/

Schedule::call(function () {
    app(CheckoutService::class)->expireStaleReservations();
})->everyMinute()
    ->name('checkout:expire-reservations')
    ->withoutOverlapping()
    ->onFailure(function (Throwable $e) {
        report($e);
    })
    ->onSuccess(function () {
        Log::channel('stack')->debug('checkout:expire-reservations completed');
    });
