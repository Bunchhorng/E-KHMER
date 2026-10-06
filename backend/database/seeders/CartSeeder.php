<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;

class CartSeeder extends Seeder
{
    public function run(): void
    {
        $variants = ProductVariant::limit(10)->get();
        $customer = User::where('email', 'customer@ekhmer.dev')->firstOrFail();
        $cart = Cart::create(['user_id' => $customer->id]);
        foreach ($variants as $index => $variant) {
            CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => ($index % 2) + 1]);
        }
    }
}
