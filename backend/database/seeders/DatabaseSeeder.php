<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Model::unguard();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ($this->truncateOrder() as $table) {
            DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->call([
            UserSeeder::class,
            ShopSeeder::class,
            AddressSeeder::class,
            CatalogSeeder::class,
            ShippingMethodSeeder::class,
            CouponSeeder::class,
            OrderSeeder::class,
            ReviewSeeder::class,
            WishlistSeeder::class,
            CartSeeder::class,
            NotificationSeeder::class,
        ]);

        Model::reguard();
    }

    /**
     * Tables purged on every run, in FK-safe dependency order.
     *
     * @return string[]
     */
    private function truncateOrder(): array
    {
        return [
            'coupon_usages',
            'tracking_events',
            'notifications',
            'reviews',
            'shipments',
            'payment_transactions',
            'payments',
            'shop_orders',
            'order_items',
            'orders',
            'wishlist_items',
            'wishlists',
            'cart_items',
            'carts',
            'inventory_transactions',
            'inventories',
            'variant_attribute_values',
            'product_images',
            'product_variants',
            'products',
            'coupons',
            'shipping_methods',
            'shop_users',
            'shops',
            'addresses',
            'attribute_values',
            'attributes',
            'brands',
            'categories',
            'users',
        ];
    }
}
