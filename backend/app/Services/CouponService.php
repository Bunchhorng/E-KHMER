<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function validate(?User $user, string $code, float $subtotal): Coupon
    {
        $coupon = Coupon::query()
            ->whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($code))])
            ->first();

        if ($coupon === null) {
            throw ValidationException::withMessages([
                'code' => ['This coupon is not valid.'],
            ]);
        }

        $this->assertRedeemable($coupon, $user, $subtotal);

        return $coupon;
    }

    /**
     * Create one redemption while holding the coupon row lock. This is called
     * inside checkout's order transaction so usage limits cannot be exceeded
     * by two simultaneous orders that both saw the last remaining use.
     */
    public function applyUsage(Coupon $coupon, Order $order, ?User $user): void
    {
        $lockedCoupon = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);

        if (CouponUsage::where('order_id', $order->id)->exists()) {
            return;
        }

        $this->assertRedeemable($lockedCoupon, $user, (float) $order->subtotal);
        $lockedCoupon->increment('used_count');

        $lockedCoupon->usages()->create([
            'coupon_id' => $lockedCoupon->id,
            'user_id' => $user?->id,
            'order_id' => $order->id,
            'redeemed_at' => now(),
        ]);
    }

    private function assertRedeemable(Coupon $coupon, ?User $user, float $subtotal): void
    {
        if (! $coupon->is_active) {
            throw ValidationException::withMessages([
                'code' => ['This coupon is not valid.'],
            ]);
        }

        if ($coupon->expires_at !== null && !$coupon->expires_at->isFuture()) {
            throw ValidationException::withMessages([
                'code' => ['This coupon has expired.'],
            ]);
        }

        if ($coupon->starts_at !== null && $coupon->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'code' => ['This coupon is not yet active.'],
            ]);
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw ValidationException::withMessages([
                'code' => ['This coupon has reached its usage limit.'],
            ]);
        }

        if ($coupon->min_order_amount !== null && (float) $coupon->min_order_amount > $subtotal) {
            throw ValidationException::withMessages([
                'code' => ['This coupon requires a minimum order amount of ' . number_format((float) $coupon->min_order_amount, 2) . '.'],
            ]);
        }

        if ($user !== null && $coupon->per_user_limit !== null) {
            $used = $coupon->usages()->where('user_id', $user->id)->count();
            if ($used >= $coupon->per_user_limit) {
                throw ValidationException::withMessages([
                    'code' => ['You have already used this coupon.'],
                ]);
            }
        }

    }

    public function discountFor(Coupon $coupon, float $subtotal): float
    {
        if ($coupon->type === 'percentage') {
            $discount = round($subtotal * (float) $coupon->value / 100, 2);
            if ($coupon->max_discount_amount !== null) {
                $discount = min($discount, (float) $coupon->max_discount_amount);
            }

            return min($discount, $subtotal);
        }

        return min((float) $coupon->value, $subtotal);
    }

    /**
     * Return any coupon usage recorded for an order back to the pool. Called
     * when an order is cancelled, refunded, or its reservation is released, so
     * abandoned checkouts do not permanently burn usage capacity.
     */
    public function releaseUsage(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $usages = CouponUsage::query()->where('order_id', $order->id)->get();

            if ($usages->isEmpty()) {
                return;
            }

            $couponIds = $usages->pluck('coupon_id')->unique();

            $usages->each->delete();

            foreach ($couponIds as $couponId) {
                $coupon = Coupon::query()->lockForUpdate()->find($couponId);

                if ($coupon === null) {
                    continue;
                }

                $released = $usages->where('coupon_id', $couponId)->count();
                $coupon->used_count = max((int) $coupon->used_count - $released, 0);
                $coupon->save();
            }
        });
    }
}
