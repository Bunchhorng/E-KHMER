<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    public function run(): void
    {
        $shops = [
            ['E-KHMER Official Store', 'EKHMER', Shop::STATUS_ACTIVE, true], ['TechHub Phnom Penh', 'TECHHUB', Shop::STATUS_ACTIVE, false],
            ['Style House Cambodia', 'STYLEHSE', Shop::STATUS_ACTIVE, false], ['Glow & Go Beauty', 'GLOWGO', Shop::STATUS_ACTIVE, false],
            ['Home Living Store', 'HOMELIVE', Shop::STATUS_ACTIVE, false], ['Everyday Accessories', 'ACCESS', Shop::STATUS_ACTIVE, false],
            ['New Market Seller', 'NEWSELL', Shop::STATUS_PENDING, false], ['Paused Outlet', 'PAUSED', Shop::STATUS_SUSPENDED, false],
            ['Rejected Demo Store', 'REJECTED', Shop::STATUS_REJECTED, false], ['Seasonal Pop-up', 'POPUP', Shop::STATUS_PENDING, false],
        ];
        $seller = User::where('email', 'seller@ekhmer.dev')->firstOrFail();
        foreach ($shops as $index => [$name, $code, $status, $default]) {
            $shop = Shop::create([
                'name' => $name, 'code' => $code, 'slug' => str($name)->slug(), 'status' => $status, 'is_default' => $default,
                'rejection_reason' => $status === Shop::STATUS_REJECTED ? 'Business verification documents are incomplete.' : null,
                'branch_type' => $default ? 'platform' : 'storefront', 'description' => 'Demo marketplace shop '.$code,
                'email' => strtolower($code).'@ekhmer.dev', 'phone' => '+855 12 555 '.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'address_line' => 'Preah Monivong Boulevard', 'mall' => 'E-KHMER Marketplace', 'city' => 'Phnom Penh',
                'province' => 'Phnom Penh', 'postal_code' => '12000', 'country' => 'KH', 'commission_rate' => $default ? 0 : 10,
            ]);
            if (!$default) $shop->users()->attach($seller->id, ['role_in_shop' => 'owner', 'status' => 'active']);
        }
    }
}
