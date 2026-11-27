<?php

namespace Database\Seeders;

use App\Services\Analytics\BusinessForecastService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Business tables in child-before-parent dependency order. */
    public const TABLES = [
        'business_expenses',
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
        Model::unguarded(function (): void {
            DB::transaction(function (): void {
                // Essential system configuration runs safely and idempotently in every environment.
                $this->call(SettingSeeder::class);

                // Development/testing demo marketplace data is strictly skipped outside local/testing.
                if (app()->environment(['local', 'testing'])) {
                    $this->call(DemoMarketplaceSeeder::class);
                }

                DB::afterCommit(static fn () => BusinessForecastService::invalidate());
            });
        });
    }
}
