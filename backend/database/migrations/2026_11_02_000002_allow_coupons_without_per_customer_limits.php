<?php

use App\Models\Coupon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Null explicitly means there is no account-based limit. Existing
        // coupons keep their limits and continue to require an account.
        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedInteger('per_user_limit')->nullable()->default(1)->change();
        });
    }

    public function down(): void
    {
        Coupon::whereNull('per_user_limit')->update(['per_user_limit' => 1]);
        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedInteger('per_user_limit')->nullable(false)->default(1)->change();
        });
    }
};
