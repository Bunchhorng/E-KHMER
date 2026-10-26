<?php

namespace Database\Factories;

use App\Models\BusinessExpense;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessExpense> */
class BusinessExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(), 'created_by' => User::factory(),
            'incurred_on' => now()->toDateString(), 'category' => 'other',
            'description' => fake()->sentence(), 'amount' => '25.00', 'currency' => 'USD',
        ];
    }
}
