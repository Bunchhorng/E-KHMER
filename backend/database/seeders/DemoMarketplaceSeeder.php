<?php

namespace Database\Seeders;

use App\Services\Analytics\BusinessForecastService;
use Database\Seeders\Concerns\EnsuresLocalDemoEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the complete Cambodian multi-vendor marketplace demo dataset
 * in strict foreign-key dependency order. Only runs in local/testing.
 */
class DemoMarketplaceSeeder extends Seeder
{
    use EnsuresLocalDemoEnvironment;

    public function run(): void
    {
        $this->ensureLocalOrTestingEnvironment('Demo marketplace data may only be seeded in local or testing environments.');

        SettingSeeder::seedDemoMetadata();

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
            EngagementSeeder::class,
        ]);

        DB::afterCommit(static fn () => BusinessForecastService::invalidate());
    }
}
