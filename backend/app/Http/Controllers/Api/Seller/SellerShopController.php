<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShopResource;
use App\Models\Shop;
use Illuminate\Http\Request;

class SellerShopController extends Controller
{
    public function index(Request $request)
    {
        $shops = $request->user()->shops()
            ->where('shops.status', Shop::STATUS_ACTIVE)
            ->wherePivot('status', 'active')
            ->wherePivotIn('role_in_shop', ['owner', 'manager'])
            ->orderBy('shops.name')
            ->get();

        return ShopResource::collection($shops);
    }

    public function dashboard(Request $request, Shop $shop)
    {
        abort_unless($request->user()->ownsShop($shop), 403, 'An active shop management membership is required.');

        return [
            'data' => [
                'shop' => new ShopResource($shop),
                'metrics' => [
                    'products' => $shop->products()->count(),
                    'active_products' => $shop->products()->where('is_active', true)->count(),
                    'inactive_products' => $shop->products()->where('is_active', false)->count(),
                    'low_stock' => $shop->inventories()->whereRaw('quantity - reserved_quantity <= low_stock_threshold')->count(),
                ],
            ],
        ];
    }
}
