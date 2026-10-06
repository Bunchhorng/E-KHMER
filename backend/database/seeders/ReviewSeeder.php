<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Verified reviews on delivered-purchase products plus a pending moderation queue.
     */
    public function run(): void
    {
        $delivered = Order::where('status', Order::STATUS_DELIVERED)->with('items.product')->get();

        $reviews = [
            ['user' => 'olivia.bennett@example.com', 'sku' => 'aurora-black', 'rating' => 5, 'status' => Review::STATUS_APPROVED, 'title' => 'Best headphones I have owned', 'body' => 'The noise cancelling is superb and battery easily lasts a full work week. Highly recommend.' ],
            ['user' => 'olivia.bennett@example.com', 'sku' => 'orbit-ocean', 'rating' => 4, 'status' => Review::STATUS_APPROVED, 'title' => 'Roomy and waterproof', 'body' => 'Plenty of pockets, and the material shrugs off rain. Wish the clamshell opened a touch wider.' ],
            ['user' => 'jake.miller@example.com', 'sku' => 'aerolite-wh-43', 'rating' => 5, 'status' => Review::STATUS_APPROVED, 'title' => 'Featherlight and fast', 'body' => 'Took them straight into a tempo run out of the box. Zero break-in needed.' ],
            ['user' => 'jake.miller@example.com', 'sku' => 'stride-g-10', 'rating' => 4, 'status' => Review::STATUS_APPROVED, 'title' => 'Sharp everyday sneaker', 'body' => 'Clean look, true to size, comfy insole. Scuffs a little easier than I would like.' ],
            ['user' => 'marcus.lee@example.com', 'sku' => 'meridian-navy-m', 'rating' => 5, 'status' => Review::STATUS_APPROVED, 'title' => 'Perfect office blazer', 'body' => 'Great drape and the wool-linen blend breathes. Runs slim as described.' ],
            ['user' => 'marcus.lee@example.com', 'sku' => 'nova-white-l', 'rating' => 4, 'status' => Review::STATUS_APPROVED, 'title' => 'Soft and easy to style', 'body' => 'Lovely relaxed fit. Shrinks slightly on first wash — size up if between sizes.' ],
            ['user' => 'elena.rodriguez@example.com', 'sku' => 'lumiere-30ml', 'rating' => 5, 'status' => Review::STATUS_APPROVED, 'title' => 'Glow in a bottle', 'body' => 'Skin looks noticeably plumper after two weeks. No fragrance, great for sensitive skin.' ],
            ['user' => 'elena.rodriguez@example.com', 'sku' => 'vertex-base', 'rating' => 4, 'status' => Review::STATUS_PENDING, 'title' => 'Great camera, stiff learning curve', 'body' => 'Stabilization is unreal, but the menu takes a while to learn.' ],
            ['user' => 'priya.shah@example.com', 'sku' => 'pulse-rose', 'rating' => 4, 'status' => Review::STATUS_PENDING, 'title' => 'Useful daily watch', 'body' => 'The health tracking is excellent and the display stays clear outdoors.' ],
            ['user' => 'dan.okafor@example.com', 'sku' => 'cascade-tan', 'rating' => 2, 'status' => Review::STATUS_REJECTED, 'title' => 'Spam giveaway', 'body' => 'Join our Telegram for a free offer.' ],
        ];

        $linked = 0;
        foreach ($reviews as $spec) {
            $user = User::where('email', 'customer@ekhmer.dev')->firstOrFail();
            $product = Product::whereHas('variants', fn ($q) => $q->where('sku', $spec['sku']))->firstOrFail();

            $sourceOrder = null;
            if ($spec['status'] === Review::STATUS_PENDING && $spec['sku'] === 'pulse-rose') {
                $sourceOrder = Order::where('user_id', $user->id)
                    ->where('status', Order::STATUS_PROCESSING)
                    ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
                    ->first();
            }

            if ($sourceOrder === null) {
                $sourceOrder = $delivered->first(fn ($order) => $order->user_id === $user->id
                    && $order->items->contains(fn ($item) => $item->product_id === $product->id));
            }

            Review::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'shop_id' => $product->shop_id,
                'order_id' => $sourceOrder?->id,
                'rating' => $spec['rating'],
                'title' => $spec['title'],
                'body' => $spec['body'],
                'status' => $spec['status'],
                'verified' => $sourceOrder?->status === Order::STATUS_DELIVERED,
                'helpful_count' => match ($spec['status']) {
                    Review::STATUS_APPROVED => $spec['rating'] + 3,
                    default => 0,
                },
                'is_featured' => $spec['rating'] === 5 && $spec['status'] === Review::STATUS_APPROVED,
            ]);

            $linked += $sourceOrder ? 1 : 0;
        }

        foreach (Product::query()->withCount(['reviews'])->having('reviews_count', '>', 0)->get() as $product) {
            $agg = $product->reviews()
                ->where('status', Review::STATUS_APPROVED)
                ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as count')
                ->first();

            $product->update([
                'rating_avg' => round((float) $agg->avg_rating, 2),
                'rating_count' => (int) $agg->count,
            ]);
        }
    }
}
