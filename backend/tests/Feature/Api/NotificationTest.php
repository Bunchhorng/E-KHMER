<?php

namespace Tests\Feature\Api;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusNotification;
use App\Notifications\ReviewApprovedNotification;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Consumer\CheckoutGuest;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase, CheckoutGuest;

    public function test_order_confirmation_dispatches_order_placed_notification(): void
    {
        $user = User::factory()->create();
        $this->productWithStock(10, 100.00);
        $this->authAs($user);

        $this->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 2])->assertCreated();
        $orderNumber = $this->beginCheckout()->json('data.order_number');

        $this->postJson("/api/checkout/$orderNumber/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'type' => OrderPlacedNotification::class,
        ]);
    }

    public function test_shipping_transition_dispatches_order_status_notification(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->productWithStock(10, 100.00);
        $this->authAs($user);

        $this->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 1])->assertCreated();
        $orderNumber = $this->beginCheckout()->json('data.order_number');
        $this->postJson("/api/checkout/$orderNumber/confirm")->assertOk();

        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/orders/{$order->id}/transition", ['status' => 'processing'])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/orders/{$order->id}/transition", ['status' => 'shipped'])
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'type' => OrderStatusNotification::class,
        ]);

        $title = $user->notifications()
            ->where('type', OrderStatusNotification::class)
            ->get()
            ->first(fn ($n) => ($n->data['status'] ?? null) === Order::STATUS_SHIPPED)
            ->data['title'] ?? null;

        $this->assertSame('Order shipped', $title);
    }

    public function test_review_approval_notifies_reviewer(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withVariant()->create(['price' => 49.99]);
        $variant = $product->variants()->first();

        $order = Order::factory()->delivered()->create(['user_id' => $user->id]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'unit_price' => 49.99,
            'quantity' => 1,
            'line_total' => 49.99,
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 5,
            'title' => 'Great',
            'body' => 'Superb.',
        ])->assertStatus(201);

        $review = Review::query()->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/reviews/{$review->id}/approve")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'type' => ReviewApprovedNotification::class,
        ]);
    }

    public function test_low_stock_alerts_admins_only_once(): void
    {
        $admin = User::factory()->admin()->create();
        $this->productWithStock(10, 100.00);

        $service = app(InventoryService::class);
        $service->reserve($this->variantId, 6);

        $this->assertSame(1, $admin->notifications()->where('type', LowStockNotification::class)->count());

        $service->reserve($this->variantId, 2);
        $this->assertSame(1, $admin->notifications()->where('type', LowStockNotification::class)->count());

        $service->adjust($this->variantId, 20);
        $this->assertNull(Inventory::where('product_variant_id', $this->variantId)->firstOrFail()->low_stock_notified_at);

        $service->reserve($this->variantId, 10);
        $this->assertSame(2, $admin->notifications()->where('type', LowStockNotification::class)->count());
    }

    public function test_email_preferences_keep_database_notifications_but_disable_mail_delivery(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $inventory = Product::factory()->withVariant(10, 2)->create()->variants()->firstOrFail()->inventory;

        Setting::set('emailOrderNotifications', '0');
        Setting::set('emailLowStockAlerts', '0');

        $this->assertSame(['database'], (new OrderPlacedNotification($order))->via($user));
        $this->assertSame(['database'], (new OrderStatusNotification($order, Order::STATUS_PROCESSING))->via($user));
        $this->assertSame(['database'], (new LowStockNotification($inventory, 'Product', 'Default', 2))->via($user));
    }

    public function test_notifications_list_returns_friendly_type_and_mark_read(): void
    {
        $user = User::factory()->create();
        $this->productWithStock(10, 100.00);
        $this->authAs($user);

        $this->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 1])->assertCreated();
        $orderNumber = $this->beginCheckout()->json('data.order_number');
        $this->postJson("/api/checkout/$orderNumber/confirm")->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/account/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'order')
            ->assertJsonPath('data.0.title', 'Order confirmed');

        $id = $user->notifications()->first()->id;

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/account/notifications/$id/read")
            ->assertOk();

        $this->assertNotNull($user->notifications()->first()->read_at);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/account/notifications/all/read')
            ->assertOk();
    }
}
