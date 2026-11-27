<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    protected $model = Shop::class;

    /** @var list<array{city: string, province: string, postal_code: string, mall: string, lat: float, lng: float}> */
    private const CAMBODIAN_HUBS = [
        ['city' => 'Phnom Penh', 'province' => 'Phnom Penh', 'postal_code' => '120101', 'mall' => 'AEON Mall Phnom Penh', 'lat' => 11.5477, 'lng' => 104.9333],
        ['city' => 'Phnom Penh', 'province' => 'Phnom Penh', 'postal_code' => '121501', 'mall' => 'AEON Mall Sen Sok', 'lat' => 11.5994, 'lng' => 104.8842],
        ['city' => 'Phnom Penh', 'province' => 'Phnom Penh', 'postal_code' => '120403', 'mall' => 'Chip Mong 271 Mega Mall', 'lat' => 11.5312, 'lng' => 104.9094],
        ['city' => 'Siem Reap', 'province' => 'Siem Reap', 'postal_code' => '171201', 'mall' => 'Made in Cambodia Market', 'lat' => 13.3547, 'lng' => 103.8549],
        ['city' => 'Battambang', 'province' => 'Battambang', 'postal_code' => '021401', 'mall' => 'Psar Nat Riverside', 'lat' => 13.0957, 'lng' => 103.2022],
        ['city' => 'Kampot', 'province' => 'Kampot', 'postal_code' => '070801', 'mall' => 'Kampot Old Market Quarter', 'lat' => 10.6104, 'lng' => 104.1815],
        ['city' => 'Ta Khmau', 'province' => 'Kandal', 'postal_code' => '082501', 'mall' => 'Kandal Riverside Arcade', 'lat' => 11.4833, 'lng' => 104.9500],
    ];

    public function definition(): array
    {
        $name = $this->faker->unique()->company().' Shop';
        $hub = $this->faker->randomElement(self::CAMBODIAN_HUBS);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'code' => strtoupper(Str::random(6)),
            'branch_type' => 'storefront',
            'description' => $this->faker->sentence(),
            'phone' => '+855 12 '.$this->faker->numerify('### ###'),
            'email' => $this->faker->unique()->companyEmail(),
            'address_line' => 'Unit #'.$this->faker->numberBetween(1, 90).', '.$hub['mall'],
            'mall' => $hub['mall'],
            'city' => $hub['city'],
            'province' => $hub['province'],
            'postal_code' => $hub['postal_code'],
            'country' => 'KH',
            'latitude' => $hub['lat'],
            'longitude' => $hub['lng'],
            'status' => Shop::STATUS_ACTIVE,
            'is_default' => false,
            'commission_rate' => 8,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Shop::STATUS_PENDING,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Shop::STATUS_SUSPENDED,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Shop::STATUS_REJECTED,
            'rejection_reason' => 'Business registration details require verification.',
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Shop::STATUS_CLOSED,
        ]);
    }

    public function withBakong(): static
    {
        return $this->state(fn (array $attributes) => [
            'bakong_account_id' => 'demo_'.strtolower((string) ($attributes['code'] ?? 'shop')).'@aclb',
            'bakong_merchant_name' => Str::substr((string) ($attributes['name'] ?? 'E-KHMER Shop'), 0, 25),
            'bakong_merchant_city' => Str::substr((string) ($attributes['city'] ?? 'Phnom Penh'), 0, 15),
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
