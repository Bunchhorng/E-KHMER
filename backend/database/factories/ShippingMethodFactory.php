<?php

namespace Database\Factories;

use App\Models\ShippingMethod;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'shop_id' => null,
            'name' => ucwords($name),
            'code' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => 'Tracked local delivery across Cambodia.',
            'price' => fake()->randomFloat(2, 1.50, 6.00),
            'estimated_days_min' => fake()->numberBetween(1, 2),
            'estimated_days_max' => fake()->numberBetween(3, 5),
            'is_active' => true,
        ];
    }

    public function forShop(Shop $shop): static
    {
        return $this->state(fn (array $attributes) => [
            'shop_id' => $shop->id,
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 0,
        ]);
    }
}
