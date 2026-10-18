<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, DatabaseSeeder::RECORD_COUNT) as $number) {
            Coupon::create([
                // Platform coupons can apply to products from any active shop.
                'shop_id' => null,
                'code' => $number === 1 ? 'WELCOME10' : 'DEMO'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'type' => 'percentage',
                'value' => 10,
                'min_order_amount' => 0,
                'max_discount_amount' => null,
                'usage_limit' => 100,
                'per_user_limit' => 1,
                'used_count' => 0,
                'starts_at' => now()->subYear(),
                'expires_at' => now()->addYear(),
                'is_active' => true,
            ]);
        }
    }
}
