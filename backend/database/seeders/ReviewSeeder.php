<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Services\ReviewService;
use Database\Seeders\Concerns\EnsuresLocalDemoEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReviewSeeder extends Seeder
{
    use EnsuresLocalDemoEnvironment;

    private const COMMENTS = [
        ['rating' => 5, 'title' => 'Excellent Cambodian quality', 'body' => 'Packed neatly and arrived in Phnom Penh on schedule. Exactly as described by the seller.'],
        ['rating' => 5, 'title' => 'Very satisfied with this order', 'body' => 'Great aroma and sturdy packaging. Will definitely reorder from this E-KHMER shop.'],
        ['rating' => 4, 'title' => 'Good value and fast courier', 'body' => 'Delivery took two days and the item matches the listing specifications well.'],
        ['rating' => 4, 'title' => 'Solid everyday item', 'body' => 'Works cleanly out of the box and customer support answered questions quickly.'],
        ['rating' => 3, 'title' => 'Decent quality overall', 'body' => 'Good product quality though delivery packaging had a minor crease on the corner.'],
    ];

    public function run(): void
    {
        $this->ensureLocalOrTestingEnvironment('Synthetic demo reviews may only be seeded in local or testing environments.');

        $reviewService = app(ReviewService::class);
        $touchedProducts = [];

        $deliveredOrders = Order::with('items.product')
            ->where('status', Order::STATUS_DELIVERED)
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->get();

        foreach ($deliveredOrders as $orderIdx => $order) {
            foreach ($order->items as $itemIdx => $item) {
                $product = $item->product;
                if ($product === null) {
                    continue;
                }

                // Respect unique(user_id, product_id) verified-purchase constraint.
                if (Review::where('user_id', $order->user_id)->where('product_id', $product->id)->exists()) {
                    continue;
                }

                $status = match (($orderIdx + $itemIdx) % 8) {
                    6 => Review::STATUS_PENDING,
                    7 => Review::STATUS_REJECTED,
                    default => Review::STATUS_APPROVED,
                };
                $sample = self::COMMENTS[($orderIdx + $itemIdx) % count(self::COMMENTS)];

                DB::transaction(function () use ($order, $product, $status, $sample): void {
                    Product::withTrashed()->lockForUpdate()->findOrFail($product->id);

                    Review::create([
                        'user_id' => $order->user_id,
                        'product_id' => $product->id,
                        'shop_id' => $product->shop_id,
                        'order_id' => $order->id,
                        'rating' => $status === Review::STATUS_REJECTED ? 2 : $sample['rating'],
                        'title' => $sample['title'],
                        'body' => $sample['body'],
                        'status' => $status,
                        'verified' => true,
                        'helpful_count' => $status === Review::STATUS_APPROVED ? (($order->id + $product->id) % 9) : 0,
                        'is_featured' => $status === Review::STATUS_APPROVED && $sample['rating'] === 5,
                    ]);
                });

                $touchedProducts[$product->id] = true;
            }
        }

        foreach (array_keys($touchedProducts) as $productId) {
            $reviewService->recalculateProductRating((int) $productId);
        }
    }
}
