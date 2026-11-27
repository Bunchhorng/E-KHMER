<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 15, 280);
        $tax = round($subtotal * 0.10, 2);
        $address = [
            'full_name' => fake()->name(),
            'phone' => '+855 12 '.fake()->numerify('### ###'),
            'address_line1' => 'House #'.fake()->numberBetween(10, 220).', Street '.fake()->randomElement(['271', '2004', '310', '63']),
            'address_line2' => 'Sangkat Boeng Keng Kang I, Khan Boeng Keng Kang',
            'city' => 'Phnom Penh',
            'state' => 'Phnom Penh',
            'postal_code' => '120101',
            'country' => 'KH',
        ];

        return [
            'order_number' => 'EKH-'.date('Y').'-'.strtoupper(Str::random(6)),
            'user_id' => User::factory(),
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_UNPAID,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'tax_amount' => $tax,
            'shipping_amount' => 0,
            'total' => round($subtotal + $tax, 2),
            'currency' => 'USD',
            'shipping_address' => $address,
            'billing_address' => $address,
            'email' => fake()->safeEmail(),
            'phone' => $address['phone'],
            'customer_name' => $address['full_name'],
            'note' => null,
            'placed_at' => now(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_PAID,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => Order::PAYMENT_PAID,
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_SHIPPED,
            'payment_status' => Order::PAYMENT_PAID,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => Order::PAYMENT_PAID,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CANCELLED,
            'payment_status' => Order::PAYMENT_FAILED,
        ]);
    }
}
