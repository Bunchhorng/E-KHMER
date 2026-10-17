<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('barcode')->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->index(['shop_id', 'barcode']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->index(['shop_id', 'barcode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'barcode']);
            $table->dropColumn(['barcode', 'cost_price']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'barcode']);
        });
    }
};
