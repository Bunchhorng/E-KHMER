<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 30)->default('requested')->index();
            $table->text('reason');
            $table->text('resolution_note')->nullable();
            $table->json('photos')->nullable();
            $table->decimal('refund_amount', 12, 2);
            $table->boolean('restocked')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('bakong_payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_return_id')->nullable()->unique()->constrained('order_returns')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('reference')->unique();
            $table->string('method', 30);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('support_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 190);
            $table->string('subject', 190);
            $table->text('message');
            $table->string('status', 20)->default('open')->index();
            $table->text('reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('newsletter_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscriptions');
        Schema::dropIfExists('support_requests');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('order_returns');
    }
};
