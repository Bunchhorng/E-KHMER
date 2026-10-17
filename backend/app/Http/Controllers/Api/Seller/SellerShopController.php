<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminShippingMethodRequest;
use App\Http\Requests\SellerShopSettingsRequest;
use App\Http\Resources\ShippingMethodResource;
use App\Http\Resources\ShopResource;
use App\Models\ShippingMethod;
use App\Models\Shop;
use App\Services\MediaUploadService;
use App\Services\SellerDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class SellerShopController extends Controller
{
    public function __construct(private SellerDashboardService $dashboard) {}

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

        return ['data' => $this->dashboard->overview($shop)];
    }

    public function settings(Shop $shop): ShopResource
    {
        return new ShopResource($shop);
    }

    public function updateSettings(SellerShopSettingsRequest $request, Shop $shop, MediaUploadService $media): ShopResource
    {
        $data = $request->safe()->except(['logo_file', 'banner_file', 'remove_logo', 'remove_banner']);
        $uploaded = [];
        $remove = [];
        try {
            foreach (['logo', 'banner'] as $field) {
                if ($request->hasFile($field.'_file')) {
                    $uploaded[] = $data[$field] = $media->storeImage($request->file($field.'_file'), 'shops');
                    $remove[] = $shop->{$field};
                } elseif ($request->boolean('remove_'.$field)) {
                    $data[$field] = null;
                    $remove[] = $shop->{$field};
                }
            }
            DB::transaction(fn () => $shop->update($data));
        } catch (Throwable $exception) {
            foreach ($uploaded as $path) {
                $media->deleteImage($path);
            }
            throw $exception;
        }
        foreach ($remove as $path) {
            $media->deleteImage($path);
        }

        return new ShopResource($shop->fresh());
    }

    public function shippingMethods(Shop $shop)
    {
        return ShippingMethodResource::collection($shop->shippingMethods()->orderBy('price')->get());
    }

    public function storeShippingMethod(AdminShippingMethodRequest $request, Shop $shop)
    {
        $method = $shop->shippingMethods()->create(array_replace($request->validated(), ['shop_id' => $shop->id]));
        Cache::forget('shipping_methods:active');

        return (new ShippingMethodResource($method))->response()->setStatusCode(201);
    }

    public function updateShippingMethod(AdminShippingMethodRequest $request, Shop $shop, ShippingMethod $method): ShippingMethodResource
    {
        abort_unless((int) $method->shop_id === (int) $shop->id, 404);
        $method->update(array_replace($request->validated(), ['shop_id' => $shop->id]));
        Cache::forget('shipping_methods:active');

        return new ShippingMethodResource($method);
    }

    public function deleteShippingMethod(Shop $shop, ShippingMethod $method)
    {
        abort_unless((int) $method->shop_id === (int) $shop->id, 404);
        // Keep historical shipment references intact.
        $method->update(['is_active' => false]);
        Cache::forget('shipping_methods:active');

        return response()->json(['data' => ['message' => 'Shipping method disabled.']]);
    }
}
