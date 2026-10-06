<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Database\Seeder;

class WishlistSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::where('email', 'customer@ekhmer.dev')->firstOrFail();
        $wishlist = Wishlist::create(['user_id' => $customer->id]);
        Product::limit(10)->get()->each(fn (Product $product) => WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => $product->id]));
    }
}
