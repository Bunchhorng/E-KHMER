<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('shipments')->whereNotNull('shop_order_id')->where('status', 'pending')
            ->whereIn('shop_order_id', function ($query): void {
                $query->select('shop_order_id')->from('tracking_events')->whereNotNull('shop_order_id')
                    ->where('description', 'Existing shop order status imported; original platform tracking is retained.');
            })
            ->update(['shipped_at' => null, 'delivered_at' => null]);
    }

    public function down(): void
    {
        // Original dates remain on platform shipments; generated pre-dispatch dates must not be reintroduced.
    }
};
