<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\ShopOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MarketplaceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_has_platform_access(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]), 'sanctum')
            ->getJson('/api/admin/dashboard/overview')->assertForbidden();
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/shops')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/admin/shops')->assertOk();
    }

    public function test_mixed_shop_checkout_creates_and_confirms_independent_shipments(): void
    {
        Notification::fake();
        $order = $this->checkout();

        $this->assertSame(2, $order->shopOrders()->count());
        $this->assertSame(2, $order->shipments()->whereNotNull('shop_order_id')->count());
        $this->assertSame(['confirmed'], $order->shopOrders()->pluck('status')->unique()->values()->all());
        $this->assertSame((float) $order->total, (float) $order->shopOrders()->sum('total'));
        foreach ($order->items as $item) {
            $this->assertSame(9, $item->variant->inventory->quantity);
            $this->assertSame(0, $item->variant->inventory->reserved_quantity);
        }
    }

    public function test_owners_fulfil_each_shop_without_advancing_the_other_delivery(): void
    {
        Notification::fake();
        [$order, $first, $second, $ownerA, $ownerB] = $this->allocations();

        $this->move($ownerA, $first, 'processing')->assertOk();
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->move($ownerA, $first, 'shipped', ['carrier' => 'Carrier A', 'tracking_number' => 'TRACK-A'])->assertOk();
        $this->assertSame('confirmed', $second->fresh()->status);
        $this->assertSame('pending', $second->shipment()->first()->status);
        $this->move($ownerB, $second, 'processing')->assertOk();
        $this->assertSame('processing', $order->fresh()->status);
        $this->move($ownerA, $first, 'delivered')->assertOk();
        $this->assertSame('processing', $order->fresh()->status);
        $this->move($ownerB, $second, 'shipped', ['carrier' => 'Carrier B', 'tracking_number' => 'TRACK-B'])->assertOk();
        $this->assertSame('shipped', $order->fresh()->status);
        $this->move($ownerB, $second, 'delivered')->assertOk();

        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('TRACK-A', $first->shipment()->first()->tracking_number);
        $this->assertSame('TRACK-B', $second->shipment()->first()->tracking_number);
        $this->assertSame(['processing', 'shipped', 'delivered'], $first->trackingEvents()->pluck('status')->all());
    }

    public function test_cross_shop_order_reads_and_writes_are_rejected(): void
    {
        [$order, $first, $second, $ownerA] = $this->allocations();
        $prefix = "/api/seller/shops/{$first->shop_id}/orders/{$second->id}";

        $this->actingAs($ownerA, 'sanctum')->getJson($prefix)->assertNotFound();
        $this->actingAs($ownerA, 'sanctum')->putJson($prefix.'/transition', ['status' => 'processing'])->assertNotFound();
        $this->actingAs($ownerA, 'sanctum')->putJson($prefix.'/shipment', ['status' => 'shipped'])->assertNotFound();
        $this->actingAs($ownerA, 'sanctum')->getJson("/api/seller/shops/{$second->shop_id}/orders/{$second->id}")->assertForbidden();
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson("/api/seller/shops/{$first->shop_id}/orders/{$first->id}")->assertForbidden();
        $this->assertSame('confirmed', $second->fresh()->status);
    }

    public function test_shop_order_detail_exposes_only_its_allocation(): void
    {
        [$order, $first, $second, $owner] = $this->allocations();

        $response = $this->actingAs($owner, 'sanctum')->getJson("/api/seller/shops/{$first->shop_id}/orders/{$first->id}");

        $response->assertOk()->assertJsonPath('data.id', $first->id)
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.allowed_transitions.0', 'processing')
            ->assertJsonMissingPath('data.order.total')->assertJsonMissingPath('data.payment.provider_data');
        $this->assertNotSame($second->items()->first()->id, $response->json('data.items.0.id'));
    }

    public function test_invalid_status_jumps_and_dispatch_without_tracking_return_422(): void
    {
        [$order, $first, $second, $owner] = $this->allocations();

        $this->move($owner, $first, 'delivered')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->move($owner, $first, 'processing')->assertOk();
        $this->move($owner, $first, 'shipped')->assertUnprocessable()->assertJsonValidationErrors(['carrier', 'tracking_number']);
        $this->assertSame('processing', $first->fresh()->status);
        $this->assertNull($first->shipment()->first()->shipped_at);
    }

    public function test_customer_tracking_returns_each_shop_without_raw_payment_metadata(): void
    {
        [$order, $first, $second] = $this->allocations();

        $response = $this->actingAs($order->user, 'sanctum')->getJson('/api/orders/'.$order->order_number);

        $response->assertOk()->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonCount(2, 'data.shop_orders')->assertJsonPath('data.shop_orders.0.shipment.status', 'pending')
            ->assertJsonMissingPath('data.payment.provider_data');
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/orders/'.$order->order_number)->assertNotFound();
    }

    public function test_customer_cancellation_synchronizes_allocations_and_restores_stock_once(): void
    {
        Notification::fake();
        $order = $this->checkout();

        $this->actingAs($order->user, 'sanctum')->postJson('/api/orders/'.$order->order_number.'/cancel')
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(['cancelled'], $order->shopOrders()->pluck('status')->unique()->values()->all());
        foreach ($order->items as $item) {
            $inventory = $item->variant->inventory->fresh();
            $this->assertSame(10, $inventory->quantity);
            $this->assertSame(0, $inventory->sold_count);
        }
        $allocation = $order->shopOrders()->first();
        $owner = $this->owner($allocation->shop);
        $this->move($owner, $allocation, 'processing')->assertUnprocessable();
        $this->actingAs($order->user, 'sanctum')->postJson('/api/orders/'.$order->order_number.'/cancel')->assertUnprocessable();
    }

    public function test_three_shop_financial_allocations_are_proportional_and_sum_to_the_parent(): void
    {
        $order = Order::factory()->create(['subtotal' => 150, 'discount_amount' => 90, 'tax_amount' => 6, 'shipping_amount' => 12, 'total' => 78]);
        foreach (range(1, 3) as $index) {
            $product = Product::factory()->withVariant(50)->create(['shop_id' => Shop::factory()->create()->id]);
            $order->items()->create($this->item($product, 50));
        }

        app(ShopOrderService::class)->split($order);

        foreach ($order->shopOrders()->get() as $allocation) {
            $this->assertSame(30.0, (float) $allocation->discount_amount);
            $this->assertSame(4.0, (float) $allocation->shipping_amount);
            $this->assertSame(2.0, (float) $allocation->tax_amount);
            $this->assertSame(26.0, (float) $allocation->total);
        }
    }

    public function test_owner_identity_lists_only_active_managed_shops(): void
    {
        $active = Shop::factory()->create();
        $owner = $this->owner($active);
        $pending = Shop::factory()->create(['status' => 'pending']);
        $owner->shops()->attach($pending->id, ['role_in_shop' => 'owner', 'status' => 'active']);
        $other = Shop::factory()->create();
        $owner->shops()->attach($other->id, ['role_in_shop' => 'staff', 'status' => 'active']);

        $this->actingAs($owner, 'sanctum')->getJson('/api/auth/me')->assertOk()
            ->assertJsonCount(1, 'data.managed_shops')->assertJsonPath('data.managed_shops.0.id', $active->id)
            ->assertJsonPath('data.role', 'customer');
    }

    public function test_owner_can_edit_products_and_adjust_stock_through_nested_shop_routes(): void
    {
        $shop = Shop::factory()->create();
        $owner = $this->owner($shop);
        $product = Product::factory()->withVariant(50, 10)->create(['shop_id' => $shop->id]);
        $inventory = $product->variants()->first()->inventory;
        $otherProduct = Product::factory()->withVariant(50, 10)->create(['shop_id' => Shop::factory()->create()->id]);

        $this->actingAs($owner, 'sanctum')->getJson("/api/seller/shops/{$shop->id}/products/{$product->id}")->assertOk();
        $this->actingAs($owner, 'sanctum')->putJson("/api/seller/shops/{$shop->id}/products/{$product->id}", ['name' => 'Updated by owner', 'shop_id' => $otherProduct->shop_id])
            ->assertOk()->assertJsonPath('data.shop_id', $shop->id);
        $this->actingAs($owner, 'sanctum')->postJson("/api/seller/shops/{$shop->id}/inventory/{$inventory->id}/adjust", ['quantity' => 15])
            ->assertOk()->assertJsonPath('data.quantity', 15);
        $this->actingAs($owner, 'sanctum')->getJson("/api/seller/shops/{$shop->id}/inventory/{$inventory->id}/transactions")
            ->assertOk()->assertJsonPath('meta.total', 1);
        $this->actingAs($owner, 'sanctum')->getJson("/api/seller/shops/{$shop->id}/products/{$otherProduct->id}")->assertNotFound();
        $this->actingAs($owner, 'sanctum')->deleteJson("/api/seller/shops/{$shop->id}/products/{$product->id}")->assertOk();
        $this->assertSoftDeleted($product);
    }

    public function test_shipment_transit_metadata_is_independent_and_cannot_erase_dispatched_tracking(): void
    {
        Notification::fake();
        [$order, $first, $second, $owner] = $this->allocations();
        $this->move($owner, $first, 'processing')->assertOk();
        $this->move($owner, $first, 'shipped', ['carrier' => 'Carrier A', 'tracking_number' => 'TRACK-A'])->assertOk();
        $path = "/api/seller/shops/{$first->shop_id}/orders/{$first->id}/shipment";

        $this->actingAs($owner, 'sanctum')->putJson($path, ['status' => 'in_transit'])->assertOk()->assertJsonPath('data.shipment.status', 'in_transit');
        $this->actingAs($owner, 'sanctum')->putJson($path, ['status' => 'in_transit', 'tracking_number' => ''])->assertUnprocessable();

        $this->assertSame('TRACK-A', $first->shipment()->first()->tracking_number);
        $this->assertSame('pending', $second->shipment()->first()->status);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_platform_status_changes_synchronize_shop_orders_without_reducing_advanced_allocations(): void
    {
        Notification::fake();
        [$order, $first, $second, $owner] = $this->allocations();
        $this->move($owner, $first, 'processing')->assertOk();
        $this->move($owner, $first, 'shipped', ['carrier' => 'Carrier A', 'tracking_number' => 'TRACK-A'])->assertOk();

        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->putJson("/api/admin/orders/{$order->id}/transition", ['status' => 'processing'])->assertOk();

        $this->assertSame('shipped', $first->fresh()->status);
        $this->assertSame('processing', $second->fresh()->status);
    }

    public function test_pending_customer_confirmation_blocks_seller_processing(): void
    {
        [$order, $first, $second, $owner] = $this->allocations();
        $order->update(['status' => 'pending', 'payment_status' => 'unpaid']);
        $first->update(['status' => 'pending']);

        $this->move($owner, $first, 'confirmed')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->move($owner, $first, 'processing')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('pending', $first->fresh()->status);
    }

    public function test_super_admin_can_manage_an_individual_shop_allocation(): void
    {
        Notification::fake();
        [$order, $first, $second] = $this->allocations();
        $otherOrder = Order::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/orders/{$otherOrder->id}/shop-orders/{$first->id}/transition", ['status' => 'processing'])->assertNotFound();
        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/orders/{$order->id}/shop-orders/{$first->id}/transition", ['status' => 'processing'])->assertOk();
        $this->assertSame('confirmed', $second->fresh()->status);
    }

    public function test_upgrade_aligns_existing_shop_statuses_without_inventing_carrier_tracking(): void
    {
        $migration = require database_path('migrations/2026_10_07_000001_add_shop_fulfilment_tracking.php');
        $migration->down();
        $order = Order::factory()->create(['status' => 'shipped']);
        $shop = Shop::factory()->create();
        $allocation = ShopOrder::create(['order_id' => $order->id, 'shop_id' => $shop->id, 'shop_order_number' => 'EXISTING-001', 'status' => 'pending']);
        $legacy = Shipment::create(['order_id' => $order->id, 'status' => 'shipped', 'tracking_number' => 'ORIGINAL-TRACK', 'carrier' => 'Original carrier', 'address_snapshot' => $order->shipping_address, 'shipped_at' => now()]);

        $migration->up();

        $this->assertSame('shipped', $allocation->fresh()->status);
        $this->assertSame('shipped', $allocation->shipment()->first()->status);
        $this->assertNull($allocation->shipment()->first()->tracking_number);
        $this->assertSame('ORIGINAL-TRACK', $legacy->fresh()->tracking_number);
        $this->assertSame(1, $allocation->trackingEvents()->count());
    }

    public function test_customer_and_platform_cannot_cancel_after_one_shop_dispatches(): void
    {
        Notification::fake();
        [$order, $first, $second, $owner] = $this->allocations();
        $this->move($owner, $first, 'processing')->assertOk();
        $this->move($owner, $first, 'shipped', ['carrier' => 'Carrier A', 'tracking_number' => 'DISPATCHED-A'])->assertOk();

        $this->actingAs($order->user, 'sanctum')->getJson('/api/orders/'.$order->order_number)
            ->assertOk()->assertJsonPath('data.can_cancel', false);
        $this->actingAs($order->user, 'sanctum')->postJson('/api/orders/'.$order->order_number.'/cancel')->assertUnprocessable();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')->putJson("/api/admin/orders/{$order->id}/transition", ['status' => 'cancelled'])->assertUnprocessable();

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame('shipped', $first->fresh()->status);
        $this->assertSame('confirmed', $second->fresh()->status);
    }

    public function test_shop_applications_with_the_same_long_name_get_distinct_valid_codes(): void
    {
        $owner = User::factory()->create();
        $name = str_repeat('Long marketplace shop name ', 4);

        $first = $this->actingAs($owner, 'sanctum')->postJson('/api/seller/application', ['name' => $name])->assertCreated();
        $second = $this->actingAs($owner, 'sanctum')->postJson('/api/seller/application', ['name' => $name])->assertCreated();

        $this->assertNotSame($first->json('data.code'), $second->json('data.code'));
        $this->assertLessThanOrEqual(30, strlen($second->json('data.code')));
    }

    public function test_platform_confirmation_deducts_cod_reservations_and_rejects_unpaid_online_orders(): void
    {
        Notification::fake();
        $cod = $this->checkout(false);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/orders/{$cod->id}/transition", ['status' => 'confirmed'])->assertOk();
        $this->assertSame(['confirmed'], $cod->shopOrders()->pluck('status')->unique()->values()->all());
        foreach ($cod->items as $item) {
            $inventory = $item->variant->inventory->fresh();
            $this->assertSame(9, $inventory->quantity);
            $this->assertSame(0, $inventory->reserved_quantity);
        }
        $online = $this->checkout(false);
        $online->payment()->update(['method' => 'card']);
        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/orders/{$online->id}/transition", ['status' => 'confirmed'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('pending', $online->fresh()->status);
    }

    public function test_returned_parcels_block_whole_order_delivery(): void
    {
        Notification::fake();
        [$order, $first, $second, $ownerA, $ownerB] = $this->allocations();
        $this->move($ownerA, $first, 'processing')->assertOk();
        $this->move($ownerA, $first, 'shipped', ['carrier' => 'A', 'tracking_number' => 'RETURN-A'])->assertOk();
        $this->move($ownerB, $second, 'processing')->assertOk();
        $this->move($ownerB, $second, 'shipped', ['carrier' => 'B', 'tracking_number' => 'TRACK-B'])->assertOk();
        $this->actingAs($ownerA, 'sanctum')->putJson("/api/seller/shops/{$first->shop_id}/orders/{$first->id}/shipment", ['status' => 'returned'])->assertOk();

        $this->actingAs(User::factory()->admin()->create(), 'sanctum')->putJson("/api/admin/orders/{$order->id}/transition", ['status' => 'delivered'])->assertUnprocessable();

        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame('shipped', $first->fresh()->status);
        $this->assertSame('returned', $first->shipment()->first()->status);
    }

    public function test_imported_pending_shipments_do_not_claim_dispatch_dates(): void
    {
        $this->freezeTime();
        [$order, $first] = $this->allocations();
        $oldDate = now()->subDay();
        $first->shipment()->update(['shipped_at' => $oldDate, 'delivered_at' => $oldDate]);
        $first->trackingEvents()->create(['order_id' => $order->id, 'status' => 'confirmed', 'description' => 'Existing shop order status imported; original platform tracking is retained.']);
        $legacy = Shipment::create(['order_id' => $order->id, 'status' => 'pending', 'address_snapshot' => $order->shipping_address, 'shipped_at' => $oldDate]);
        $migration = require database_path('migrations/2026_10_07_000002_clear_imported_pending_shipment_dates.php');

        $migration->up();

        $this->assertNull($first->shipment()->first()->shipped_at);
        $this->assertNull($first->shipment()->first()->delivered_at);
        $this->assertSame($oldDate->toDateTimeString(), $legacy->fresh()->shipped_at->toDateTimeString());
    }

    private function move(User $owner, ShopOrder $allocation, string $status, array $metadata = [])
    {
        return $this->actingAs($owner, 'sanctum')->putJson("/api/seller/shops/{$allocation->shop_id}/orders/{$allocation->id}/transition", ['status' => $status] + $metadata);
    }

    private function owner(Shop $shop): User
    {
        $user = User::factory()->create();
        $user->shops()->attach($shop->id, ['role_in_shop' => 'owner', 'status' => 'active']);

        return $user;
    }

    private function item(Product $product, float $price): array
    {
        return ['product_id' => $product->id, 'product_variant_id' => $product->variants()->first()->id, 'shop_id' => $product->shop_id, 'product_name' => $product->name, 'sku' => $product->sku, 'unit_price' => $price, 'quantity' => 1, 'line_total' => $price];
    }

    private function allocations(): array
    {
        $order = Order::factory()->create(['status' => 'confirmed', 'payment_status' => 'paid']);
        $allocations = [];
        $owners = [];
        foreach (range(1, 2) as $index) {
            $shop = Shop::factory()->create();
            $product = Product::factory()->withVariant(50)->create(['shop_id' => $shop->id]);
            $allocation = ShopOrder::create(['order_id' => $order->id, 'shop_id' => $shop->id, 'shop_order_number' => $order->order_number.'-'.$index, 'status' => 'confirmed', 'subtotal' => 50, 'total' => 55]);
            $allocation->items()->create($this->item($product, 50) + ['order_id' => $order->id]);
            $allocation->shipment()->create(['order_id' => $order->id, 'status' => 'pending', 'address_snapshot' => $order->shipping_address]);
            $allocations[] = $allocation;
            $owners[] = $this->owner($shop);
        }

        return [$order, ...$allocations, ...$owners];
    }

    private function checkout(bool $confirm = true): Order
    {
        $customer = User::factory()->create();
        $this->actingAs($customer, 'sanctum');
        foreach (range(1, 2) as $index) {
            $product = Product::factory()->withVariant(50, 10)->create(['shop_id' => Shop::factory()->create()->id]);
            $this->postJson('/api/cart', ['product_variant_id' => $product->variants()->first()->id, 'quantity' => 1])->assertCreated();
        }
        $response = $this->postJson('/api/checkout', ['shipping_method_id' => ShippingMethod::factory()->create(['price' => 8])->id, 'payment_method' => 'cod', 'address' => ['full_name' => 'Marketplace buyer', 'phone' => '+85512345678', 'address_line1' => 'Street 1', 'city' => 'Phnom Penh', 'state' => 'Phnom Penh', 'postal_code' => '12000', 'country' => 'KH']])->assertCreated();
        $number = $response->json('data.order_number');
        if ($confirm) {
            $this->postJson('/api/checkout/'.$number.'/confirm')->assertOk();
        }

        return Order::with('items.variant.inventory')->where('order_number', $number)->firstOrFail();
    }
}
