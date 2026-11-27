<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => null,
        ];
    }

    public function forSession(string $sessionId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'session_id' => $sessionId,
        ]);
    }
}
