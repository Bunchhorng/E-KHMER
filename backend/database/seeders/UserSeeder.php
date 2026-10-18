<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /** Twenty verified demo accounts, including the existing login accounts. */
    public function run(): void
    {
        $accounts = [
            ['name' => 'Super Admin', 'email' => 'admin@ekhmer.com', 'phone' => '+855 10 000 001', 'role' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Demo Customer', 'email' => 'customer@ekhmer.com', 'phone' => '+855 10 000 002', 'role' => User::ROLE_CUSTOMER],
            // Seller access is represented by shop_users; the base User role remains customer by design.
            ['name' => 'Demo Seller', 'email' => 'seller@ekhmer.com', 'phone' => '+855 10 000 003', 'role' => User::ROLE_CUSTOMER],
        ];

        foreach (range(4, DatabaseSeeder::RECORD_COUNT) as $number) {
            $accounts[] = [
                'name' => 'Demo Customer '.$number,
                'email' => 'customer'.$number.'@ekhmer.com',
                'phone' => '+855 10 000 '.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'role' => User::ROLE_CUSTOMER,
            ];
        }

        foreach ($accounts as $account) {
            $role = $account['role'];
            unset($account['role']);
            $user = User::create($account + ['password' => 'password', 'newsletter' => false]);
            $user->forceFill(['role' => $role, 'email_verified_at' => now()])->save();
        }
    }
}
