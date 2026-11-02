<?php

namespace Tests\Integration;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Opt-in: run only against the disposable restore-db database described in the F10 report. */
class ReviewAggregateConcurrencyTest extends TestCase
{
    private array $productIds = [];

    private array $userIds = [];

    private array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql'
            || DB::getDatabaseName() !== 'ecommerce_f10_test'
            || config('database.connections.mysql.host') !== 'restore-db') {
            $this->markTestSkipped('Requires the isolated ecommerce_f10_test database on restore-db.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        if ($this->productIds !== []) {
            Product::withTrashed()->whereIn('id', $this->productIds)->forceDelete();
            User::whereIn('id', $this->userIds)->delete();
        }
        parent::tearDown();
    }

    private function product(): Product
    {
        $product = Product::factory()->create();
        $this->productIds[] = $product->id;

        return $product;
    }

    private function review(Product $product, int $rating, string $status): Review
    {
        $user = User::factory()->create();
        $this->userIds[] = $user->id;

        return Review::create(['user_id' => $user->id, 'product_id' => $product->id, 'rating' => $rating, 'status' => $status]);
    }

    private function worker(Review $review, string $action): Process
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/review-aggregate-worker.php'), (string) $review->id, $action], base_path(), timeout: 25);
        $this->processes[] = $process;
        $process->start();
        $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'ready')), $process->getErrorOutput());

        return $process;
    }

    public function test_competing_moderators_wait_for_one_product_and_leave_other_products_unblocked(): void
    {
        $product = $this->product();
        $this->review($product, 4, 'approved');
        $approve = $this->review($product, 3, 'pending');
        $reject = $this->review($product, 5, 'approved');
        app(ReviewService::class)->recalculateProductRating($product->id);
        $other = $this->product();
        $otherReview = $this->review($other, 2, 'pending');

        DB::beginTransaction();
        Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
        $a = $this->worker($approve, 'approve');
        $b = $this->worker($reject, 'reject');
        $independent = $this->worker($otherReview, 'approve');
        $this->assertSame(0, $independent->wait(), $independent->getErrorOutput());
        $this->assertTrue($a->isRunning());
        $this->assertTrue($b->isRunning());
        DB::commit();
        $this->assertSame(0, $a->wait(), $a->getErrorOutput());
        $this->assertSame(0, $b->wait(), $b->getErrorOutput());

        $this->assertSame('approved', $approve->fresh()->status);
        $this->assertSame('rejected', $reject->fresh()->status);
        $this->assertSame(2, (int) $product->fresh()->rating_count);
        $this->assertSame(3.5, (float) $product->fresh()->rating_avg);
        $this->assertSame(1, (int) $other->fresh()->rating_count);
        $this->assertSame(2.0, (float) $other->fresh()->rating_avg);
    }

    public function test_repair_reads_current_approved_rows_even_inside_an_older_repeatable_read_snapshot(): void
    {
        $product = $this->product();
        $this->review($product, 4, 'approved');
        $reject = $this->review($product, 5, 'approved');
        app(ReviewService::class)->recalculateProductRating($product->id);
        DB::beginTransaction();
        $this->assertSame(2, Review::where('product_id', $product->id)->approved()->count());

        $worker = $this->worker($reject, 'reject');
        $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
        // The original snapshot still sees the old approved row.
        $this->assertSame(2, Review::where('product_id', $product->id)->approved()->count());
        app(ReviewService::class)->recalculateProductRating($product->id);
        DB::commit();
        $this->assertSame(1, (int) $product->fresh()->rating_count);
        $this->assertSame(4.0, (float) $product->fresh()->rating_avg);
    }
}
