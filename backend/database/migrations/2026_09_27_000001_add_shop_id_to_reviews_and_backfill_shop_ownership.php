<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reviews belong to the branch selling the reviewed product. The column was
     * missing entirely, which left branch managers unable to moderate (or even
     * list) reviews for their own products without a per-row product join.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('reviews', 'shop_id')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->foreignId('shop_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
                $table->index(['shop_id', 'status']);
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        if (Schema::hasColumn('reviews', 'shop_id')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropConstrainedForeignId('shop_id');
                $table->dropIndex(['shop_id', 'status']);
            });
        }
    }

    /**
     * Backfill every shop_id that is still NULL by walking down the ownership
     * chain: variant -> product -> shop. Written with correlated subqueries
     * rather than UPDATE ... JOIN so it runs unchanged on both MySQL and the
     * SQLite in-memory database the test suite uses. A row whose source shop is
     * itself NULL is assigned NULL, so unresolvable rows stay unclaimed for
     * `shop:assign-default` to pick up later.
     */
    private function backfill(): void
    {
        // Reviews and order items inherit the shop of the referenced product.
        foreach (['reviews', 'order_items'] as $table) {
            DB::table($table)
                ->whereNull('shop_id')
                ->whereNotNull('product_id')
                ->update([
                    'shop_id' => DB::raw(
                        "(SELECT p.shop_id FROM products p WHERE p.id = {$table}.product_id)"
                    ),
                ]);
        }

        // Inventories inherit the shop of the owning product variant.
        DB::table('inventories')
            ->whereNull('shop_id')
            ->whereNotNull('product_variant_id')
            ->update([
                'shop_id' => DB::raw(
                    '(SELECT p.shop_id FROM product_variants pv'
                    .' JOIN products p ON p.id = pv.product_id'
                    .' WHERE pv.id = inventories.product_variant_id)'
                ),
            ]);

        // Transactions mirror the shop of the inventory row they belong to.
        DB::table('inventory_transactions')
            ->whereNull('shop_id')
            ->whereNotNull('inventory_id')
            ->update([
                'shop_id' => DB::raw(
                    '(SELECT i.shop_id FROM inventories i WHERE i.id = inventory_transactions.inventory_id)'
                ),
            ]);

        $defaultShopId = DB::table('shops')
            ->where('is_default', true)
            ->orderBy('id')
            ->value('id');

        if ($defaultShopId === null) {
            return;
        }

        // Coupons and shipping methods predate branch scoping and have no
        // product to inherit from. They are platform-level configuration, so
        // attach them to the default shop to make them explicitly visible to it
        // instead of leaving an ambiguous NULL.
        DB::table('coupons')->whereNull('shop_id')->update(['shop_id' => $defaultShopId]);
        DB::table('shipping_methods')->whereNull('shop_id')->update(['shop_id' => $defaultShopId]);
    }
};
