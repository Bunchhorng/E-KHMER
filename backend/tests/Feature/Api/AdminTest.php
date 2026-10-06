<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_routes_require_authentication(): void
    {
        $this->getJson('/api/admin/dashboard/overview')->assertStatus(401);
    }

    public function test_customer_is_forbidden_from_admin_routes(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/dashboard/overview')
            ->assertStatus(403);
    }

    public function test_dashboard_overview_returns_shape(): void
    {
        Product::factory()->withVariant()->create(['price' => 50]);
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/dashboard/overview')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'metrics' => ['total_revenue', 'orders_count', 'customers_count', 'pending_orders', 'low_stock_products'],
                    'revenue_trend',
                    'status_distribution',
                    'sales_by_category',
                ],
            ]);
    }

    public function test_order_transition_state_machine_is_strict(): void
    {
        $user = $this->admin();
        $order = $this->orderFixture('pending', 'unpaid');

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/orders/{$order->id}/transition", ['status' => 'delivered'])
            ->assertStatus(422)
            ->assertJsonPath('errors.message.0', 'Invalid status transition from pending to delivered');

        $path = ['confirmed', 'processing', 'shipped', 'delivered'];
        $current = $order;

        foreach ($path as $to) {
            $written = $this->actingAs($user, 'sanctum')
                ->putJson("/api/admin/orders/{$current->id}/transition", ['status' => $to])
                ->assertOk()
                ->json('data');
            $this->assertSame($to, $written['status']);
            $current = Order::findOrFail($current->id);
        }

        $shipment = $current->shipments()->first();
        $this->assertSame('delivered', $shipment->fresh()->status);
        $this->assertNotNull($shipment->fresh()->delivered_at);
    }

    public function test_order_status_history_records_actor_and_previous_status(): void
    {
        $admin = $this->admin();
        $order = $this->orderFixture('pending', 'unpaid');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/orders/{$order->id}/transition", [
                'status' => 'confirmed',
                'note' => 'Payment verified by support',
            ])
            ->assertOk()
            ->assertJsonPath('data.tracking_events.0.from_status', 'pending')
            ->assertJsonPath('data.tracking_events.0.status', 'confirmed')
            ->assertJsonPath('data.tracking_events.0.changed_by.id', $admin->id);

        $this->assertDatabaseHas('tracking_events', [
            'order_id' => $order->id,
            'from_status' => 'pending',
            'status' => 'confirmed',
            'changed_by' => $admin->id,
            'description' => 'Payment verified by support',
        ]);
    }

    public function test_refund_sets_payment_refunded(): void
    {
        $user = $this->admin();
        $order = $this->orderFixture('shipped', 'paid');

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/orders/{$order->id}/transition", ['status' => 'refunded'])
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.payment.status', 'refunded');
    }

    public function test_admin_order_list_supports_status_filter(): void
    {
        Order::factory()->create(['status' => 'pending']);
        Order::factory()->delivered()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/orders?status=delivered')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'delivered');
    }

    public function test_customer_order_list_is_scoped(): void
    {
        Order::factory()->create(['user_id' => User::factory()->create()->id]);
        Order::factory()->create(['user_id' => User::factory()->create()->id]);

        $customer = User::factory()->create();
        Order::factory()->create(['user_id' => $customer->id]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_order_list_includes_numeric_id(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.order_number', $order->order_number);
    }

    public function test_admin_customer_show_includes_recent_orders(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => 'delivered',
            'payment_status' => 'paid',
            'total' => 25,
        ]);
        $product = Product::factory()->withVariant()->create(['price' => 25]);
        $variant = $product->variants()->first();
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_label' => $variant->name,
            'sku' => $variant->sku,
            'unit_price' => 25,
            'quantity' => 1,
            'line_total' => 25,
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/admin/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('user.id', $customer->id)
            ->assertJsonPath('orders_count', 1)
            ->assertJsonPath('lifetime_spend', 25)
            ->assertJsonCount(1, 'recent_orders')
            ->assertJsonStructure(['recent_orders' => [['id', 'order_number', 'status']]]);
    }

    public function test_admin_orders_report_pdf_downloads(): void
    {
        $this->orderFixture('pending', 'unpaid');
        $this->orderFixture('confirmed', 'paid');

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->get('/api/admin/reports/orders.pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_admin_order_receipt_pdf_downloads(): void
    {
        $order = $this->orderFixture('confirmed', 'paid');

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->get("/api/admin/orders/{$order->id}/receipt")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_product_edit_response_includes_inactive_variants(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->withVariant()->create();
        $variant = $product->variants()->firstOrFail();
        $variant->update(['is_active' => false]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/products/{$product->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.variants')
            ->assertJsonPath('data.variants.0.id', $variant->id)
            ->assertJsonPath('data.variants.0.is_active', false);

        // The edit screen resubmits every variant it receives. Confirm that an
        // inactive row is retained rather than being treated as an omission.
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/products/{$product->id}", [
                'name' => 'Updated product',
                'variants' => [[
                    'id' => $variant->id,
                    'is_active' => false,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'product_id' => $product->id,
            'is_active' => false,
            'deleted_at' => null,
        ]);
    }

    private function orderFixture(string $status, string $paymentStatus): Order
    {
        $user = User::factory()->create();
        $product = Product::factory()->withVariant()->create(['price' => 25]);
        $variant = $product->variants()->first();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
            'payment_status' => $paymentStatus,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_label' => $variant->name,
            'sku' => $variant->sku,
            'unit_price' => 25,
            'quantity' => 1,
            'line_total' => 25,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'method' => 'card',
            'status' => $paymentStatus === 'paid' ? 'completed' : 'pending',
            'amount' => 25,
        ]);

        Shipment::create([
            'order_id' => $order->id,
            'status' => $paymentStatus === 'paid' ? 'shipped' : 'pending',
            'address_snapshot' => ['full_name' => 'x'],
        ]);

        return $order;
    }
}
