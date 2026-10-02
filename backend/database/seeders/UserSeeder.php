<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Shared demo password for every seeded account.
     */
    private const DEMO_PASSWORD = 'password';

    /**
     * Seed one admin and six customer accounts.
     */
    public function run(): void
    {
        $this->createUser([
            'name' => 'Admin',
            'email' => 'admin@ekhmer.dev',
            'phone' => '+1 (415) 555-0100',
            'newsletter' => false,
        ], User::ROLE_ADMIN);

        $customers = [
            ['name' => 'Olivia Bennett', 'email' => 'olivia.bennett@example.com', 'phone' => '+1 (415) 555-0123', 'newsletter' => true],
            ['name' => 'Marcus Lee', 'email' => 'marcus.lee@example.com', 'phone' => '+1 (555) 010-0002', 'newsletter' => true],
            ['name' => 'Priya Shah', 'email' => 'priya.shah@example.com', 'phone' => '+1 (555) 010-0003', 'newsletter' => false],
            ['name' => 'Jake Miller', 'email' => 'jake.miller@example.com', 'phone' => '+1 (555) 010-0004', 'newsletter' => true],
            ['name' => 'Elena Rodriguez', 'email' => 'elena.rodriguez@example.com', 'phone' => '+1 (555) 010-0005', 'newsletter' => false],
            ['name' => 'Dan Okafor', 'email' => 'dan.okafor@example.com', 'phone' => '+1 (555) 010-0006', 'newsletter' => true],
        ];

        foreach ($customers as $customer) {
            $this->createUser($customer, User::ROLE_CUSTOMER);
        }
    }

    /**
     * `role` and `email_verified_at` are excluded from the User model's
     * $fillable so that no request payload can set them, which means they have
     * to be written explicitly here. Going through create() for them would
     * silently drop both values and produce a shop with no admin account.
     */
    private function createUser(array $attributes, string $role): User
    {
        $user = User::create(array_merge(
            ['password' => self::DEMO_PASSWORD],
            $attributes,
        ));

        $user->forceFill([
            'role' => $role,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}