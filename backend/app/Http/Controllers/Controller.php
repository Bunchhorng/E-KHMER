<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Provides $this->authorize() so the shop/branch policies are enforced at
     * the HTTP layer, not just in unit assertions.
     *
     * ValidatesRequests is intentionally NOT used here: the public coupon
     * validate endpoint is itself a controller action named validate(), which
     * would collide with the trait method. Those controllers already validate
     * through injected FormRequests.
     */
    use AuthorizesRequests;
}
