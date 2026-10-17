<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bakong_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('shop_order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->string('receiver_account', 32);
            $table->string('payer_account')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->text('qr_payload')->nullable();
            $table->string('md5', 32)->nullable()->unique();
            $table->string('transaction_hash', 64)->nullable()->unique();
            $table->string('refund_hash', 64)->nullable()->unique();
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bakong_payments');
    }
};
