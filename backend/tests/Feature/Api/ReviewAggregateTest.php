<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Review;
use App\Models\Shop;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ReviewAggregateTest extends TestCase
{
    use RefreshDatabase;

    private function review(Product $product, int $rating, string $status = Review::STATUS_APPROVED, ?User $user = null): Review
    {
        return Review::create([
            'user_id' => ($user ?? User::factory()->create())->id,
            'product_id' => $product->id,
            'shop_id' => $product->shop_id,
            'rating' => $rating,
            'status' => $status,
            'verified' => true,
        ]);
    }

    private function assertAggregate(Product $product, float $average, int $count): void
    {
        $product = $product->fresh();
        $this->assertSame($average, (float) $product->rating_avg);
        $this->assertSame($count, (int) $product->rating_count);
    }

    public static function aggregates(): array
    {
        return [
            'empty' => [[], [], [], 0.0, 0],
            'one' => [[5], [], [], 5.0, 1],
            'two' => [[5, 4], [], [], 4.5, 2],
            'pending excluded' => [[5], [1], [], 5.0, 1],
            'rejected excluded' => [[5], [], [1], 5.0, 1],
            'only unpublished' => [[], [5], [4], 0.0, 0],
            'rounds down' => [[5, 4, 4], [], [], 4.33, 3],
            'rounds up' => [[5, 5, 4], [], [], 4.67, 3],
        ];
    }

    #[DataProvider('aggregates')]
    public function test_shared_recalculation_uses_approved_rows_only(array $approved, array $pending, array $rejected, float $average, int $count): void
    {
        $product = Product::factory()->create(['rating_avg' => 1, 'rating_count' => 99]);
        foreach ([Review::STATUS_APPROVED => $approved, Review::STATUS_PENDING => $pending, Review::STATUS_REJECTED => $rejected] as $status => $ratings) {
            foreach ($ratings as $rating) {
                $this->review($product, $rating, $status);
            }
        }

        $service = app(ReviewService::class);
        $this->assertTrue($service->recalculateProductRating($product->id));
        $this->assertAggregate($product, $average, $count);
        $before = $product->fresh()->getRawOriginal();
        $this->assertFalse($service->recalculateProductRating($product->id));
        $this->assertSame($before, $product->fresh()->getRawOriginal());
    }

    public function test_rejecting_the_only_approved_review_resets_database_and_public_responses(): void
    {
        $product = Product::factory()->withVariant()->create(['rating_avg' => 5, 'rating_count' => 1]);
        $review = $this->review($product, 5);
        $this->review($product, 1, Review::STATUS_PENDING);
        $this->review($product, 2, Review::STATUS_REJECTED);

        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->postJson("/api/admin/reviews/{$review->id}/reject")
            ->assertOk()->assertJsonPath('data.status', Review::STATUS_REJECTED);

        $this->assertAggregate($product, 0.0, 0);
        $this->getJson("/api/catalog/products/{$product->slug}")->assertOk()
            ->assertJsonPath('data.rating_avg', 0)->assertJsonPath('data.rating_count', 0);
        $this->getJson('/api/catalog/products')->assertOk()
            ->assertJsonPath('data.0.rating_avg', 0)->assertJsonPath('data.0.rating_count', 0);
        $this->getJson("/api/products/{$product->id}/reviews")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_approval_rejection_reapproval_and_repeated_actions_recompute(): void
    {
        $product = Product::factory()->create(['rating_avg' => 4, 'rating_count' => 1]);
        $this->review($product, 4);
        $review = $this->review($product, 5, Review::STATUS_PENDING);
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        foreach (['approve' => [4.5, 2], 'reject' => [4.0, 1]] as $action => [$average, $count]) {
            for ($repeat = 0; $repeat < 2; $repeat++) {
                $this->postJson("/api/admin/reviews/{$review->id}/{$action}")->assertOk();
                $this->assertAggregate($product, $average, $count);
            }
        }
        $this->postJson("/api/admin/reviews/{$review->id}/approve")->assertOk();
        $this->assertAggregate($product, 4.5, 2);
    }

    public static function deletions(): array
    {
        return [
            'admin approved' => [true, Review::STATUS_APPROVED],
            'customer approved' => [false, Review::STATUS_APPROVED],
            'admin pending' => [true, Review::STATUS_PENDING],
            'admin rejected' => [true, Review::STATUS_REJECTED],
            'customer pending' => [false, Review::STATUS_PENDING],
            'customer rejected' => [false, Review::STATUS_REJECTED],
        ];
    }

    #[DataProvider('deletions')]
    public function test_all_supported_deletions_maintain_only_the_affected_product(bool $admin, string $status): void
    {
        $product = Product::factory()->create(['rating_avg' => $status === Review::STATUS_APPROVED ? 4.5 : 4, 'rating_count' => $status === Review::STATUS_APPROVED ? 2 : 1]);
        $other = Product::factory()->create(['rating_avg' => 3, 'rating_count' => 1]);
        $this->review($other, 3);
        $this->review($product, 4);
        $owner = User::factory()->create();
        $review = $this->review($product, 5, $status, $owner);
        $response = $this->actingAs($admin ? User::factory()->admin()->create() : $owner, 'sanctum')
            ->deleteJson('/api/'.($admin ? 'admin/' : '')."reviews/{$review->id}");
        $response->assertStatus($admin ? 200 : 204);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertAggregate($product, 4.0, 1);
        $this->assertAggregate($other, 3.0, 1);
    }

    public function test_edit_of_approved_review_resets_to_pending_then_reapproval_uses_new_rating(): void
    {
        $product = Product::factory()->create(['rating_avg' => 4.5, 'rating_count' => 2]);
        $this->review($product, 4);
        $owner = User::factory()->create();
        $review = $this->review($product, 5, Review::STATUS_APPROVED, $owner);
        $this->actingAs($owner, 'sanctum')->putJson("/api/reviews/{$review->id}", ['rating' => 2, 'title' => null, 'body' => null])
            ->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.rating', 2);
        $this->assertAggregate($product, 4.0, 1);
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->postJson("/api/admin/reviews/{$review->id}/approve")->assertOk();
        $this->assertAggregate($product, 3.0, 2);
    }

    public function test_stale_route_model_does_not_bypass_pending_reset_after_another_approval(): void
    {
        $product = Product::factory()->create();
        $owner = User::factory()->create();
        $stale = $this->review($product, 5, Review::STATUS_PENDING, $owner);
        app(ReviewService::class)->approve($stale);
        $updated = app(ReviewService::class)->update($owner, $stale, ['rating' => 2]);
        $this->assertSame(Review::STATUS_PENDING, $updated->status);
        $this->assertAggregate($product, 0.0, 0);
    }

    public function test_customer_text_edit_requires_moderation_but_empty_payload_preserves_status(): void
    {
        $product = Product::factory()->create(['rating_avg' => 5, 'rating_count' => 1]);
        $owner = User::factory()->create();
        $review = $this->review($product, 5, Review::STATUS_APPROVED, $owner);
        $this->actingAs($owner, 'sanctum')->putJson("/api/reviews/{$review->id}", [])
            ->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertAggregate($product, 5.0, 1);
        $this->putJson("/api/reviews/{$review->id}", ['body' => 'Updated experience'])
            ->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertAggregate($product, 0.0, 0);
    }

    public function test_rating_filter_and_sorting_use_the_updated_aggregate(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->withVariant()->create(['shop_id' => $shop->id]);
        $other = Product::factory()->withVariant()->create(['rating_avg' => 4.6, 'rating_count' => 1]);
        $reviews = [$this->review($product, 5), $this->review($product, 5), $this->review($product, 4)];
        app(ReviewService::class)->recalculateProductRating($product->id);
        $this->assertAggregate($product, 4.67, 3);
        $this->getJson('/api/catalog/products?rating=4.5&sort=rating')->assertJsonPath('data.0.id', $product->id);
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $this->postJson("/api/admin/reviews/{$reviews[0]->id}/reject")->assertOk();
        $this->assertAggregate($product, 4.5, 2);
        $this->getJson('/api/catalog/products?rating=4.5')->assertJsonCount(2, 'data');
        $this->postJson("/api/admin/reviews/{$reviews[1]->id}/reject")->assertOk();
        $this->assertAggregate($product, 4.0, 1);
        $this->getJson('/api/catalog/products?rating=4.5')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);
        $this->getJson("/api/shops/{$shop->slug}/products?rating=4.5")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/shops/{$shop->slug}")->assertOk()->assertJsonPath('data.rating_avg', 4)->assertJsonPath('data.rating_count', 1);
        $this->getJson('/api/catalog/products?sort=rating')->assertJsonPath('data.0.id', $other->id);
        $this->getJson("/api/products/{$product->id}/reviews")->assertJsonCount(1, 'data')->assertJsonPath('data.0.rating', 4);
    }

    public static function mutations(): array
    {
        return [['reject'], ['approve'], ['admin_delete'], ['customer_delete'], ['update']];
    }

    #[DataProvider('mutations')]
    public function test_aggregate_failure_rolls_back_review_and_notifications_and_returns_safe_error(string $action): void
    {
        config(['app.debug' => true]);
        Log::spy();
        $approved = $action !== 'approve';
        $product = Product::factory()->create(['rating_avg' => $approved ? 5 : 0, 'rating_count' => $approved ? 1 : 0]);
        $owner = User::factory()->create();
        $review = $this->review($product, 5, $approved ? Review::STATUS_APPROVED : Review::STATUS_PENDING, $owner);
        Product::updating(function (Product $current) use ($product): void {
            if ($current->id === $product->id && $current->isDirty('rating_count')) {
                throw new RuntimeException('Internal aggregate failure with private details');
            }
        });

        $this->actingAs(in_array($action, ['customer_delete', 'update']) ? $owner : User::factory()->admin()->create(), 'sanctum');
        $response = match ($action) {
            'admin_delete' => $this->deleteJson("/api/admin/reviews/{$review->id}"),
            'customer_delete' => $this->deleteJson("/api/reviews/{$review->id}"),
            'update' => $this->putJson("/api/reviews/{$review->id}", ['rating' => 2]),
            default => $this->postJson("/api/admin/reviews/{$review->id}/{$action}"),
        };
        $response->assertStatus(503)->assertExactJson(['message' => 'Could not update this review. Please try again.']);
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => $review->status, 'rating' => 5]);
        $this->assertAggregate($product, $approved ? 5.0 : 0.0, $approved ? 1 : 0);
        $this->assertDatabaseCount('notifications', 0);
        Log::shouldHaveReceived('error')->once()->with('Review aggregate operation failed.', \Mockery::on(fn ($context) => $context['product_id'] === $product->id && ! isset($context['body'])));
    }

    public function test_soft_deleted_product_can_be_moderated_without_resource_errors(): void
    {
        $product = Product::factory()->create(['rating_avg' => 5, 'rating_count' => 1]);
        $review = $this->review($product, 5);
        $product->delete();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->postJson("/api/admin/reviews/{$review->id}/reject")->assertOk()->assertJsonPath('data.product', null);
        $archived = Product::withTrashed()->findOrFail($product->id);
        $this->assertSame(0, (int) $archived->rating_count);
        $this->assertSame(0.0, (float) $archived->rating_avg);
    }

    public function test_customer_and_seller_cannot_moderate_or_edit_someone_elses_review(): void
    {
        $shop = Shop::factory()->create();
        $seller = User::factory()->create();
        $shop->users()->attach($seller->id, ['role_in_shop' => 'owner', 'status' => 'active']);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'rating_avg' => 5, 'rating_count' => 1]);
        $review = $this->review($product, 5);
        foreach ([$seller, User::factory()->create()] as $actor) {
            $this->actingAs($actor, 'sanctum')->postJson("/api/admin/reviews/{$review->id}/reject")->assertForbidden();
            $this->deleteJson("/api/admin/reviews/{$review->id}")->assertForbidden();
            $this->putJson("/api/reviews/{$review->id}", ['rating' => 1])->assertNotFound();
        }
        $this->assertAggregate($product, 5.0, 1);
    }

    public function test_many_reviews_are_aggregated_in_sql(): void
    {
        $product = Product::factory()->create();
        $rows = User::factory()->count(300)->create()->map(fn ($user, $index) => [
            'user_id' => $user->id, 'product_id' => $product->id,
            'rating' => $index % 2 ? 5 : 4, 'status' => $index < 200 ? 'approved' : 'pending',
        ])->all();
        Review::insert($rows);
        app(ReviewService::class)->recalculateProductRating($product->id);
        $this->assertAggregate($product, 4.5, 200);
    }
}
