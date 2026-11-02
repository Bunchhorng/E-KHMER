<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewRatingRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_without_writes_then_repair_is_targeted_and_idempotent(): void
    {
        $product = Product::factory()->create(['rating_avg' => 5, 'rating_count' => 10]);
        $other = Product::factory()->create(['rating_avg' => 5, 'rating_count' => 10]);
        foreach ([4, 2] as $rating) {
            Review::create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'rating' => $rating, 'status' => 'approved']);
        }
        $before = $product->fresh()->getRawOriginal();
        $this->artisan('reviews:recalculate-ratings', ['--product' => $product->id, '--dry-run' => true])
            ->expectsOutput('Products scanned: 1')->expectsOutput('Incorrect aggregates: 1')
            ->expectsOutput('No database changes performed.')->assertSuccessful();
        $this->assertSame($before, $product->fresh()->getRawOriginal());
        $this->artisan('reviews:recalculate-ratings', ['--product' => $product->id])
            ->expectsOutput('Products repaired: 1')->assertSuccessful();
        $this->assertSame(3.0, (float) $product->fresh()->rating_avg);
        $this->assertSame(2, (int) $product->fresh()->rating_count);
        $this->assertSame(10, (int) $other->fresh()->rating_count);
        $this->artisan('reviews:recalculate-ratings', ['--product' => $product->id])
            ->expectsOutput('Products repaired: 0')->assertSuccessful();
        $this->assertDatabaseCount('reviews', 2);
    }

    public function test_full_scan_includes_archived_products_and_zero_review_rows(): void
    {
        Product::factory()->create(['rating_avg' => 5, 'rating_count' => 1])->delete();
        Product::factory()->create(['rating_avg' => 4, 'rating_count' => 1]);
        Product::factory()->create();
        $this->artisan('reviews:recalculate-ratings')->expectsOutput('Products scanned: 3')
            ->expectsOutput('Incorrect aggregates: 2')->expectsOutput('Products repaired: 2')->assertSuccessful();
        $this->assertSame(0, Product::withTrashed()->where('rating_count', '>', 0)->count());
    }

    public function test_invalid_and_missing_product_options_fail_without_writes(): void
    {
        $product = Product::factory()->create(['rating_avg' => 5, 'rating_count' => 10]);
        foreach (['0', '-1', 'all', '1 OR 1=1', '1.5'] as $value) {
            $this->artisan('reviews:recalculate-ratings', ['--product' => $value])->assertFailed();
        }
        $this->artisan('reviews:recalculate-ratings', ['--product' => 99999])->assertFailed();
        $this->assertSame(10, (int) $product->fresh()->rating_count);
    }
}
