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
        $variants = ProductVariant::orderBy('id')->get();
        foreach (User::orderBy('id')->get() as $index => $customer) {
            $variant = $variants[$index];
            $cart = Cart::create(['user_id' => $customer->id]);
            CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => ($index % 2) + 1]);
        }
    }
}
