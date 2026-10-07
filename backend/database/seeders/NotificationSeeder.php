<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    /** Seed read and unread records for the customer and admin notification screens. */
    public function run(): void
    {
        $admin = User::whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])->first();
        $customer = User::where('email', 'customer@ekhmer.dev')->first();
        $order = Order::latest('id')->first();

        $notifications = [
            [$admin, 'shop.application', ['title' => 'New seller application', 'message' => 'New Market Seller is ready for review.'], null],
            [$admin, 'inventory.low_stock', ['title' => 'Low stock alert', 'message' => 'Several marketplace variants are at or below their threshold.'], null],
            [$customer, 'order.shipped', ['title' => 'Order update', 'message' => 'Your order is on its way.', 'order_id' => $order?->id], now()->subDay()],
        ];
        foreach (range(4, 10) as $number) {
            $notifications[] = [$number % 2 === 0 ? $admin : $customer, 'system.demo', ['title' => 'Demo notification '.$number, 'message' => 'Sample marketplace notification '.$number.'.'], $number % 3 === 0 ? now()->subHours($number) : null];
        }
        foreach ($notifications as [$user, $type, $data, $readAt]) {
            if ($user === null) continue;
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => $type,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
                'read_at' => $readAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
