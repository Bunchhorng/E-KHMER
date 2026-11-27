<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Shop;
use Database\Seeders\Concerns\EnsuresLocalDemoEnvironment;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    use EnsuresLocalDemoEnvironment;

    public function run(): void
    {
        $this->ensureLocalOrTestingEnvironment('Demo coupons may only be seeded in local or testing environments.');

        $shops = Shop::pluck('id', 'code');

        $coupons = [
            // Platform-wide active promotions
            [
                'code' => 'WELCOME10',
                'shop_id' => null,
                'type' => 'percentage',
                'value' => 10.00,
                'min_order_amount' => 10.00,
                'max_discount_amount' => 25.00,
                'usage_limit' => 500,
                'per_user_limit' => 2,
                'used_count' => 0,
                'starts_at' => now()->subMonths(6),
                'expires_at' => now()->addYear(),
                'is_active' => true,
            ],
            [
                'code' => 'KHMERNEWYEAR',
                'shop_id' => null,
                'type' => 'percentage',
                'value' => 15.00,
                'min_order_amount' => 30.00,
                'max_discount_amount' => 35.00,
                'usage_limit' => 300,
                'per_user_limit' => 1,
                'used_count' => 0,
                'starts_at' => now()->subMonth(),
                'expires_at' => now()->addMonths(6),
                'is_active' => true,
            ],
            [
                'code' => 'EKSAVE5',
                'shop_id' => null,
                'type' => 'fixed',
                'value' => 5.00,
                'min_order_amount' => 25.00,
                'max_discount_amount' => null,
                'usage_limit' => 250,
                'per_user_limit' => 2,
                'used_count' => 0,
                'starts_at' => now()->subMonth(),
                'expires_at' => now()->addMonths(9),
                'is_active' => true,
            ],
            [
                'code' => 'PHNOMPENH20',
                'shop_id' => null,
                'type' => 'percentage',
                'value' => 20.00,
                'min_order_amount' => 60.00,
                'max_discount_amount' => 40.00,
                'usage_limit' => 150,
                'per_user_limit' => 1,
                'used_count' => 0,
                'starts_at' => now()->subWeeks(2),
                'expires_at' => now()->addMonths(6),
                'is_active' => true,
            ],
            // Shop-scoped coupons
            [
                'code' => 'KAMPOT10',
                'shop_id' => $shops['KAMPOT'] ?? null,
                'type' => 'percentage',
                'value' => 10.00,
                'min_order_amount' => 15.00,
                'max_discount_amount' => 15.00,
                'usage_limit' => 100,
                'per_user_limit' => 2,
                'used_count' => 0,
                'starts_at' => now()->subMonth(),
                'expires_at' => now()->addMonths(8),
                'is_active' => true,
            ],
            [
                'code' => 'ANGKOR15',
                'shop_id' => $shops['ANGKOR'] ?? null,
                'type' => 'percentage',
                'value' => 15.00,
                'min_order_amount' => 20.00,
                'max_discount_amount' => 30.00,
                'usage_limit' => 100,
                'per_user_limit' => 2,
                'used_count' => 0,
                'starts_at' => now()->subMonth(),
                'expires_at' => now()->addMonths(8),
                'is_active' => true,
            ],
            [
                'code' => 'TECHHUB15',
                'shop_id' => $shops['PPTECH'] ?? null,
                'type' => 'fixed',
                'value' => 15.00,
                'min_order_amount' => 80.00,
                'max_discount_amount' => null,
                'usage_limit' => 80,
                'per_user_limit' => 1,
                'used_count' => 0,
                'starts_at' => now()->subMonth(),
                'expires_at' => now()->addMonths(8),
                'is_active' => true,
            ],
            [
                'code' => 'ROASTERY3',
                'shop_id' => $shops['ROASTERY'] ?? null,
                'type' => 'fixed',
                'value' => 3.00,
                'min_order_amount' => 18.00,
                'max_discount_amount' => null,
                'usage_limit' => 120,
                'per_user_limit' => 2,
                'used_count' => 0,
                'starts_at' => now()->subMonth(),
                'expires_at' => now()->addMonths(8),
                'is_active' => true,
            ],
            // Boundary / negative test coupons
            [
                'code' => 'OLDPROMO2025',
                'shop_id' => null,
                'type' => 'percentage',
                'value' => 25.00,
                'min_order_amount' => 10.00,
                'max_discount_amount' => 50.00,
                'usage_limit' => 100,
                'per_user_limit' => 1,
                'used_count' => 12,
                'starts_at' => now()->subYear(),
                'expires_at' => now()->subMonth(),
                'is_active' => true,
            ],
            [
                'code' => 'LIMITED50',
                'shop_id' => null,
                'type' => 'fixed',
                'value' => 10.00,
                'min_order_amount' => 30.00,
                'max_discount_amount' => null,
                'usage_limit' => 5,
                'per_user_limit' => 1,
                'used_count' => 5,
                'starts_at' => now()->subMonth(),
                'expires_at' => now()->addMonths(3),
                'is_active' => true,
            ],
            [
                'code' => 'DRAFTCOUPON',
                'shop_id' => null,
                'type' => 'percentage',
                'value' => 12.00,
                'min_order_amount' => 20.00,
                'max_discount_amount' => null,
                'usage_limit' => 50,
                'per_user_limit' => 1,
                'used_count' => 0,
                'starts_at' => null,
                'expires_at' => now()->addMonths(3),
                'is_active' => false,
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::firstOrCreate(['code' => $coupon['code']], $coupon);
        }
    }
}
