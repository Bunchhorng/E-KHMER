<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewApprovedNotification;
use App\Notifications\ReviewRejectedNotification;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

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
        return $this->withProductLock((int) $data['product_id'], 'create', null, $user->id, function (Product $product) use ($user, $data): Review {
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
                'shop_id' => $product->shop_id,
                'rating' => (int) $data['rating'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'status' => Review::STATUS_PENDING,
                'verified' => true,
            ]);
        });
    }

    public function update(User $user, Review $review, array $data): Review
    {
        return $this->mutateReview($review, 'update', function (Review $review) use ($user, $data): void {
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
                return;
            }

            $wasApproved = $review->status === Review::STATUS_APPROVED;
            if ($wasApproved) {
                // An edited published review must be moderated again; its old score
                // must no longer contribute to the public product aggregate.
                $updates['status'] = Review::STATUS_PENDING;
            }

            $review->update($updates);
        }, $user->id);
    }

    public function delete(User $user, Review $review): void
    {
        $this->mutateReview($review, 'customer_delete', function (Review $review) use ($user): void {
            if ((int) $review->user_id !== (int) $user->id) {
                abort(404, 'Review not found.');
            }

            $review->delete();
        }, $user->id);
    }

    /** Authorization belongs to the admin controller's existing policy. */
    public function deleteByAdmin(Review $review): void
    {
        $this->mutateReview($review, 'admin_delete', fn (Review $current) => $current->delete());
    }

    public function approve(Review $review): void
    {
        $this->mutateReview($review, 'approve', function (Review $review): void {
            $review->update(['status' => Review::STATUS_APPROVED]);

            $review->load(['user', 'product']);
            $review->user?->notify(new ReviewApprovedNotification($review));
        });
    }

    /** Recompute one product, including archived products. Return whether it differed. */
    public function recalculateProductRating(int $productId, bool $dryRun = false): bool
    {
        return $this->withProductLock($productId, 'recalculate', null, null,
            fn (Product $product) => $this->persistProductRating($product, $dryRun), false);
    }

    private function persistProductRating(Product $product, bool $dryRun = false): bool
    {
        $stats = Review::where('product_id', $product->id)
            ->approved()
            ->selectRaw('COUNT(*) as count, AVG(rating) as avg')
            // A current read also works when called inside an older MySQL
            // REPEATABLE READ transaction; never recompute from its snapshot.
            ->lockForUpdate()
            ->first();

        $count = (int) ($stats->count ?? 0);
        $average = round((float) ($stats->avg ?? 0), 2);
        $changed = (int) $product->rating_count !== $count
            || number_format((float) $product->rating_avg, 2, '.', '') !== number_format($average, 2, '.', '');

        if ($changed && ! $dryRun) {
            $product->forceFill([
                'rating_count' => $count,
                'rating_avg' => $average,
            ])->save();
        }

        return $changed;
    }

    public function reject(Review $review): void
    {
        $this->mutateReview($review, 'reject', function (Review $review): void {
            $review->update(['status' => Review::STATUS_REJECTED]);

            $review->load(['user', 'product']);
            $review->user?->notify(new ReviewRejectedNotification($review));
        });
    }

    private function mutateReview(Review $review, string $action, Closure $mutation, ?int $actorId = null): Review
    {
        return $this->withProductLock((int) $review->product_id, $action, $review->id, $actorId,
            function () use ($review, $mutation): Review {
                // Route-bound models can be stale after waiting for another writer.
                $current = Review::where('product_id', $review->product_id)->lockForUpdate()->findOrFail($review->id);
                $mutation($current);

                return $current;
            });
    }

    private function withProductLock(int $productId, string $action, ?int $reviewId, ?int $actorId, Closure $operation, bool $recalculate = true): mixed
    {
        try {
            return DB::transaction(function () use ($productId, $operation, $recalculate) {
                // All supported writers and repairs lock product first, then review.
                // Different products can still be moderated independently.
                $product = Product::withTrashed()->lockForUpdate()->findOrFail($productId);
                $result = $operation($product);
                if ($recalculate) {
                    $this->persistProductRating($product);
                }

                return $result;
            }, 3);
        } catch (ValidationException|ModelNotFoundException|HttpExceptionInterface|HttpResponseException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Review aggregate operation failed.', [
                'review_id' => $reviewId,
                'product_id' => $productId,
                'action' => $action,
                'actor_id' => $actorId ?? auth()->id(),
                'exception_class' => $exception::class,
            ]);

            // An explicit response also stays safe when development debug is on.
            throw new HttpResponseException(response()->json([
                'message' => 'Could not update this review. Please try again.',
            ], 503));
        }
    }
}
