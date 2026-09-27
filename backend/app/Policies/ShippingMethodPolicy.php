<?php

namespace App\Policies;

use App\Models\ShippingMethod;
use App\Models\Shop;
use App\Models\User;

class ShippingMethodPolicy
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

    public function view(User $user, ShippingMethod $method): bool
    {
        return $this->belongs($user, $method->shop_id, false);
    }

    public function create(User $user, array $attributes = []): bool
    {
        return $this->belongs($user, $attributes['shop_id'] ?? null, true);
    }

    public function update(User $user, ShippingMethod $method): bool
    {
        return $this->belongs($user, $method->shop_id, true);
    }

    public function delete(User $user, ShippingMethod $method): bool
    {
        return $this->belongs($user, $method->shop_id, true);
    }

    public function changeShop(User $user, ShippingMethod $method, ?int $targetShopId): bool
    {
        if (! $this->update($user, $method)) {
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
