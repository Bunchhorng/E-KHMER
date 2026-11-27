<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
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
        $price = fake()->randomFloat(2, 12, 220);

        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'shop_id' => null,
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->paragraph(),
            'short_description' => fake()->sentence(),
            'price' => $price,
            'compare_at_price' => round($price * 1.15, 2),
            'cost_price' => round($price * 0.60, 2),
            'sku' => 'P-'.Str::upper(Str::random(8)),
            'barcode' => '884'.fake()->unique()->numerify('##########'),
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

    public function forShop(Shop $shop): static
    {
        return $this->state(fn (array $attributes) => [
            'shop_id' => $shop->id,
        ]);
    }

    /**
     * Attach a single sellable variant plus its inventory row.
     */
    public function withVariant(?float $price = null, int $stock = 10): static
    {
        return $this->afterCreating(function (Product $product) use ($price, $stock): void {
            $variantPrice = $price ?? (float) $product->price;
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'shop_id' => $product->shop_id,
                'name' => 'Standard',
                'sku' => 'SKU-'.Str::upper(Str::random(8)),
                'barcode' => '884'.fake()->numerify('##########'),
                'price' => $variantPrice,
                'cost_price' => round($variantPrice * 0.60, 2),
                'compare_at_price' => $product->compare_at_price,
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
