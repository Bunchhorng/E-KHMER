<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CouponValidateRequest;
use App\Http\Resources\CheckoutQuoteResource;
use App\Http\Resources\CouponResource;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function __construct(protected CheckoutService $checkout, protected CartService $carts) {}

    public function validate(CouponValidateRequest $request)
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $sessionId = $request->header('X-Session-Id');
        if ($user === null && ($sessionId === null || trim($sessionId) === '')) {
            throw ValidationException::withMessages(['session' => ['A session identifier is required for guest carts.']]);
        }
        $cart = $this->carts->forUser($user, $sessionId);
        $quote = $this->checkout->quote($cart, ['coupon_code' => $request->input('code')], $user);

        return (new CouponResource($quote['coupon']))
            ->additional([
                'subtotal' => $quote['subtotal'], 'eligible_subtotal' => $quote['eligible_subtotal'],
                'discount_amount' => $quote['discount_amount'], 'raw_discount' => $quote['raw_discount'],
                'quote' => (new CheckoutQuoteResource($quote))->resolve($request),
            ]);
    }
}
