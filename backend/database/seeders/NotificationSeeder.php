<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Database\Seeders\Concerns\EnsuresLocalDemoEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    use EnsuresLocalDemoEnvironment;

    /** Seed read and unread records for customer, seller, and admin notification screens. */
    public function run(): void
    {
        $this->ensureLocalOrTestingEnvironment('Demo notifications may only be seeded in local or testing environments.');

        $admin = User::whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])->orderBy('id')->first();
        $seller = User::where('email', 'seller@ekhmer.com')->first();
        $customer = User::where('email', 'customer@ekhmer.com')->first();
        $latestOrder = Order::latest('id')->first();

        $entries = [
            [$admin, 'shop.application', ['title' => 'New seller application', 'message' => 'Takeo Leaf & Tea Garden is waiting for verification review.'], null],
            [$admin, 'shop.application', ['title' => 'New seller application', 'message' => 'Kampong Cham Textile Mill submitted onboarding documents.'], null],
            [$admin, 'inventory.low_stock', ['title' => 'Low stock alert', 'message' => 'Several marketplace variants are at or below their low-stock threshold.'], null],
            [$admin, 'return.requested', ['title' => 'Return request received', 'message' => 'A customer opened a return request for a recent delivered order.'], now()->subHours(6)],
            [$seller, 'order.new', ['title' => 'New shop order awaiting dispatch', 'message' => 'An order from Phnom Penh is ready for packing.'], null],
            [$seller, 'inventory.low_stock', ['title' => 'Low stock notice', 'message' => 'One of your Kampot spice variants has 3 units remaining.'], null],
            [$seller, 'review.submitted', ['title' => 'New verified product review', 'message' => 'A verified buyer rated your item 5 stars.'], now()->subDay()],
            [$customer, 'order.confirmed', ['title' => 'Order confirmed', 'message' => 'Your E-KHMER order has been confirmed and is being prepared.'], now()->subDays(2)],
            [$customer, 'order.shipped', ['title' => 'Parcel in transit', 'message' => 'Your parcel was handed off to Virak Buntham Express.'], null],
            [$customer, 'order.delivered', ['title' => 'Order delivered', 'message' => 'Your recent E-KHMER order has been delivered.', 'order_id' => $latestOrder?->id], now()->subDay()],
        ];

        foreach ($entries as $idx => [$user, $type, $data, $readAt]) {
            if ($user === null) {
                continue;
            }
            if (DB::table('notifications')->where('notifiable_id', $user->id)->where('type', $type)->where('data', json_encode($data, JSON_THROW_ON_ERROR))->exists()) {
                continue;
            }

            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => $type,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
                'read_at' => $readAt,
                'created_at' => now()->subHours(4 + $idx),
                'updated_at' => now(),
            ]);
        }
    }
}
