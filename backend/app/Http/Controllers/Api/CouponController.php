<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CouponValidateRequest;
use App\Http\Resources\CouponResource;
use App\Services\CartService;
use App\Services\CouponService;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function __construct(protected CouponService $coupons, protected CartService $carts) {}

    public function validate(CouponValidateRequest $request)
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $sessionId = $request->header('X-Session-Id');
        if ($user === null && ($sessionId === null || trim($sessionId) === '')) {
            throw ValidationException::withMessages(['session' => ['A session identifier is required for guest carts.']]);
        }
        $cart = $this->carts->forUser($user, $sessionId)->load('items.variant.product');
        $coupon = $this->coupons->validate(
            $user,
            $request->input('code'),
            $cart
        );
        $eligibleSubtotal = $this->coupons->eligibleSubtotal($coupon, $cart);

        return (new CouponResource($coupon))
            ->additional([
                'subtotal' => $this->carts->totals($cart, $cart->items)['subtotal'],
                'eligible_subtotal' => $eligibleSubtotal,
                'discount_amount' => $this->coupons->discountFor($coupon, $eligibleSubtotal),
            ]);
    }
}
