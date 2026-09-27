<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminShippingMethodRequest;
use App\Http\Resources\ShippingMethodResource;
use App\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminShippingMethodController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ShippingMethod::class);

        $query = ShippingMethod::query()->with('shop');

        if ($request->filled('shop_id')) {
            $request->shop_id === 'none'
                ? $query->whereNull('shop_id')
                : $query->where('shop_id', (int) $request->shop_id);
        }

        return ShippingMethodResource::collection($query->orderBy('price')->get());
    }

    public function store(AdminShippingMethodRequest $request)
    {
        $this->authorize('create', ShippingMethod::class, $request->validated());

        $method = ShippingMethod::create($request->validated());
        Cache::forget('shipping_methods:active');

        return (new ShippingMethodResource($method->load('shop')))->response()->setStatusCode(201);
    }

    public function update(AdminShippingMethodRequest $request, ShippingMethod $method)
    {
        $this->authorize('update', $method);

        if ($request->has('shop_id')) {
            $this->authorize('changeShop', $method, $request->input('shop_id') === null ? null : (int) $request->input('shop_id'));
        }

        $method->update($request->validated());
        Cache::forget('shipping_methods:active');

        return new ShippingMethodResource($method->load('shop'));
    }

    public function destroy(ShippingMethod $method)
    {
        $this->authorize('delete', $method);

        $method->delete();
        Cache::forget('shipping_methods:active');

        return response()->json(['data' => ['message' => 'Shipping method deleted.']]);
    }
}
