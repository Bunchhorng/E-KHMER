<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;

class AddressSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::where('email', 'customer@ekhmer.dev')->firstOrFail();
        foreach (range(1, 10) as $number) {
            Address::create([
                'user_id' => $customer->id,
                'label' => $number === 1 ? 'Default home' : 'Saved address '.$number,
                'full_name' => $customer->name,
                'phone' => $customer->phone,
                'address_line1' => $number.' Street '.(100 + $number),
                'city' => 'Phnom Penh', 'state' => 'Phnom Penh', 'postal_code' => '120'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'country' => 'KH', 'is_default' => $number === 1,
            ]);
        }
    }
}
