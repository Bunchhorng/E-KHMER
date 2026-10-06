<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        $shopId = Shop::where('is_default', true)->value('id');
        $methods = [
            ['Standard Shipping', 'standard', 0, 5, 7], ['Express Shipping', 'express', 9.99, 2, 3],
            ['Same Day Delivery', 'same-day', 19.99, 1, 1], ['Economy Delivery', 'economy', 2.5, 7, 10],
            ['Phnom Penh Express', 'pp-express', 4.99, 1, 2], ['Weekend Delivery', 'weekend', 7.99, 1, 3],
            ['Store Collection', 'collection', 0, 1, 2], ['Fragile Goods', 'fragile', 12.99, 3, 5],
            ['International Standard', 'intl-standard', 24.99, 7, 14], ['International Express', 'intl-express', 49.99, 3, 6],
        ];
        foreach ($methods as [$name, $code, $price, $min, $max]) {
            ShippingMethod::create(['shop_id' => $shopId, 'name' => $name, 'code' => $code, 'description' => $name.' demo method', 'price' => $price, 'estimated_days_min' => $min, 'estimated_days_max' => $max, 'is_active' => true]);
        }
    }
}
