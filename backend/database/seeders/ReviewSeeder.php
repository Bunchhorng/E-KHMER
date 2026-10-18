<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Order::with('items.product')->where('status', Order::STATUS_DELIVERED)->orderBy('id')->get() as $index => $order) {
            $product = $order->items->sole()->product;
            $status = match ($index % 5) {
                3 => Review::STATUS_PENDING,
                4 => Review::STATUS_REJECTED,
                default => Review::STATUS_APPROVED,
            };
            $rating = ($index % 3) + 3;
            Review::create([
                'user_id' => $order->user_id,
                'product_id' => $product->id,
                'shop_id' => $product->shop_id,
                'order_id' => $order->id,
                'rating' => $rating,
                'title' => 'My experience with '.$product->name,
                'body' => 'The delivery arrived on time. I kept one item and returned the spare.',
                'status' => $status,
                'verified' => true,
                'helpful_count' => 0,
                'is_featured' => $status === Review::STATUS_APPROVED && $rating === 5,
            ]);
            $product->update([
                'rating_avg' => $status === Review::STATUS_APPROVED ? $rating : 0,
                'rating_count' => $status === Review::STATUS_APPROVED ? 1 : 0,
            ]);
        }
    }
}
