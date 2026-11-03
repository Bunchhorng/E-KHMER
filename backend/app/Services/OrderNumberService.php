<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\ShopOrder;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderNumberService
{
    public const MAX_ATTEMPTS = 5;

    public function __construct(private OrderNumberGenerator $generator) {}

    public function createParent(array $attributes): Order
    {
        return $this->insert('orders', 'order_number', fn () => Order::create([
            ...$attributes, 'order_number' => $this->generator->next(),
        ]));
    }

    public function createShop(Order $parent, int $position, array $attributes): ShopOrder
    {
        return $this->insert('shop_orders', 'shop_order_number', fn (int $attempt) => ShopOrder::create([
            ...$attributes,
            'order_id' => $parent->id,
            'shop_order_number' => $this->generator->forShop($parent->order_number, $position, $attempt),
        ]));
    }

    /** Retry only the insert, leaving checkout's stock and financial work outside the loop. */
    private function insert(string $table, string $column, Closure $create): Order|ShopOrder
    {
        $requestKey = request()->header('Idempotency-Key');
        $context = [
            'table' => $table,
            'generator_strategy' => OrderNumberGenerator::STRATEGY,
            'checkout_reference' => (string) Str::uuid(),
            'idempotency_reference' => $requestKey === null ? null : hash('sha256', $requestKey),
        ];

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                // A savepoint rolls back this failed insert without restarting checkout.
                return DB::transaction(fn () => $create($attempt));
            } catch (UniqueConstraintViolationException $exception) {
                $numberConflict = $exception->index === $table.'_'.$column.'_unique'
                    || ($exception->index === null && $exception->columns === [$column]);
                if (! $numberConflict) {
                    throw $exception;
                }

                Log::info('order_number_collision', [...$context, 'attempt_count' => $attempt]);
            }
        }

        Log::error('order_number_retry_exhausted', [...$context, 'attempt_count' => self::MAX_ATTEMPTS]);

        throw new HttpResponseException(response()->json([
            'code' => 'ORDER_CREATION_UNAVAILABLE',
            'message' => "We couldn't create your order. Please try again.",
        ], 503));
    }
}
