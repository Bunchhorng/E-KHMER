<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use Illuminate\Http\Request;

class AdminCouponController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Coupon::class);

        $query = Coupon::query()->with('shop');

        if ($request->filled('shop_id')) {
            $request->shop_id === 'none'
                ? $query->whereNull('shop_id')
                : $query->where('shop_id', (int) $request->shop_id);
        }

        $paginator = $query->orderByDesc('id')->paginate(15);

        return [
            'data' => CouponResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function store(AdminCouponRequest $request)
    {
        $this->authorize('create', Coupon::class, $request->validated());

        $coupon = Coupon::create($request->validated());

        return (new CouponResource($coupon))->response()->setStatusCode(201);
    }

    public function update(AdminCouponRequest $request, Coupon $coupon)
    {
        $this->authorize('update', $coupon);

        if ($request->has('shop_id')) {
            $this->authorize('changeShop', $coupon, $request->input('shop_id') === null ? null : (int) $request->input('shop_id'));
        }

        $coupon->update($request->validated());

        return new CouponResource($coupon);
    }

    public function destroy(Coupon $coupon)
    {
        $this->authorize('delete', $coupon);

        $coupon->delete();

        return response()->json(['data' => ['message' => 'Coupon deleted.']]);
    }
}
