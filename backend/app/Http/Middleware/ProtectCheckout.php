<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Requests\CheckoutRequest;
use App\Models\CheckoutAttempt;
use App\Services\CheckoutProtection;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProtectCheckout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $session = $request->header('X-Session-Id');
        $validSession = is_string($session) && trim($session) !== '' && strlen($session) <= 255;
        $protection = app(CheckoutProtection::class);

        // Only an exact, validated, previously committed owner-scoped retry bypasses
        // throttling. Changed or malformed payloads still consume the attempt budget.
        $key = $request->header('Idempotency-Key');
        if (($user !== null || $validSession) && is_string($key) && preg_match('/^[A-Za-z0-9_-]{8,80}$/', $key)) {
            $attempt = CheckoutAttempt::where('owner_key', CheckoutProtection::ownerKey($user, $session))
                ->where('request_key', $key)->whereNotNull('order_id')->first();
            if ($attempt !== null) {
                $exact = false;
                try {
                    $validated = app(CheckoutRequest::class)->validated();
                    $exact = hash_equals($attempt->payload_hash, CheckoutProtection::payloadHash($validated));
                } catch (ValidationException $exception) {
                    $protection->throttle($request, $user, $validSession ? $session : null);
                    throw $exception;
                }
                if ($exact) {
                    return $next($request);
                }
            }
        }

        $protection->throttle($request, $user, $validSession ? $session : null);
        if ($user === null && ! $validSession) {
            throw ValidationException::withMessages(['session' => 'A valid session identifier is required for guest checkout.']);
        }

        return $next($request);
    }
}
