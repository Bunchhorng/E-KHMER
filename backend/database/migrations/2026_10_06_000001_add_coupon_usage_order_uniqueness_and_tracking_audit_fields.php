<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->unique('order_id', 'coupon_usages_order_unique');
        });

        Schema::table('tracking_events', function (Blueprint $table) {
            $table->string('from_status')->nullable()->after('order_id');
            $table->foreignId('changed_by')->nullable()->after('description')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tracking_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('changed_by');
            $table->dropColumn('from_status');
        });

        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->dropUnique('coupon_usages_order_unique');
        });
    }
};
