<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessForecastRequest;
use App\Http\Resources\BusinessForecastResource;
use App\Models\Shop;
use App\Services\Analytics\BusinessForecastService;
use Illuminate\Support\Facades\Gate;

class BusinessForecastController extends Controller
{
    public function __construct(private BusinessForecastService $forecasts) {}

    public function seller(BusinessForecastRequest $request, Shop $shop): BusinessForecastResource
    {
        return $this->result($request, $shop);
    }

    public function admin(BusinessForecastRequest $request): BusinessForecastResource
    {
        $id = $request->validated('shop_id');

        return $this->result($request, $id ? Shop::findOrFail($id) : null);
    }

    private function result(BusinessForecastRequest $request, ?Shop $shop): BusinessForecastResource
    {
        Gate::authorize('business-analytics', [$shop]);

        return new BusinessForecastResource($this->forecasts->forecast($shop, $request->options(), $request->isMethod('post')));
    }
}
