<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShopResource;
use App\Models\Shop;
use Illuminate\Http\Request;

class SellerDashboardController extends Controller
{
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

        return [
            'data' => [
                'shop' => new ShopResource($shop),
                'metrics' => [
                    'products' => $shop->products()->count(),
                    'active_products' => $shop->products()->where('is_active', true)->count(),
                    'inactive_products' => $shop->products()->where('is_active', false)->count(),
                    'low_stock' => $shop->inventories()
                        ->whereRaw('quantity - reserved_quantity <= low_stock_threshold')
                        ->count(),
                ],
            ],
        ];
    }
}
