<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;

class BusinessForecastPolicy
{
    public function view(User $user, ?Shop $shop = null): bool
    {
        return $user->isSuperAdmin() || ($shop !== null && $user->ownsShop($shop));
    }
}
