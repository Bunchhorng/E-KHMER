<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /** Exactly one account per demo role. All passwords are `password`. */
    public function run(): void
    {
        foreach ([
            ['name' => 'Super Admin', 'email' => 'admin@ekhmer.dev', 'phone' => '+855 10 000 001', 'role' => User::ROLE_ADMIN],
            ['name' => 'Demo Customer', 'email' => 'customer@ekhmer.dev', 'phone' => '+855 10 000 002', 'role' => User::ROLE_CUSTOMER],
            // Seller access is represented by shop_users; the base User role remains customer by design.
            ['name' => 'Demo Seller', 'email' => 'seller@ekhmer.dev', 'phone' => '+855 10 000 003', 'role' => User::ROLE_CUSTOMER],
        ] as $account) {
            $role = $account['role']; unset($account['role']);
            $user = User::create($account + ['password' => 'password', 'newsletter' => false]);
            $user->forceFill(['role' => $role, 'email_verified_at' => now()])->save();
        }
    }
}
