<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'description' => fake()->paragraph(),
            'short_description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 20, 200),
            'cost_price' => fake()->randomFloat(2, 10, 150),
            'sku' => 'P-' . Str::upper(Str::random(8)),
            'weight' => fake()->randomFloat(2, 0.2, 5),
            'is_featured' => false,
            'is_active' => true,
            'rating_avg' => 0,
            'rating_count' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Attach a single sellable variant plus its inventory row.
     */
    public function withVariant(?float $price = null, int $stock = 10): static
    {
        return $this->afterCreating(function (Product $product) use ($price, $stock): void {
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'shop_id' => $product->shop_id,
                'name' => 'Default',
                'sku' => 'SKU-' . Str::upper(Str::random(8)),
                'price' => $price ?? (float) $product->price,
                'is_default' => true,
                'is_active' => true,
            ]);

            $variant->inventory()->create([
                'shop_id' => $product->shop_id,
                'quantity' => $stock,
                'reserved_quantity' => 0,
                'low_stock_threshold' => 5,
                'sold_count' => 0,
            ]);
        });
    }
}