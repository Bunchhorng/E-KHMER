<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scope variant SKU uniqueness to a branch.
     *
     * `product_variants.sku` carried a global UNIQUE index, which is wrong for
     * a multi-shop catalogue: two branches stocking the same manufacturer SKU
     * is a normal situation, but only one of them could ever list it.
     *
     * The owning shop is denormalised onto the variant (a variant always
     * belongs to exactly one product, and a product to exactly one shop) so the
     * database can enforce the rule that actually matters: unique per branch.
     *
     * Variants whose product has no shop yet keep a NULL shop_id. SQL unique
     * indexes do not treat NULLs as duplicates, so platform-level variants stay
     * unconstrained, and `shop:assign-default` can claim them later.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('product_variants', 'shop_id')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->foreignId('shop_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            });
        }

        // Written as a correlated subquery rather than UPDATE ... JOIN so it
        // runs unchanged on MySQL and on the SQLite in-memory test database.
        DB::table('product_variants')
            ->whereNull('shop_id')
            ->whereNotNull('product_id')
            ->update([
                'shop_id' => DB::raw(
                    '(SELECT p.shop_id FROM products p WHERE p.id = product_variants.product_id)'
                ),
            ]);

        // The old index is named after the column by the `->unique()` call in
        // the original create_product_variants_table migration.
        if (Schema::hasIndex('product_variants', 'product_variants_sku_unique')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropUnique('product_variants_sku_unique');
            });
        }

        if (! Schema::hasIndex('product_variants', 'product_variants_shop_sku_unique')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->unique(['shop_id', 'sku'], 'product_variants_shop_sku_unique');
            });
        }

        if (! Schema::hasIndex('product_variants', ['shop_id'])) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->index('shop_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('product_variants', 'product_variants_shop_sku_unique')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropUnique('product_variants_shop_sku_unique');
            });
        }

        // Restoring the global index can fail if two branches legitimately share
        // a SKU by now; that is a deliberate, visible signal rather than a
        // silent data loss.
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique('sku');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasIndex('product_variants', ['shop_id'])) {
                $table->dropIndex(['shop_id']);
            }
        });

        if (Schema::hasColumn('product_variants', 'shop_id')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropConstrainedForeignId('shop_id');
            });
        }
    }
};
