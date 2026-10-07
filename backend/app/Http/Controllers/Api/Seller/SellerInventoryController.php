<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Api\Admin\AdminInventoryController;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminInventoryAdjustRequest;
use App\Models\Inventory;
use App\Models\Shop;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class SellerInventoryController extends Controller
{
    public function __construct(private AdminInventoryController $inventories, private InventoryService $inventoryService) {}

    public function index(Request $request, Shop $shop): mixed
    {
        return $this->inventories->index($request);
    }

    public function transactions(Request $request, Shop $shop, Inventory $inventory): mixed
    {
        return $this->inventories->transactions($request, $inventory);
    }

    public function adjust(AdminInventoryAdjustRequest $request, Shop $shop, Inventory $inventory): mixed
    {
        return $this->inventories->adjust($request, $inventory, $this->inventoryService);
    }
}
