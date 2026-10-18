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
        $products = Product::orderBy('id')->get();
        foreach (User::orderBy('id')->get() as $index => $customer) {
            $wishlist = Wishlist::create(['user_id' => $customer->id]);
            WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => $products[$index]->id]);
        }
    }
}
