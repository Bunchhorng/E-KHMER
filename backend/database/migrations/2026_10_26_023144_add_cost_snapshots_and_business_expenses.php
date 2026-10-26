<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 12, 2)->nullable()->after('unit_price');
        });
        Schema::create('business_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('incurred_on');
            $table->string('category', 30);
            $table->string('description', 500);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['shop_id', 'currency', 'incurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_expenses');
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('unit_cost');
        });
    }
};
