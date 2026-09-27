<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\Shop;
use App\Models\User;

class CouponPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->shops()->exists();
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $this->belongs($user, $coupon->shop_id, false);
    }

    public function create(User $user, array $attributes = []): bool
    {
        return $this->belongs($user, $attributes['shop_id'] ?? null, true);
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $this->belongs($user, $coupon->shop_id, true);
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $this->belongs($user, $coupon->shop_id, true);
    }

    /**
     * A coupon may not be moved into a branch the actor does not manage.
     */
    public function changeShop(User $user, Coupon $coupon, ?int $targetShopId): bool
    {
        if (! $this->update($user, $coupon)) {
            return false;
        }

        return $targetShopId === null || $user->ownsShop(Shop::findOrFail($targetShopId));
    }

    private function belongs(User $user, ?int $shopId, bool $requiresOwnership): bool
    {
        if ($shopId === null) {
            return $user->isAdmin();
        }

        return $requiresOwnership
            ? $user->ownsShop(Shop::findOrFail($shopId))
            : $user->belongsToShop(Shop::findOrFail($shopId));
    }
}
