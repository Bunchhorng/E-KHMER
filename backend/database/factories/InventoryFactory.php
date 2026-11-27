<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'shop_id' => null,
            'quantity' => fake()->numberBetween(5, 100),
            'reserved_quantity' => 0,
            'low_stock_threshold' => 5,
            'sold_count' => 0,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 0,
        ]);
    }

    public function lowStock(int $quantity = 3, int $threshold = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $quantity,
            'low_stock_threshold' => $threshold,
        ]);
    }

    public function reserved(int $reserved = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'reserved_quantity' => $reserved,
        ]);
    }
}
