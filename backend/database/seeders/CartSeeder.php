<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\Concerns\EnsuresLocalDemoEnvironment;
use Illuminate\Database\Seeder;

class CartSeeder extends Seeder
{
    use EnsuresLocalDemoEnvironment;

    public function run(): void
    {
        $this->ensureLocalOrTestingEnvironment('Demo shopping carts may only be seeded in local or testing environments.');

        $variants = ProductVariant::with('product')
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->active())
            ->whereHas('inventory', fn ($q) => $q->whereRaw('quantity - reserved_quantity >= 4'))
            ->orderBy('id')
            ->get()
            ->groupBy('shop_id');

        if ($variants->isEmpty()) {
            return;
        }

        $shopKeys = $variants->keys()->values()->all();
        $customers = User::where('role', User::ROLE_CUSTOMER)
            ->where('email', '!=', 'seller@ekhmer.com')
            ->orderBy('id')
            ->take(18)
            ->get();

        foreach ($customers as $index => $customer) {
            $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
            if ($cart->items()->exists()) {
                continue;
            }

            // Give Demo Customer a 3-item multi-shop cart ready for testing checkout immediately.
            $lineCount = $customer->email === 'customer@ekhmer.com' ? 3 : 1 + ($index % 2);
            for ($i = 0; $i < $lineCount; $i++) {
                $shopId = $shopKeys[($index + $i * 2) % count($shopKeys)];
                $pool = $variants[$shopId];
                $variant = $pool[($index + $i) % $pool->count()];

                CartItem::firstOrCreate(
                    ['cart_id' => $cart->id, 'product_variant_id' => $variant->id],
                    ['quantity' => $i === 0 ? 1 : 2]
                );
            }
        }
    }
}
