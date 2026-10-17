<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Controller;
use App\Models\OrderReturn;
use App\Models\Shop;
use App\Services\ReturnService;
use Illuminate\Http\Request;

class SellerReturnController extends Controller
{
    public function __construct(private ReturnController $returns) {}

    public function index(Request $request, Shop $shop)
    {
        return $this->returns->index($request);
    }

    public function transition(Request $request, Shop $shop, OrderReturn $orderReturn, ReturnService $returns)
    {
        return $this->returns->transition($request, $orderReturn, $returns);
    }

    public function photo(Request $request, Shop $shop, OrderReturn $orderReturn, int $index)
    {
        return $this->returns->photo($request, $orderReturn, $index);
    }
}
