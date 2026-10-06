<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::factory()->withVariant()->create(['price' => 49.99]);
    }

    private function deliveredPurchase(User $user, Product $product): Order
    {
        $variant = $product->variants()->first();

        $order = Order::factory()->delivered()->create(['user_id' => $user->id]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_label' => $variant->name,
            'sku' => $variant->sku,
            'unit_price' => 49.99,
            'quantity' => 1,
            'line_total' => 49.99,
        ]);

        return $order;
    }

    public function test_customer_without_delivered_purchase_is_rejected(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reviews', [
                'product_id' => $product->id,
                'rating' => 5,
                'title' => 'Nice',
                'body' => 'Works great.',
            ])->assertStatus(422)
            ->assertJsonPath('errors.product_id.0', 'You can only review products you have purchased and received.');
    }

    public function test_customer_with_delivered_purchase_can_review(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $this->deliveredPurchase($user, $product);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reviews', [
                'product_id' => $product->id,
                'rating' => 4,
                'title' => 'Loved it',
                'body' => 'Would buy again.',
            ])->assertStatus(201)
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_customer_cannot_review_same_product_twice(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $this->deliveredPurchase($user, $product);

        $payload = [
            'product_id' => $product->id,
            'rating' => 4,
            'title' => 'Once',
            'body' => 'First take.',
        ];

        $this->actingAs($user, 'sanctum')->postJson('/api/reviews', $payload)->assertStatus(201);
        $this->actingAs($user, 'sanctum')->postJson('/api/reviews', $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.product_id.0', 'You have already reviewed this product.');
    }

    public function test_admin_approval_recalculates_product_rating(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $this->deliveredPurchase($user, $product);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reviews', [
                'product_id' => $product->id,
                'rating' => 5,
                'title' => 'Great',
                'body' => 'Superb.',
            ])->assertStatus(201);

        $review = Review::query()->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/reviews/{$review->id}/approve")
            ->assertOk();

        $this->assertSame(1, (int) $product->fresh()->rating_count);
        $this->assertSame(5.0, (float) $product->fresh()->rating_avg);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/reviews/{$review->id}/reject")
            ->assertOk();

        $this->assertSame('rejected', Review::findOrFail($review->id)->status);
    }

    public function test_reviews_of_an_unpublished_product_are_not_readable(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $this->deliveredPurchase($user, $product);

        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rating' => 5,
            'status' => Review::STATUS_APPROVED,
            'verified' => true,
        ]);

        $this->getJson("/api/products/{$product->id}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $product->update(['is_active' => false]);

        // Otherwise the review endpoint stays a way to read a product the catalog
        // hides.
        $this->getJson("/api/products/{$product->id}/reviews")->assertStatus(404);

        $this->assertSame($review->id, Review::query()->firstOrFail()->id);
    }

    public function test_customer_can_delete_only_their_own_review_and_public_rating_is_recalculated(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = $this->product();

        $review = Review::create([
            'user_id' => $owner->id,
            'product_id' => $product->id,
            'rating' => 5,
            'status' => Review::STATUS_APPROVED,
            'verified' => true,
        ]);
        $product->update(['rating_count' => 1, 'rating_avg' => 5]);

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/reviews/{$review->id}")
            ->assertNotFound();

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/reviews/{$review->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertSame(0, (int) $product->fresh()->rating_count);
        $this->assertSame(0.0, (float) $product->fresh()->rating_avg);
    }
}
