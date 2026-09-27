<?php

namespace App\Policies;

use App\Models\Inventory;
use App\Models\Shop;
use App\Models\User;

class InventoryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * A staff member may view/adjust stock only in the branch they belong to.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->shops()->exists();
    }

    public function view(User $user, Inventory $inventory): bool
    {
        if ($inventory->shop_id === null) {
            return $user->isAdmin();
        }

        return $user->belongsToShop(Shop::findOrFail($inventory->shop_id));
    }

    /**
     * Adjusting stock (reserve/release/deduct/adjust) is a manager action.
     */
    public function update(User $user, Inventory $inventory): bool
    {
        if ($inventory->shop_id === null) {
            return $user->isAdmin();
        }

        return $user->ownsShop(Shop::findOrFail($inventory->shop_id));
    }
}
