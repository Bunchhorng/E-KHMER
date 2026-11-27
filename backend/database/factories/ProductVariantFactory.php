<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 12, 220);

        return [
            'product_id' => Product::factory(),
            'shop_id' => null,
            'name' => ucwords(fake()->words(2, true)),
            'sku' => 'SKU-'.Str::upper(Str::random(10)),
            'barcode' => '884'.fake()->numerify('##########'),
            'price' => $price,
            'cost_price' => round($price * 0.60, 2),
            'compare_at_price' => round($price * 1.15, 2),
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ProductVariant $variant): void {
            if ($variant->shop_id === null && $variant->product_id !== null) {
                $variant->shop_id = Product::whereKey($variant->product_id)->value('shop_id');
            }
        });
    }

    public function withInventory(int $stock = 20): static
    {
        return $this->afterCreating(function (ProductVariant $variant) use ($stock): void {
            $variant->inventory()->firstOrCreate([], [
                'shop_id' => $variant->shop_id ?? $variant->product?->shop_id,
                'quantity' => $stock,
                'reserved_quantity' => 0,
                'low_stock_threshold' => 5,
                'sold_count' => 0,
            ]);
        });
    }
}
