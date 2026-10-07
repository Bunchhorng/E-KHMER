<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('shop_order_id')->nullable()->constrained('shop_orders')->nullOnDelete();
            $table->unique('shop_order_id');
        });
        Schema::table('tracking_events', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
            $table->foreignId('shop_order_id')->nullable()->constrained('shop_orders')->cascadeOnDelete();
            $table->index(['shop_order_id', 'created_at']);
        });

        DB::table('shop_orders')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $order = DB::table('orders')->where('id', $row->order_id)->first();
                if ($order === null) {
                    continue;
                }
                $status = $row->status === 'pending' ? $order->status : $row->status;
                DB::table('shop_orders')->where('id', $row->id)->update(['status' => $status]);
                $legacyShipment = DB::table('shipments')->where('order_id', $row->order_id)->whereNull('shop_order_id')->orderBy('id')->first();
                DB::table('shipments')->insert([
                    'order_id' => $row->order_id,
                    'shop_order_id' => $row->id,
                    'shipping_method_id' => $legacyShipment?->shipping_method_id,
                    'status' => match ($status) {
                        'shipped' => 'shipped',
                        'delivered' => 'delivered',
                        default => 'pending',
                    },
                    'address_snapshot' => $legacyShipment?->address_snapshot ?? $order->shipping_address,
                    'shipped_at' => $legacyShipment?->shipped_at,
                    'delivered_at' => $legacyShipment?->delivered_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('tracking_events')->insert([
                    'order_id' => $row->order_id,
                    'shop_order_id' => $row->id,
                    'status' => $status,
                    'description' => 'Existing shop order status imported; original platform tracking is retained.',
                    'created_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tracking_events', function (Blueprint $table) {
            $table->dropIndex(['shop_order_id', 'created_at']);
            $table->dropConstrainedForeignId('shop_order_id');
        });
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique(['shop_order_id']);
            $table->dropConstrainedForeignId('shop_order_id');
        });
    }
};
