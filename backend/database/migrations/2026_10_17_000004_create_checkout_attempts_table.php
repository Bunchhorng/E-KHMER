<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('owner_key', 64);
            $table->string('request_key', 80);
            $table->string('payload_hash', 64);
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['owner_key', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_attempts');
    }
};
