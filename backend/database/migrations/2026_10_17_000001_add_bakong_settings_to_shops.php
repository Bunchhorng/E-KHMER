<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('bakong_account_id', 32)->nullable();
            $table->string('bakong_merchant_name', 25)->nullable();
            $table->string('bakong_merchant_city', 15)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn(['bakong_account_id', 'bakong_merchant_name', 'bakong_merchant_city']));
    }
};
