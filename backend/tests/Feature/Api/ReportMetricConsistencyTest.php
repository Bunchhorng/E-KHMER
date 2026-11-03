<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportMetricConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_unpaid_order_is_demand_but_not_a_settled_sale(): void
    {
        $this->orderWithItem();

        $this->summary()->assertOk()
            ->assertJsonPath('data.gross_order_demand', 100)
            ->assertJsonPath('data.settled_revenue', 0)
            ->assertJsonPath('data.net_revenue', 0)
            ->assertJsonPath('data.items_revenue', 0)
            ->assertJsonPath('data.units_ordered', 1)
            ->assertJsonPath('data.units_sold', 0)
            ->assertJsonPath('data.paid_orders', 0)
            ->assertJsonPath('data.avg_order_value', 0);
    }

    public function test_verified_online_payment_counts_once_for_revenue_items_units_and_aov(): void
    {
        $order = $this->orderWithItem();
        $order->update(['payment_status' => Order::PAYMENT_PAID]);
        Payment::create(['order_id' => $order->id, 'method' => 'card', 'status' => Payment::STATUS_COMPLETED, 'amount' => 100, 'paid_at' => now()]);

        $this->summary()->assertOk()
            ->assertJsonPath('data.settled_revenue', 100)
            ->assertJsonPath('data.net_revenue', 100)
            ->assertJsonPath('data.items_revenue', 100)
            ->assertJsonPath('data.units_sold', 1)
            ->assertJsonPath('data.paid_orders', 1)
            ->assertJsonPath('data.avg_order_value', 100);
    }

    public function test_completed_refunds_reduce_net_and_item_revenue_without_erasing_settlement_history(): void
    {
        $order = $this->orderWithItem();
        $order->update(['payment_status' => 'partially_refunded']);
        Payment::create(['order_id' => $order->id, 'method' => 'card', 'status' => Payment::STATUS_COMPLETED, 'amount' => 100, 'paid_at' => now()]);
        PaymentRefund::create(['order_id' => $order->id, 'amount' => 30, 'currency' => 'USD', 'reference' => 'REF-30', 'method' => 'bakong_verified']);

        $this->summary()->assertOk()
            ->assertJsonPath('data.settled_revenue', 100)
            ->assertJsonPath('data.refunded_amount', 30)
            ->assertJsonPath('data.net_revenue', 70)
            ->assertJsonPath('data.items_revenue', 70)
            ->assertJsonPath('data.units_sold', 1)
            ->assertJsonPath('data.avg_order_value', 70);
    }

    public function test_collected_cod_shop_order_is_settled(): void
    {
        [$order, $allocation] = $this->codOrder();
        PaymentTransaction::create([
            'payment_id' => $order->payment->id,
            'shop_order_id' => $allocation->id,
            'type' => 'collection',
            'status' => 'success',
            'amount' => 100,
            'reference' => 'COD-100',
        ]);

        $this->summary()->assertOk()
            ->assertJsonPath('data.settled_revenue', 100)
            ->assertJsonPath('data.items_revenue', 100)
            ->assertJsonPath('data.units_sold', 1)
            ->assertJsonPath('data.paid_orders', 1);
    }

    private function orderWithItem(): Order
    {
        $product = Product::factory()->withVariant()->create();
        $order = Order::factory()->create(['subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 0, 'shipping_amount' => 0, 'total' => 100, 'placed_at' => now()]);
        $order->items()->create([
            'product_id' => $product->id, 'product_variant_id' => $product->variants()->sole()->id,
            'product_name' => $product->name, 'sku' => $product->sku, 'unit_price' => 100, 'quantity' => 1, 'line_total' => 100,
        ]);

        return $order;
    }

    /** @return array{0: Order, 1: ShopOrder} */
    private function codOrder(): array
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->withVariant()->create(['shop_id' => $shop->id]);
        $order = Order::factory()->create(['subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 0, 'shipping_amount' => 0, 'total' => 100, 'placed_at' => now()]);
        $allocation = ShopOrder::create([
            'order_id' => $order->id, 'shop_id' => $shop->id, 'shop_order_number' => 'SO-'.$order->id,
            'status' => Order::STATUS_DELIVERED, 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 0, 'shipping_amount' => 0, 'total' => 100,
        ]);
        $order->items()->create([
            'shop_order_id' => $allocation->id, 'shop_id' => $shop->id, 'product_id' => $product->id,
            'product_variant_id' => $product->variants()->sole()->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'unit_price' => 100, 'quantity' => 1, 'line_total' => 100,
        ]);
        Payment::create(['order_id' => $order->id, 'method' => 'cod', 'status' => Payment::STATUS_PENDING, 'amount' => 100]);

        return [$order->fresh('payment'), $allocation];
    }

    private function summary()
    {
        return $this->actingAs(User::factory()->admin()->create(), 'sanctum')->getJson('/api/admin/reports/summary');
    }
}
