<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Shop::orderBy('id')->get() as $index => $shop) {
            ShippingMethod::create([
                'shop_id' => $shop->id,
                'name' => $shop->name.' Standard Shipping',
                'code' => $index === 0 ? 'standard' : 'standard-'.strtolower($shop->code),
                'description' => 'Tracked delivery within Cambodia.',
                'price' => $index % 3 === 0 ? 0 : 2.50,
                'estimated_days_min' => 2,
                'estimated_days_max' => 5,
                'is_active' => true,
            ]);
        }
    }
}
