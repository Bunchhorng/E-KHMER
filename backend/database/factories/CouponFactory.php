<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => null,
            'code' => strtoupper(Str::random(10)),
            'type' => 'percentage',
            'value' => 10,
            'min_order_amount' => null,
            'max_discount_amount' => null,
            'usage_limit' => null,
            'per_user_limit' => 1,
            'used_count' => 0,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => true,
        ];
    }

    public function fixed(int|float $amount = 20): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fixed',
            'value' => $amount,
        ]);
    }

    public function forShop(Shop $shop): static
    {
        return $this->state(fn (array $attributes) => [
            'shop_id' => $shop->id,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function notStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->addDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function usageLimitReached(int $limit = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_limit' => $limit,
            'used_count' => $limit,
        ]);
    }
}
