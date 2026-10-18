<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public const RECORD_COUNT = 20;

    /** Business tables in child-before-parent deletion order. */
    public const TABLES = [
        'payment_refunds', 'order_returns', 'bakong_payments', 'checkout_attempts',
        'coupon_usages', 'tracking_events', 'notifications', 'reviews', 'shipments',
        'payment_transactions', 'payments', 'order_items', 'shop_orders', 'orders',
        'wishlist_items', 'wishlists', 'cart_items', 'carts',
        'inventory_transactions', 'inventories', 'variant_attribute_values',
        'product_variants', 'product_images', 'products', 'coupons', 'shipping_methods',
        'shop_users', 'shops', 'addresses', 'attribute_values', 'attributes',
        'brands', 'categories', 'support_requests', 'newsletter_subscriptions',
        'settings', 'users',
    ];

    public function run(): void
    {
        // Deletes are transactional and leave foreign-key checks enabled.
        // A failed seed restores the previous dataset on MySQL and SQLite.
        Model::unguarded(function (): void {
            DB::transaction(function (): void {
                // Authentication records must not retain references to removed accounts.
                foreach (['personal_access_tokens', 'password_reset_tokens', 'sessions'] as $table) {
                    DB::table($table)->delete();
                }
                foreach (self::TABLES as $table) {
                    DB::table($table)->delete();
                }

                $this->call([
                    UserSeeder::class,
                    ShopSeeder::class,
                    AddressSeeder::class,
                    SettingSeeder::class,
                    CatalogSeeder::class,
                    ShippingMethodSeeder::class,
                    CouponSeeder::class,
                    OrderSeeder::class,
                    ReviewSeeder::class,
                    WishlistSeeder::class,
                    CartSeeder::class,
                    NotificationSeeder::class,
                    EngagementSeeder::class,
                ]);
            });
        });
    }
}
