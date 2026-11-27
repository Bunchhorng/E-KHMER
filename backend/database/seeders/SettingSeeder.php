<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Essential platform settings safe to seed in every environment,
     * preserving existing administrator overrides.
     */
    public function run(): void
    {
        $essential = [
            'storeName' => 'E-KHMER',
            'supportEmail' => 'support@ekhmer.com',
            'supportPhone' => '+855 12 555 000',
            'storeAddress' => 'Phnom Penh, Cambodia',
            'currency' => 'USD',
            'locale' => 'en',
            'timezone' => 'Asia/Phnom_Penh',
            'lowStockThreshold' => '5',
            'emailOrderNotifications' => '1',
            'emailLowStockAlerts' => '1',
            'maintenanceMode' => '0',
        ];

        foreach ($essential as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * Local/testing metadata settings kept strictly separate from production settings.
     */
    public static function seedDemoMetadata(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $demo = [
            'demo.name' => 'E-KHMER Cambodian Marketplace Demo',
            'demo.version' => '2',
            'demo.currency' => 'USD',
            'demo.country' => 'KH',
            'demo.gatewayCalls' => '0',
        ];

        foreach ($demo as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
