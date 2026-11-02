<?php

namespace Tests\Feature\Api;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\Consumer\CheckoutGuest;
use Tests\TestCase;

class LowStockLifecycleTest extends TestCase
{
    use CheckoutGuest, RefreshDatabase;

    private function stock(?Shop $shop = null, int $quantity = 10): Inventory
    {
        Setting::set('emailLowStockAlerts', '0');

        return Product::factory()->withVariant(10, $quantity)->create(['shop_id' => $shop?->id])
            ->variants()->firstOrFail()->inventory;
    }

    private function alerts(User $user): int
    {
        return $user->notifications()->where('type', LowStockNotification::class)->count();
    }

    public function test_manual_recovery_rearms_a_new_episode_and_preserves_the_ledger(): void
    {
        $this->freezeTime();
        $admin = User::factory()->admin()->create();
        $stock = $this->stock();
        $service = app(InventoryService::class);
        $id = (int) $stock->product_variant_id;

        $service->checkLowStock($stock);
        $this->assertSame(0, $this->alerts($admin));
        $this->assertNull($stock->fresh()->low_stock_notified_at);

        $service->adjust($id, 4);
        $first = $stock->fresh()->low_stock_notified_at;
        $this->assertNotNull($first);
        $this->assertSame(1, $this->alerts($admin));
        $service->checkLowStock($stock); // Deliberately pass a stale instance.
        $service->adjust($id, 3);
        $this->assertSame(1, $this->alerts($admin));

        $service->adjust($id, 10);
        $this->assertNull($stock->fresh()->low_stock_notified_at);
        $this->travel(1)->minutes();
        $service->adjust($id, 4);
        $this->assertTrue($stock->fresh()->low_stock_notified_at->greaterThan($first));
        $this->assertSame(2, $this->alerts($admin));
        $this->assertSame([-6, -1, 7, -6], $stock->transactions()->orderBy('id')->pluck('quantity')->all());
    }

    public function test_threshold_is_inclusive_and_zero_remains_the_same_episode(): void
    {
        $admin = User::factory()->admin()->create();
        $stock = $this->stock();
        $service = app(InventoryService::class);
        foreach ([6, 5, 4, 5, 0] as $quantity) {
            $service->adjust((int) $stock->product_variant_id, $quantity);
        }
        $this->assertSame(1, $this->alerts($admin));
        $this->assertNotNull($stock->fresh()->low_stock_notified_at);

        $service->adjust((int) $stock->product_variant_id, 6);
        $this->assertNull($stock->fresh()->low_stock_notified_at);
        $service->adjust((int) $stock->product_variant_id, 5);
        $this->assertSame(2, $this->alerts($admin));
    }

    public function test_reservation_release_rearms_only_after_available_stock_recovers(): void
    {
        $admin = User::factory()->admin()->create();
        $stock = $this->stock();
        $service = app(InventoryService::class);
        $id = (int) $stock->product_variant_id;

        $this->assertTrue($service->reserve($id, 6));
        $this->assertSame(4, $stock->fresh()->available_quantity);
        $this->assertSame(1, $this->alerts($admin));
        $service->release($id, 1);
        $this->assertNotNull($stock->fresh()->low_stock_notified_at);
        $service->releaseMany([$id => 5]);
        $this->assertNull($stock->fresh()->low_stock_notified_at);
        $this->assertSame(10, $stock->fresh()->available_quantity);

        $service->reserve($id, 6);
        $service->deduct($id, 6);
        $this->assertSame(2, $this->alerts($admin));
        $this->assertSame(4, $stock->fresh()->available_quantity);
        $this->assertSame(6, (int) $stock->fresh()->sold_count);
        $service->restockMany([$id => 6]);
        $this->assertNull($stock->fresh()->low_stock_notified_at);
        $this->assertSame(0, (int) $stock->fresh()->sold_count);
        $this->assertSame(['reserve', 'release', 'release', 'reserve', 'deduct', 'restock'],
            $stock->transactions()->orderBy('id')->pluck('type')->all());
    }

    #[TestWith(['checkout'])]
    #[TestWith(['order'])]
    #[TestWith(['expiry'])]
    public function test_pending_order_release_paths_clear_the_episode(string $path): void
    {
        $this->freezeTime();
        $admin = User::factory()->admin()->create();
        Setting::set('emailLowStockAlerts', '0');
        $buyer = User::factory()->create();
        $this->productWithStock(10, 10);
        $this->authAs($buyer);
        $this->postJson('/api/cart', ['product_variant_id' => $this->variantId, 'quantity' => 6])->assertCreated();
        $number = $this->beginCheckout()->assertCreated()->json('data.order_number');
        $stock = Inventory::where('product_variant_id', $this->variantId)->firstOrFail();
        $this->assertNotNull($stock->low_stock_notified_at);

        match ($path) {
            'checkout' => $this->postJson("/api/checkout/$number/cancel")->assertOk(),
            'order' => $this->postJson("/api/orders/$number/cancel")->assertOk(),
            'expiry' => (function (): void {
                $this->travel(16)->minutes();
                $this->assertSame(1, app(CheckoutService::class)->expireStaleReservations());
            })(),
        };

        $this->assertNull($stock->fresh()->low_stock_notified_at);
        $this->assertSame(10, $stock->fresh()->available_quantity);
        $this->assertSame(Order::STATUS_CANCELLED, Order::where('order_number', $number)->sole()->status);
        app(InventoryService::class)->reserve($this->variantId, 6);
        $this->assertSame(2, $this->alerts($admin));
    }

    #[TestWith(['cancelled'])]
    #[TestWith(['refunded'])]
    public function test_confirmed_order_reversal_restocks_and_rearms(string $status): void
    {
        $stock = $this->stock();
        $service = app(InventoryService::class);
        $service->reserve((int) $stock->product_variant_id, 6);
        $service->deduct((int) $stock->product_variant_id, 6);
        $order = Order::factory()->create(['status' => Order::STATUS_CONFIRMED]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $stock->variant->product_id,
            'product_variant_id' => $stock->product_variant_id, 'product_name' => 'Item',
            'unit_price' => 10, 'quantity' => 6, 'line_total' => 60]);

        app(OrderService::class)->transition($order, $status);

        $this->assertSame(10, $stock->fresh()->available_quantity);
        $this->assertNull($stock->fresh()->low_stock_notified_at);
        $this->assertSame(0, (int) $stock->fresh()->sold_count);
    }

    public function test_received_return_rearms_without_changing_return_accounting(): void
    {
        $stock = $this->stock(quantity: 3);
        $service = app(InventoryService::class);
        $service->checkLowStock($stock);
        $order = Order::factory()->delivered()->create();
        $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $stock->variant->product_id,
            'product_variant_id' => $stock->product_variant_id, 'product_name' => 'Item',
            'unit_price' => 10, 'quantity' => 5, 'line_total' => 50]);
        $return = OrderReturn::create(['order_id' => $order->id, 'order_item_id' => $item->id,
            'user_id' => $order->user_id, 'quantity' => 5, 'reason' => 'Wrong size', 'status' => 'approved', 'refund_amount' => 50]);

        app(ReturnService::class)->transition($return, 'received', User::factory()->admin()->create(), null, null, true);

        $this->assertSame(8, $stock->fresh()->available_quantity);
        $this->assertNull($stock->fresh()->low_stock_notified_at);
        $this->assertSame('restock', $stock->transactions()->sole()->type);
        $this->assertSame('received', $return->fresh()->status);
    }

    public function test_threshold_settings_immediately_start_and_end_episodes(): void
    {
        $admin = User::factory()->admin()->create();
        $stock = $this->stock(quantity: 8);
        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/settings', ['lowStockThreshold' => 10])->assertOk();
        $this->assertSame(1, $this->alerts($admin));
        $this->assertNotNull($stock->fresh()->low_stock_notified_at);

        $this->putJson('/api/admin/settings', ['lowStockThreshold' => 5])->assertOk();
        $this->assertNull($stock->fresh()->low_stock_notified_at);
        $this->putJson('/api/admin/settings', ['lowStockThreshold' => 10])->assertOk();
        $this->assertSame(2, $this->alerts($admin));
    }

    public function test_only_own_active_managers_and_super_admins_receive_alerts_once(): void
    {
        $shop = Shop::factory()->create();
        $other = Shop::factory()->create();
        $stock = $this->stock($shop);
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $staff = User::factory()->create();
        $suspended = User::factory()->create();
        $outsider = User::factory()->create();
        foreach ([[$admin, 'owner', 'active'], [$owner, 'owner', 'active'], [$manager, 'manager', 'active'],
            [$staff, 'staff', 'active'], [$suspended, 'manager', 'suspended']] as [$user, $role, $status]) {
            $shop->users()->attach($user, ['role_in_shop' => $role, 'status' => $status]);
        }
        $other->users()->attach($outsider, ['role_in_shop' => 'owner', 'status' => 'active']);

        app(InventoryService::class)->reserve((int) $stock->product_variant_id, 6);

        foreach ([$admin, $owner, $manager] as $recipient) {
            $this->assertSame(1, $this->alerts($recipient));
        }
        foreach ([$staff, $suspended, $outsider] as $excluded) {
            $this->assertSame(0, $this->alerts($excluded));
        }
        $data = $owner->notifications()->sole()->data;
        $this->assertSame($shop->id, $data['shop_id']);
        $this->assertSame($stock->variant->product_id, $data['product_id']);
        $this->assertSame($stock->variant->sku, $data['sku']);
        $this->assertSame(4, $data['available_quantity']);
        $this->assertSame(5, $data['threshold']);
        $this->assertArrayNotHasKey('cost_price', $data);
        $this->assertSame('/admin/inventory', $admin->notifications()->sole()->data['url']);
    }

    #[TestWith(['product_deleted'])]
    #[TestWith(['variant_deleted'])]
    #[TestWith(['product_hidden'])]
    #[TestWith(['variant_hidden'])]
    #[TestWith(['shop_suspended'])]
    #[TestWith(['shop_deleted'])]
    public function test_removed_or_inactive_merchandise_does_not_start_alerts(string $state): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create();
        $stock = $this->stock($shop);
        match ($state) {
            'product_deleted' => $stock->variant->product->delete(),
            'variant_deleted' => $stock->variant->delete(),
            'product_hidden' => $stock->variant->product->update(['is_active' => false]),
            'variant_hidden' => $stock->variant->update(['is_active' => false]),
            'shop_suspended' => $shop->update(['status' => Shop::STATUS_SUSPENDED]),
            'shop_deleted' => $shop->delete(),
        };

        app(InventoryService::class)->adjust((int) $stock->product_variant_id, 4);

        $this->assertSame(0, $this->alerts($admin));
        $this->assertNull($stock->fresh()->low_stock_notified_at);
    }

    public function test_database_jobs_are_dispatched_after_commit_once_with_immutable_context(): void
    {
        $admin = User::factory()->admin()->create();
        $stock = $this->stock();
        config(['queue.default' => 'database']);
        $service = app(InventoryService::class);
        DB::transaction(function () use ($service, $stock): void {
            $service->reserve((int) $stock->product_variant_id, 6);
            $service->checkLowStock($stock);
            $this->assertDatabaseCount('jobs', 0);
        });
        $this->assertDatabaseCount('jobs', 1);
        $stock->variant->update(['sku' => 'CHANGED-SKU']);
        $stock->update(['low_stock_threshold' => 1]);

        Queue::connection()->pop()->fire();

        $this->assertSame(1, $this->alerts($admin));
        $data = $admin->notifications()->sole()->data;
        $this->assertSame(5, $data['threshold']);
        $this->assertNotSame('CHANGED-SKU', $data['sku']);
        $this->assertSame(4, $data['available_quantity']);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_rollback_removes_marker_ledger_and_queued_delivery(): void
    {
        User::factory()->admin()->create();
        $stock = $this->stock();
        config(['queue.default' => 'database']);
        try {
            DB::transaction(function () use ($stock): void {
                app(InventoryService::class)->reserve((int) $stock->product_variant_id, 6);
                throw new RuntimeException('Rollback checkout');
            });
            $this->fail('The checkout should roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Rollback checkout', $exception->getMessage());
        }

        $this->assertNull($stock->fresh()->low_stock_notified_at);
        $this->assertSame(10, $stock->fresh()->available_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_revoked_membership_blocks_queued_delivery(): void
    {
        $shop = Shop::factory()->create();
        $stock = $this->stock($shop);
        $manager = User::factory()->create();
        $shop->users()->attach($manager, ['role_in_shop' => 'manager', 'status' => 'active']);
        config(['queue.default' => 'database']);
        app(InventoryService::class)->reserve((int) $stock->product_variant_id, 6);
        $this->assertDatabaseCount('jobs', 1);
        $shop->users()->updateExistingPivot($manager->id, ['status' => 'suspended']);

        Queue::connection()->pop()->fire();

        $this->assertSame(0, $this->alerts($manager));
        $this->assertNotNull($stock->fresh()->low_stock_notified_at);
    }

    public function test_disabling_email_while_queued_preserves_the_in_app_alert(): void
    {
        $admin = User::factory()->admin()->create();
        $stock = $this->stock();
        Setting::set('emailLowStockAlerts', '1');
        config(['queue.default' => 'database']);
        app(InventoryService::class)->reserve((int) $stock->product_variant_id, 6);
        $this->assertDatabaseCount('jobs', 2);
        Setting::set('emailLowStockAlerts', '0');
        $this->mock(MailChannel::class)->shouldNotReceive('send');

        while ($job = Queue::connection()->pop()) {
            $job->fire();
        }

        $this->assertSame(1, $this->alerts($admin));
        $this->assertNotNull($stock->fresh()->low_stock_notified_at);
    }

    public function test_email_preference_keeps_in_app_alerts_and_mail_has_correct_action(): void
    {
        $shop = Shop::factory()->create();
        $stock = $this->stock($shop);
        $owner = User::factory()->create();
        $shop->users()->attach($owner, ['role_in_shop' => 'owner', 'status' => 'active']);
        $notification = new LowStockNotification($stock, '<script>bad()</script>', 'Black / XL', 4);
        $this->assertSame(['database'], $notification->via($owner));
        Setting::set('emailLowStockAlerts', '1');
        $this->assertSame(['database', 'mail'], $notification->via($owner));
        $mail = $notification->toMail($owner);
        $this->assertStringEndsWith('/seller/shops/'.$shop->id.'/inventory', $mail->actionUrl);
        $this->assertStringNotContainsString('<script>bad()</script>', (string) $mail->render());
        config(['queue.default' => 'database']);

        app(InventoryService::class)->reserve((int) $stock->product_variant_id, 6);
        app(InventoryService::class)->checkLowStock($stock);

        $this->assertDatabaseCount('jobs', 2); // One event, one job per channel.
    }

    public function test_seller_inventory_dashboard_and_inbox_follow_the_complete_lifecycle(): void
    {
        $shop = Shop::factory()->create();
        $stock = $this->stock($shop);
        $owner = User::factory()->create();
        $shop->users()->attach($owner, ['role_in_shop' => 'owner', 'status' => 'active']);
        $this->actingAs($owner, 'sanctum');
        $base = '/api/seller/shops/'.$shop->id;
        $this->postJson($base.'/inventory/'.$stock->id.'/adjust', ['quantity' => 4])
            ->assertOk()->assertJsonPath('data.is_low_stock', true)->assertJsonPath('data.available_quantity', 4);
        $this->getJson($base.'/dashboard')->assertOk()->assertJsonPath('data.metrics.low_stock', 1);
        $this->getJson('/api/account/notifications')->assertOk()->assertJsonPath('data.0.type', 'inventory')
            ->assertJsonPath('data.0.url', '/seller/shops/'.$shop->id.'/inventory')->assertJsonPath('data.0.read_at', null);
        $notification = $owner->notifications()->sole();
        $this->postJson('/api/account/notifications/'.$notification->id.'/read')->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);

        $this->postJson($base.'/inventory/'.$stock->id.'/adjust', ['quantity' => 10])
            ->assertOk()->assertJsonPath('data.is_low_stock', false)->assertJsonPath('data.low_stock_notified_at', null);
        $this->getJson($base.'/dashboard')->assertOk()->assertJsonPath('data.metrics.low_stock', 0);
        $this->postJson($base.'/inventory/'.$stock->id.'/adjust', ['quantity' => 4])->assertOk();
        $this->assertSame(2, $this->alerts($owner));
        $this->getJson($base.'/dashboard')->assertOk()->assertJsonPath('data.metrics.low_stock', 1);
    }

    public function test_seller_product_creation_and_stock_edits_share_the_episode_lifecycle(): void
    {
        Setting::set('emailLowStockAlerts', '0');
        $shop = Shop::factory()->create();
        $owner = User::factory()->create();
        $shop->users()->attach($owner, ['role_in_shop' => 'owner', 'status' => 'active']);
        $base = '/api/seller/shops/'.$shop->id.'/products';
        $id = $this->actingAs($owner, 'sanctum')->postJson($base, [
            'name' => 'Simple inventory item', 'price' => 10, 'initial_stock' => 4, 'is_active' => true,
        ])->assertCreated()->json('data.id');
        $product = Product::findOrFail($id);
        $variant = $product->variants()->sole();
        $this->assertSame(1, $this->alerts($owner));
        $this->assertSame(0, $variant->inventory->transactions()->count());

        foreach ([10, 4] as $quantity) {
            $this->putJson($base.'/'.$id, ['variants' => [[
                'id' => $variant->id, 'name' => 'Default', 'sku' => $variant->sku, 'quantity' => $quantity,
            ]]])->assertOk();
        }
        $this->assertSame(2, $this->alerts($owner));
        $this->assertSame([6, -6], $variant->inventory->transactions()->orderBy('id')->pluck('quantity')->all());
    }

    public function test_zero_threshold_only_alerts_when_available_stock_reaches_zero(): void
    {
        $admin = User::factory()->admin()->create();
        $stock = $this->stock(quantity: 2);
        $stock->update(['low_stock_threshold' => 0]);
        $service = app(InventoryService::class);
        $service->reserve((int) $stock->product_variant_id, 1);
        $this->assertSame(0, $this->alerts($admin));
        $service->reserve((int) $stock->product_variant_id, 1);
        $this->assertSame(1, $this->alerts($admin));
        $service->release((int) $stock->product_variant_id, 1);
        $this->assertNull($stock->fresh()->low_stock_notified_at);
    }
}
