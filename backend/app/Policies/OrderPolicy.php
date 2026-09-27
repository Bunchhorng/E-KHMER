<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * An order is a customer-facing aggregate that can span several branches, so it
 * carries no single shop_id. Visibility is therefore derived from its line
 * items: a branch manager may open an order that contains at least one of their
 * own lines, and only ever sees those lines (see OrderItemPolicy).
 */
class OrderPolicy
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

    public function view(User $user, Order $order): bool
    {
        $shopIds = $user->shops()
            ->wherePivot('status', 'active')
            ->pluck('shops.id')
            ->all();

        if ($shopIds === []) {
            return false;
        }

        return $order->items()->whereIn('shop_id', $shopIds)->exists();
    }

    /**
     * The set of order item ids a staff member is allowed to read on a given
     * order. Super admins receive an empty array meaning "no restriction".
     */
    public function visibleItemIds(User $user, Order $order): ?array
    {
        if ($user->isAdmin()) {
            return null;
        }

        $shopIds = $user->shops()
            ->wherePivot('status', 'active')
            ->pluck('shops.id')
            ->all();

        if ($shopIds === []) {
            return [];
        }

        return $order->items()->whereIn('shop_id', $shopIds)->pluck('id')->all();
    }
}
