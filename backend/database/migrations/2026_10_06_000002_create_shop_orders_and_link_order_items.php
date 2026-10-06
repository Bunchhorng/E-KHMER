<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->string('shop_order_number')->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['order_id', 'shop_id']);
            $table->index(['shop_id', 'status']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('shop_order_id')->nullable()->after('order_id')
                ->constrained('shop_orders')->nullOnDelete();
            $table->index(['shop_order_id', 'shop_id']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['shop_order_id', 'shop_id']);
            $table->dropConstrainedForeignId('shop_order_id');
        });

        Schema::dropIfExists('shop_orders');
    }
};
