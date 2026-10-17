<?php

namespace Tests\Feature\Console;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Consumer\CheckoutGuest;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use CheckoutGuest, RefreshDatabase;

    public function test_schedule_registers_reservation_cleanup_task(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('checkout:expire-reservations')
            ->assertExitCode(0);
    }

    public function test_schedule_run_expires_stale_reservations(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $this->productWithStock(10, 100.00);
        $this->authAs($user);

        $this->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 3])->assertCreated();
        $orderNumber = $this->beginCheckout()->json('data.order_number');

        $inventory = Inventory::where('product_variant_id', $this->variantId)->firstOrFail();
        $this->assertSame(3, (int) $inventory->reserved_quantity);

        Order::where('order_number', $orderNumber)->update(['placed_at' => now()->subMinutes(16)]);

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame('cancelled', Order::where('order_number', $orderNumber)->firstOrFail()->status);
        $this->assertSame(0, (int) $inventory->fresh()->reserved_quantity);
    }

    public function test_schedule_run_keeps_recent_reservations(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $this->productWithStock(10, 100.00);
        $this->authAs($user);

        $this->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 2])->assertCreated();
        $orderNumber = $this->beginCheckout()->json('data.order_number');

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame('pending', Order::where('order_number', $orderNumber)->firstOrFail()->status);
        $this->assertSame(2, (int) Inventory::where('product_variant_id', $this->variantId)->firstOrFail()->reserved_quantity);
    }
}
