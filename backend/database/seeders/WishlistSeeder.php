<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Database\Seeders\Concerns\EnsuresLocalDemoEnvironment;
use Illuminate\Database\Seeder;

class WishlistSeeder extends Seeder
{
    use EnsuresLocalDemoEnvironment;

    public function run(): void
    {
        $this->ensureLocalOrTestingEnvironment('Demo wishlists may only be seeded in local or testing environments.');

        $products = Product::active()->orderBy('id')->get();
        if ($products->isEmpty()) {
            return;
        }

        $customers = User::where('role', User::ROLE_CUSTOMER)
            ->orderBy('id')
            ->take(28)
            ->get();

        foreach ($customers as $index => $customer) {
            $wishlist = Wishlist::firstOrCreate(['user_id' => $customer->id]);
            if ($wishlist->items()->exists()) {
                continue;
            }

            $itemCount = $customer->email === 'customer@ekhmer.com' ? 6 : 2 + ($index % 3);
            for ($i = 0; $i < $itemCount; $i++) {
                $product = $products[($index * 5 + $i * 11) % $products->count()];
                WishlistItem::firstOrCreate([
                    'wishlist_id' => $wishlist->id,
                    'product_id' => $product->id,
                ]);
            }
        }
    }
}
