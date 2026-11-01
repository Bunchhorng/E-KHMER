<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreignId('shop_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['shop_order_id', 'type'], 'shop_payment_transaction_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropForeign(['shop_order_id']);
            $table->dropUnique('shop_payment_transaction_type_unique');
            $table->dropColumn('shop_order_id');
        });
    }
};
