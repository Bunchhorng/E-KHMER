<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\Shop;
use App\Models\User;

class ReviewPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * A review belongs to the branch selling the reviewed product, so a branch
     * manager may only moderate reviews for their own products.
     */
    public function view(User $user, Review $review): bool
    {
        return $this->belongs($user, $review->shop_id, false);
    }

    public function moderate(User $user, Review $review): bool
    {
        return $this->belongs($user, $review->shop_id, true);
    }

    public function delete(User $user, Review $review): bool
    {
        return $this->belongs($user, $review->shop_id, true);
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
