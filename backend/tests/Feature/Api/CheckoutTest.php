<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Consumer\CheckoutGuest;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase, CheckoutGuest;

    public function test_begin_reserves_stock_and_creates_pending_order(): void
    {
        $user = User::factory()->create();
        $this->productWithStock(10, 100.00);
        $this->authAs($user);

        $this->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 2])->assertCreated();

        $response = $this->beginCheckout()->assertStatus(201);

        $orderNumber = $response->json('data.order_number');
        $response->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonStructure(['reservation_expires_at']);

        $inventory = Inventory::where('product_variant_id', $this->variantId)->firstOrFail();
        $this->assertSame(2, (int) $inventory->reserved_quantity);

        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertNotNull($order->payment);
        $this->assertSame('pending', $order->payment->status);
        $this->assertNotNull($order->shipments()->first());
        $this->assertSame(225.0, (float) $order->total);
    }

    public function test_checkout_requires_non_empty_cart(): void
    {
        $user = User::factory()->create();
        $this->authAs($user);

        $this->beginCheckout()->assertStatus(422)
            ->assertJsonPath('errors.message.0', 'Your cart is empty');
    }

    public function test_confirm_deducts_and_marks_paid(): void
    {
        $user = User::factory()->create();
        $this->productWithStock(10, 100.00);
        $this->authAs($user);

        $this->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 2])->assertCreated();
        $orderNumber = $this->beginCheckout()->json('data.order_number');

        $response = $this->postJson("/api/checkout/$orderNumber/confirm", ['transaction_id' => 'PAY-TEST-1'])
            ->assertOk();

        $response->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.payment.status', 'completed');

        $inventory = Inventory::where('product_variant_id', $this->variantId)->firstOrFail();
        $this->assertSame(8, (int) $inventory->quantity);
        $this->assertSame(0, (int) $inventory->reserved_quantity);
        $this->assertSame(2, (int) $inventory->sold_count);
    }

    public function test_double_confirm_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->productWithStock(10);
        $this->authAs($user);

        $this->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 1])->assertCreated();
        $orderNumber = $this->beginCheckout()->json('data.order_number');

        $this->postJson("/api/checkout/$orderNumber/confirm")->assertOk();
        $this->postJson("/api/checkout/$orderNumber/confirm")
            ->assertStatus(422)
            ->assertJsonPath('errors.message.0', 'Order already settled');
    }

    public function test_cancel_releases_reservation(): void
    {
        $user = User::factory()->create();
        $this->productWithStock(10);
        $this->authAs($user);

        $this->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 3])->assertCreated();
        $orderNumber = $this->beginCheckout()->json('data.order_number');

        $inventory = Inventory::where('product_variant_id', $this->variantId)->firstOrFail();
        $this->assertSame(3, (int) $inventory->reserved_quantity);

        $this->postJson("/api/checkout/$orderNumber/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $inventory->refresh();
        $this->assertSame(0, (int) $inventory->reserved_quantity);
        $this->assertSame(10, (int) $inventory->quantity);
    }

    public function test_begin_rejects_when_stock_insufficient(): void
    {
        $user = User::factory()->create();
        $this->productWithStock(2, 100.00);
        $this->authAs($user);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $this->variantId,
            'quantity' => 5,
        ]);

        $this->beginCheckout()->assertStatus(422)
            ->assertJsonPath('errors.message.0', 'Insufficient stock. Some items in your cart are no longer available.');

        $inventory = Inventory::where('product_variant_id', $this->variantId)->firstOrFail();
        $this->assertSame(0, (int) $inventory->reserved_quantity);
    }

    public function test_order_isolates_cart_items_with_coupon_discount(): void
    {
        $user = User::factory()->create();
        $this->productWithStock(30, 100.00);
        $this->authAs($user);

        $coupon = Coupon::factory()->create([
            'code' => 'SAVE10',
            'type' => 'percentage',
            'value' => 10,
            'max_discount_amount' => null,
        ]);

        $this->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 2])->assertCreated();

        $response = $this->beginCheckout(overrides: ['coupon_code' => 'SAVE10'])->assertStatus(201);

        $this->assertSame(200.0, (float) $response->json('data.subtotal'));
        $this->assertSame(20.0, (float) $response->json('data.discount_amount'));
        $this->assertSame('SAVE10', $response->json('data.coupon_code'));

        $order = Order::where('order_number', $response->json('data.order_number'))->firstOrFail();
        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);
        $this->assertSame(1, (int) $coupon->fresh()->used_count);
    }

    public function test_guest_checkout_reserves_confirms_and_denies_other_sessions(): void
    {
        $this->productWithStock(10, 100.00);
        $session = 'guest-session-1';

        $this->withHeaders(['X-Session-Id' => $session])
            ->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 1])
            ->assertCreated();

        $begin = $this->withHeaders(['X-Session-Id' => $session])
            ->postJson('/api/checkout', [
                'shipping_method_id' => $this->seedShipping(),
                'payment_method' => 'card',
                'address' => $this->addressPayload(),
            ])->assertStatus(201);

        $orderNumber = $begin->json('data.order_number');
        $inventory = Inventory::where('product_variant_id', $this->variantId)->firstOrFail();
        $this->assertSame(1, (int) $inventory->reserved_quantity);

        $this->withHeaders(['X-Session-Id' => $session])
            ->postJson("/api/checkout/$orderNumber/confirm", ['transaction_id' => 'PAY-GUEST'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->withHeaders(['X-Session-Id' => $session])
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->withHeaders(['X-Session-Id' => 'some-other-session'])
            ->postJson("/api/checkout/$orderNumber/cancel")
            ->assertStatus(404);
    }

    public function test_checkout_rejects_a_variant_deactivated_after_it_was_added_to_cart(): void
    {
        $user = User::factory()->create();
        $this->productWithStock(10, 100.00);
        $this->authAs($user);

        $this->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 1])
            ->assertCreated();
        \App\Models\ProductVariant::whereKey($this->variantId)->update(['is_active' => false]);

        $this->beginCheckout()
            ->assertStatus(422)
            ->assertJsonPath('errors.cart.0', 'One or more items in your cart are no longer available.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_orders_list_is_scoped_to_own_orders(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Order::factory()->create(['user_id' => $user->id]);
        $otherOrder = Order::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/orders/{$otherOrder->order_number}")
            ->assertStatus(404);
    }

    public function test_customer_receipt_pdf_is_scoped_to_own_orders(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $otherOrder = Order::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->get("/api/orders/{$order->order_number}/receipt")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', (string) $response->getContent());

        $this->actingAs($user, 'sanctum')
            ->get("/api/orders/{$otherOrder->order_number}/receipt")
            ->assertStatus(404);
    }
}
