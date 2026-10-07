<?php

namespace App\Http\Middleware;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManagedShop
{
    public function handle(Request $request, Closure $next): Response
    {
        $shop = $request->route('shop');

        abort_unless($shop instanceof Shop && $request->user()?->ownsShop($shop), 403, 'An active shop management membership is required.');

        $product = $request->route('product');
        if ($product instanceof Product) {
            abort_unless((int) $product->shop_id === (int) $shop->id, 404);
        }

        $inventory = $request->route('inventory');
        if ($inventory instanceof Inventory) {
            abort_unless((int) $inventory->shop_id === (int) $shop->id, 404);
        }

        $shopOrder = $request->route('shopOrder');
        if ($shopOrder instanceof ShopOrder) {
            abort_unless((int) $shopOrder->shop_id === (int) $shop->id, 404);
        }

        // Seller routes are never allowed to choose a different branch from the payload/query string.
        $request->merge(['shop_id' => $shop->id]);
        // Controllers and policies can use this server-validated model as their
        // authorization context. It must never be derived from request input.
        $request->attributes->set('managed_shop', $shop);

        return $next($request);
    }
}
