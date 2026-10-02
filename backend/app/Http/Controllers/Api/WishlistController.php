<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $wishlist = $this->wishlistFor($request->user());

        $products = $wishlist->products()
            ->with(['brand', 'category', 'images', 'variants.inventory'])
            ->active()
            ->get();

        return ProductResource::collection($products);
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $wishlist = $this->wishlistFor($request->user());
        $wishlist->items()->firstOrCreate([
            'product_id' => (int) $request->product_id,
        ]);

        return $this->wishlistIds($request->user());
    }

    public function remove(Request $request, int $product)
    {
        $wishlist = $this->wishlistFor($request->user());
        $wishlist->items()->where('product_id', $product)->delete();

        return $this->wishlistIds($request->user());
    }

    protected function wishlistFor($user): Wishlist
    {
        return Wishlist::firstOrCreate(['user_id' => $user->id]);
    }

    protected function wishlistIds($user)
    {
        $wishlist = $this->wishlistFor($user);

        return response()->json([
            'data' => $wishlist->items()->pluck('product_id')->map(fn ($id) => (int) $id)->values(),
        ]);
    }
}
