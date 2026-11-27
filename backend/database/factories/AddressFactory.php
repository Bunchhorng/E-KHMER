<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /** @var list<array{city: string, state: string, postal_code: string, district: string}> */
    private const CAMBODIAN_LOCATIONS = [
        ['city' => 'Phnom Penh', 'state' => 'Phnom Penh', 'postal_code' => '120101', 'district' => 'Sangkat Boeng Keng Kang I, Khan Boeng Keng Kang'],
        ['city' => 'Phnom Penh', 'state' => 'Phnom Penh', 'postal_code' => '120404', 'district' => 'Sangkat Toul Tom Poung I, Khan Chamkar Mon'],
        ['city' => 'Phnom Penh', 'state' => 'Phnom Penh', 'postal_code' => '121501', 'district' => 'Sangkat Teuk Thla, Khan Sen Sok'],
        ['city' => 'Siem Reap', 'state' => 'Siem Reap', 'postal_code' => '171201', 'district' => 'Sangkat Svay Dangkum, Krong Siem Reap'],
        ['city' => 'Battambang', 'state' => 'Battambang', 'postal_code' => '021401', 'district' => 'Sangkat Svay Por, Krong Battambang'],
        ['city' => 'Kampot', 'state' => 'Kampot', 'postal_code' => '070801', 'district' => 'Sangkat Kampong Kandal, Krong Kampot'],
        ['city' => 'Ta Khmau', 'state' => 'Kandal', 'postal_code' => '082501', 'district' => 'Sangkat Ta Khmau, Krong Ta Khmau'],
        ['city' => 'Sihanoukville', 'state' => 'Preah Sihanouk', 'postal_code' => '180301', 'district' => 'Sangkat Bei, Krong Preah Sihanouk'],
    ];

    public function definition(): array
    {
        $location = fake()->randomElement(self::CAMBODIAN_LOCATIONS);
        $houseNo = fake()->numberBetween(12, 248);
        $streetNo = fake()->randomElement(['110', '2004', '271', '310', '360', '51', '63', '371']);

        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Home', 'Work', 'Family']),
            'full_name' => fake()->name(),
            'phone' => '+855 '.fake()->randomElement(['10', '12', '16', '77', '96']).' '.fake()->numerify('### ###'),
            'address_line1' => 'House #'.$houseNo.', Street '.$streetNo,
            'address_line2' => $location['district'],
            'city' => $location['city'],
            'state' => $location['state'],
            'postal_code' => $location['postal_code'],
            'country' => 'KH',
            'is_default' => false,
        ];
    }

    public function defaultAddress(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
