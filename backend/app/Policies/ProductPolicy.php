<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;

class ProductPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Any authenticated user belonging to at least one branch may browse a
     * branch-scoped product list; super admins always may.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->shops()->exists();
    }

    /**
     * A product may only be created by a super admin, or by a manager/owner of
     * the branch it is being assigned to.
     */
    public function create(User $user, array $attributes = []): bool
    {
        $shopId = $attributes['shop_id'] ?? null;

        if ($shopId === null) {
            return $user->isAdmin();
        }

        return $user->ownsShop(Shop::findOrFail($shopId));
    }

    /**
     * Reading a product follows the same branch scoping as editing it.
     */
    public function view(User $user, Product $product): bool
    {
        $shopId = $product->shop_id;

        if ($shopId === null) {
            return $user->isAdmin();
        }

        return $user->belongsToShop(Shop::findOrFail($shopId));
    }

    /**
     * Only a manager/owner of the product's branch may edit a product.
     */
    public function update(User $user, Product $product): bool
    {
        $shopId = $product->shop_id;

        if ($shopId === null) {
            return $user->isAdmin();
        }

        return $user->ownsShop(Shop::findOrFail($shopId));
    }

    /**
     * Editing a product may not move it into a branch the actor does not manage.
     */
    public function changeShop(User $user, Product $product, ?int $targetShopId): bool
    {
        if (! $this->update($user, $product)) {
            return false;
        }

        if ($targetShopId === null) {
            return true;
        }

        return $user->ownsShop(Shop::findOrFail($targetShopId));
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    public function restore(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}
