<?php

namespace App\Http\Middleware;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Shop;
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

        // Seller routes are never allowed to choose a different branch from the payload/query string.
        $request->merge(['shop_id' => $shop->id]);

        return $next($request);
    }
}
