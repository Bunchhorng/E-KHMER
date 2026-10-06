<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewApprovedNotification;
use App\Notifications\ReviewRejectedNotification;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public function verifiedPurchase(User $user, int $productId): ?Order
    {
        return $user->orders()
            ->where('status', Order::STATUS_DELIVERED)
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))
            ->first();
    }

    public function store(User $user, array $data): Review
    {
        $order = $this->verifiedPurchase($user, (int) $data['product_id']);

        if ($order === null) {
            throw ValidationException::withMessages([
                'product_id' => ['You can only review products you have purchased and received.'],
            ]);
        }

        $existing = Review::where('user_id', $user->id)
            ->where('product_id', (int) $data['product_id'])
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'product_id' => ['You have already reviewed this product.'],
            ]);
        }

        return Review::create([
            'user_id' => $user->id,
            'product_id' => (int) $data['product_id'],
            'order_id' => $order->id,
            'shop_id' => Product::find((int) $data['product_id'])?->shop_id,
            'rating' => (int) $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'status' => Review::STATUS_PENDING,
            'verified' => true,
        ]);
    }

    public function update(User $user, Review $review, array $data): Review
    {
        if ((int) $review->user_id !== (int) $user->id) {
            abort(404, 'Review not found.');
        }

        $updates = [];

        if (array_key_exists('rating', $data) && $data['rating'] !== null) {
            $updates['rating'] = (int) $data['rating'];
        }

        // Nullable text fields must be allowed to be cleared by the customer.
        foreach (['title', 'body'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if ($updates === []) {
            return $review->fresh();
        }

        $wasApproved = $review->status === Review::STATUS_APPROVED;
        if ($wasApproved) {
            // An edited published review must be moderated again; its old score
            // must no longer contribute to the public product aggregate.
            $updates['status'] = Review::STATUS_PENDING;
        }

        $review->update($updates);

        if ($wasApproved) {
            $this->recalculateProductRating($review->product_id);
        }

        return $review->fresh();
    }

    public function delete(User $user, Review $review): void
    {
        if ((int) $review->user_id !== (int) $user->id) {
            abort(404, 'Review not found.');
        }

        $wasApproved = $review->status === Review::STATUS_APPROVED;
        $productId = (int) $review->product_id;

        $review->delete();

        if ($wasApproved) {
            $this->recalculateProductRating($productId);
        }
    }

    public function approve(Review $review): void
    {
        $review->update(['status' => Review::STATUS_APPROVED]);
        $this->recalculateProductRating($review->product_id);

        $review->load(['user', 'product']);
        $review->user?->notify(new ReviewApprovedNotification($review));
    }

    protected function recalculateProductRating(int $productId): void
    {
        $product = \App\Models\Product::find($productId);
        if ($product === null) {
            return;
        }

        $stats = Review::where('product_id', $productId)
            ->where('status', Review::STATUS_APPROVED)
            ->selectRaw('COUNT(*) as count, AVG(rating) as avg')
            ->first();

        $product->forceFill([
            'rating_count' => (int) ($stats->count ?? 0),
            'rating_avg' => round((float) ($stats->avg ?? 0), 2),
        ])->save();
    }

    public function reject(Review $review): void
    {
        $review->update(['status' => Review::STATUS_REJECTED]);

        $review->load(['user', 'product']);
        $review->user?->notify(new ReviewRejectedNotification($review));
    }
}
