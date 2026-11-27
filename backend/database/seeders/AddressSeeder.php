<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use Database\Seeders\Concerns\EnsuresLocalDemoEnvironment;
use Illuminate\Database\Seeder;

class AddressSeeder extends Seeder
{
    use EnsuresLocalDemoEnvironment;

    /** @var list<array{city: string, state: string, postal_code: string, district: string, streets: list<string>}> */
    private const PROVINCIAL_ZONES = [
        ['city' => 'Phnom Penh', 'state' => 'Phnom Penh', 'postal_code' => '120101', 'district' => 'Sangkat Boeng Keng Kang I, Khan Boeng Keng Kang', 'streets' => ['51', '63', '278', '306']],
        ['city' => 'Phnom Penh', 'state' => 'Phnom Penh', 'postal_code' => '120404', 'district' => 'Sangkat Toul Tom Poung I, Khan Chamkar Mon', 'streets' => ['155', '432', '456', '476']],
        ['city' => 'Phnom Penh', 'state' => 'Phnom Penh', 'postal_code' => '121501', 'district' => 'Sangkat Teuk Thla, Khan Sen Sok', 'streets' => ['1003', '1986', '2004', '371']],
        ['city' => 'Siem Reap', 'state' => 'Siem Reap', 'postal_code' => '171201', 'district' => 'Sangkat Svay Dangkum, Krong Siem Reap', 'streets' => ['Sivutha Blvd', 'Oum Chhay', 'Wat Bo Rd']],
        ['city' => 'Battambang', 'state' => 'Battambang', 'postal_code' => '021401', 'district' => 'Sangkat Svay Por, Krong Battambang', 'streets' => ['Street 1', 'Street 2.5', 'Street 3']],
        ['city' => 'Kampot', 'state' => 'Kampot', 'postal_code' => '070801', 'district' => 'Sangkat Kampong Kandal, Krong Kampot', 'streets' => ['Riverfront Rd', 'Ekareach St', 'Old Market St']],
        ['city' => 'Ta Khmau', 'state' => 'Kandal', 'postal_code' => '082501', 'district' => 'Sangkat Ta Khmau, Krong Ta Khmau', 'streets' => ['Street 21', 'Street 110', 'Riverside Rd']],
        ['city' => 'Sihanoukville', 'state' => 'Preah Sihanouk', 'postal_code' => '180301', 'district' => 'Sangkat Bei, Krong Preah Sihanouk', 'streets' => ['Ekareach Blvd', 'Serendipity Rd', '2 Thnou St']],
        ['city' => 'Kampong Cham', 'state' => 'Kampong Cham', 'postal_code' => '031001', 'district' => 'Sangkat Veal Vong, Krong Kampong Cham', 'streets' => ['Preah Monivong St', 'Mekong Riverside']],
        ['city' => 'Doun Kaev', 'state' => 'Takeo', 'postal_code' => '210801', 'district' => 'Sangkat Roka Knong, Krong Doun Kaev', 'streets' => ['National Rd 2', 'Market Blvd']],
    ];

    public function run(): void
    {
        $this->ensureLocalOrTestingEnvironment('Demo customer addresses may only be seeded in local or testing environments.');

        $zonesCount = count(self::PROVINCIAL_ZONES);

        foreach (User::orderBy('id')->get() as $index => $user) {
            if ($user->addresses()->exists()) {
                continue;
            }

            $zone = self::PROVINCIAL_ZONES[$index % $zonesCount];
            $street = $zone['streets'][$index % count($zone['streets'])];
            $houseNo = 14 + ($index * 7) % 210;

            Address::create([
                'user_id' => $user->id,
                'label' => 'Home',
                'full_name' => $user->name,
                'phone' => $user->phone ?: '+855 12 900 '.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'address_line1' => 'House #'.$houseNo.', Street '.$street,
                'address_line2' => $zone['district'],
                'city' => $zone['city'],
                'state' => $zone['state'],
                'postal_code' => $zone['postal_code'],
                'country' => 'KH',
                'is_default' => true,
            ]);

            // Give 40% of accounts a secondary delivery address (Work or Provincial Family).
            if ($index % 5 === 1 || $user->email === 'customer@ekhmer.com') {
                $altZone = self::PROVINCIAL_ZONES[($index + 3) % $zonesCount];
                Address::create([
                    'user_id' => $user->id,
                    'label' => $index % 2 === 0 ? 'Work' : 'Family',
                    'full_name' => $user->name,
                    'phone' => $user->phone ?: '+855 12 900 '.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'address_line1' => 'Office #'.(10 + $index).', '.$altZone['streets'][0],
                    'address_line2' => $altZone['district'],
                    'city' => $altZone['city'],
                    'state' => $altZone['state'],
                    'postal_code' => $altZone['postal_code'],
                    'country' => 'KH',
                    'is_default' => false,
                ]);
            }
        }
    }
}
