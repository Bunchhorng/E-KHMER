<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\SellerDashboardService;
use Illuminate\Http\Request;

class SellerDashboardController extends Controller
{
    public function __construct(private SellerDashboardService $dashboard) {}

    public function show(Request $request)
    {
        $shop = $request->user()->shops()
            ->where('shops.status', Shop::STATUS_ACTIVE)
            ->wherePivot('status', 'active')
            ->wherePivotIn('role_in_shop', ['owner', 'manager'])
            ->first();

        if ($shop === null) {
            abort(403, 'An active shop membership is required.');
        }

        return ['data' => $this->dashboard->overview($shop)];
    }
}
