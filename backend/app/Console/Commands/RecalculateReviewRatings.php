<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ReviewService;
use Illuminate\Console\Command;

class RecalculateReviewRatings extends Command
{
    protected $signature = 'reviews:recalculate-ratings
                            {--product= : Limit to one product ID, including archived products}
                            {--dry-run : Report mismatches without changing any rows}';

    protected $description = 'Recompute approved review aggregates and repair only incorrect products';

    public function handle(ReviewService $reviews): int
    {
        $productId = $this->option('product');
        if ($productId !== null && (! ctype_digit((string) $productId) || (int) $productId < 1)) {
            $this->error('The --product option must be a positive integer.');

            return self::FAILURE;
        }

        $query = Product::withTrashed()->select('id');
        if ($productId !== null) {
            $query->whereKey((int) $productId);
        }

        $scanned = 0;
        $incorrect = 0;
        $dryRun = (bool) $this->option('dry-run');
        foreach ($query->lazyById(200) as $product) {
            $scanned++;
            if ($reviews->recalculateProductRating($product->id, $dryRun)) {
                $incorrect++;
                $this->line("Product #{$product->id}: ".($dryRun ? 'incorrect aggregate' : 'aggregate repaired'));
            }
        }

        if ($productId !== null && $scanned === 0) {
            $this->error('Product not found.');

            return self::FAILURE;
        }

        $this->info("Products scanned: {$scanned}");
        $this->info("Incorrect aggregates: {$incorrect}");
        $this->info($dryRun ? 'No database changes performed.' : "Products repaired: {$incorrect}");

        return self::SUCCESS;
    }
}
