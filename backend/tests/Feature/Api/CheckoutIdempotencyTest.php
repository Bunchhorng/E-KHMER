<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_returns_the_same_order_and_does_not_reserve_stock_twice(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->create(), 'sanctum');
        $product = Product::factory()->withVariant(10, 5)->create(['shop_id' => Shop::factory()->create()->id]);
        $variant = $product->variants()->first();
        $this->postJson('/api/cart', ['product_variant_id' => $variant->id, 'quantity' => 1])->assertCreated();
        $payload = ['shipping_method_id' => ShippingMethod::factory()->create()->id, 'payment_method' => 'cod',
            'address' => ['full_name' => 'Buyer', 'address_line1' => 'Street', 'city' => 'PP', 'state' => 'PP', 'postal_code' => '12000']];
        $headers = ['Idempotency-Key' => 'checkout-test-key-1'];
        $first = $this->postJson('/api/checkout', $payload, $headers)->assertCreated()->json('data.order_number');
        $this->postJson('/api/checkout', $payload, $headers)->assertOk()->assertJsonPath('data.order_number', $first);
        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals(1, $variant->inventory->fresh()->reserved_quantity);
        $this->postJson('/api/checkout', array_replace($payload, ['note' => 'Changed payload']), $headers)->assertUnprocessable();
        $this->postJson("/api/checkout/{$first}/confirm")->assertOk();
        $this->postJson('/api/checkout', $payload, $headers)->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->assertEquals(4, $variant->inventory->fresh()->quantity);
        $this->assertSame(1, Order::count());
    }
}
