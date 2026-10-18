<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'storeName' => 'E-KHMER',
            'supportEmail' => 'support@ekhmer.dev',
            'supportPhone' => '+855 12 555 000',
            'storeAddress' => 'Phnom Penh, Cambodia',
            'currency' => 'USD',
            'locale' => 'en',
            'timezone' => 'Asia/Phnom_Penh',
            'lowStockThreshold' => '5',
            'emailOrderNotifications' => '1',
            'emailLowStockAlerts' => '1',
            'maintenanceMode' => '0',
            // Dataset metadata, separate from the eleven supported application settings.
            'demo.name' => 'Twenty records per business table',
            'demo.version' => '1',
            'demo.recordsPerTable' => (string) DatabaseSeeder::RECORD_COUNT,
            'demo.currency' => 'USD',
            'demo.country' => 'KH',
            'demo.orderStatus' => 'delivered',
            'demo.paymentStatus' => 'partially_refunded',
            'demo.returnStatus' => 'refunded',
            'demo.gatewayCalls' => '0',
        ];

        foreach ($settings as $key => $value) {
            Setting::create(['key' => $key, 'value' => $value]);
        }
    }
}
