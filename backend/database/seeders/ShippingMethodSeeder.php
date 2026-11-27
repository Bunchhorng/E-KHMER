<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use App\Models\Shop;
use Database\Seeders\Concerns\EnsuresLocalDemoEnvironment;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    use EnsuresLocalDemoEnvironment;

    public function run(): void
    {
        $this->ensureLocalOrTestingEnvironment('Demo shipping methods may only be seeded in local or testing environments.');

        foreach (Shop::orderBy('id')->get() as $index => $shop) {
            $slugCode = strtolower($shop->code);
            $standardCode = $index === 0 ? 'standard' : 'standard-'.$slugCode;
            $expressCode = $index === 0 ? 'express' : 'express-'.$slugCode;

            ShippingMethod::firstOrCreate(
                ['code' => $standardCode],
                [
                    'shop_id' => $shop->id,
                    'name' => $shop->name.' Standard Delivery',
                    'description' => 'Tracked nationwide courier delivery across Cambodia (2–5 business days).',
                    'price' => $shop->is_default ? 0.00 : ($index % 2 === 0 ? 1.50 : 2.00),
                    'estimated_days_min' => 2,
                    'estimated_days_max' => 5,
                    'is_active' => true,
                ]
            );

            if ($shop->status === Shop::STATUS_ACTIVE) {
                ShippingMethod::firstOrCreate(
                    ['code' => $expressCode],
                    [
                        'shop_id' => $shop->id,
                        'name' => $shop->name.' Express Delivery',
                        'description' => 'Priority next-day delivery in '.$shop->province.' and major urban hubs.',
                        'price' => $shop->is_default ? 2.50 : 3.50,
                        'estimated_days_min' => 1,
                        'estimated_days_max' => 2,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
