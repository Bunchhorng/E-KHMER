<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckoutProtection
{
    public static function ownerKey(?User $user, ?string $session): string
    {
        return hash('sha256', $user ? 'user:'.$user->id : 'guest:'.$session);
    }

    public static function payloadHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** Count attempts before request validation, including malformed submissions. */
    public function throttle(Request $request, ?User $user, ?string $session): void
    {
        $limiter = null;
        $seconds = max(1, (int) config('ecommerce.checkout.window_seconds'));
        // MySQL's cart uniqueness collation may equate case variants of a session.
        // Use the stored cart owner for the budget instead of allowing aliases.
        $limiterSession = $user === null && $session !== null
            ? (Cart::where('session_id', $session)->value('session_id') ?? $session) : $session;
        $owner = $user ? 'user:'.$user->id : 'guest:'.self::ownerKey(null, $limiterSession);
        $limits = [
            'ip' => ['checkout:ip:'.hash('sha256', (string) $request->ip()), config('ecommerce.checkout.ip_attempts')],
            'owner' => ['checkout:'.$owner, config('ecommerce.checkout.owner_attempts')],
        ];
        foreach ($limits as $type => [$key, $maximum]) {
            try {
                $limiter ??= new RateLimiter(Cache::store(config('ecommerce.checkout.limiter_store')));
                // Increment first: concurrent requests cannot all pass a read-then-hit check.
                $count = $limiter->hit($key, $seconds);
                $retryAfter = max(1, $limiter->availableIn($key));
            } catch (\Throwable $exception) {
                Log::warning('Checkout limiter unavailable', ['exception_type' => $exception::class]);
                throw new HttpResponseException(response()->json([
                    'code' => 'CHECKOUT_TEMPORARILY_UNAVAILABLE',
                    'message' => 'Checkout is temporarily unavailable. Your cart has been kept. Please try again shortly.',
                ], 503));
            }
            if ($count > max(1, (int) $maximum)) {
                self::logLimit($user, $session, $request, $type, $count);
                throw new HttpResponseException(response()->json([
                    'code' => 'CHECKOUT_RATE_LIMIT',
                    'message' => 'Please wait a moment before placing another order. Your cart has been kept.',
                ], 429, ['Retry-After' => (string) $retryAfter]));
            }
        }
    }

    /** Caller must hold this owner's unique cart row lock until checkout commits. */
    public function assertCapacity(Cart $cart, ?User $user): void
    {
        $query = Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->whereIn('payment_status', [Order::PAYMENT_UNPAID, 'partial'])
            ->where('placed_at', '>', now()->subMinutes((int) config('ecommerce.reservation_minutes', 15)))
            ->whereHas('payment', fn ($payment) => $payment->where('status', Payment::STATUS_PENDING)->lockForUpdate());
        if ($user !== null) {
            $query->where('user_id', $user->id);
        } else {
            $query->whereNull('user_id')->whereHas('payment', fn ($payment) => $payment
                ->where(fn ($owner) => $owner->where('provider_data->cart_id', $cart->id)
                    ->orWhere('provider_data->session_id', 'guest_'.md5((string) $cart->session_id)))
                ->lockForUpdate());
        }

        // A locking read sees the latest committed orders under MySQL REPEATABLE READ,
        // including a checkout that committed while this request waited for the cart.
        $count = $query->lockForUpdate()->pluck('id')->count();
        if ($count >= max(1, (int) config('ecommerce.checkout.active_limit'))) {
            self::logLimit($user, $cart->session_id, request(), 'active', $count);
            throw new HttpResponseException(response()->json([
                'code' => 'ACTIVE_CHECKOUT_LIMIT',
                'message' => 'Please complete or cancel an existing checkout before placing another order. Your cart has been kept.',
            ], 409));
        }
    }

    private static function logLimit(?User $user, ?string $session, Request $request, string $type, int $count): void
    {
        Log::notice('Checkout limit reached', [
            'user_id' => $user?->id,
            'guest_owner_hash' => $user === null ? self::ownerKey(null, $session) : null,
            'ip' => $request->ip(),
            'endpoint' => '/api/checkout',
            'limit_type' => $type,
            'count' => $count,
        ]);
    }
}
