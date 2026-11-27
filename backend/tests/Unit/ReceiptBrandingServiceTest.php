<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Services\ReceiptBrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptBrandingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_single_shop_receipt_uses_that_shops_name_and_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('images/shops/seller-logo.png', 'shop-logo');

        $shop = Shop::factory()->create([
            'name' => 'SV Store',
            'description' => 'Shop with a dynamic receipt logo',
            'logo' => Storage::disk('public')->url('images/shops/seller-logo.png'),
        ]);
        $order = Order::factory()->create();
        OrderItem::create([
            'order_id' => $order->id,
            'shop_id' => $shop->id,
            'product_name' => 'Receipt product',
            'sku' => 'RECEIPT-001',
            'image_path' => $shop->logo,
            'unit_price' => 10,
            'quantity' => 1,
            'line_total' => 10,
        ]);

        $branding = app(ReceiptBrandingService::class)->forOrder($order->load('items.shop'));

        $this->assertSame('SV Store', $branding['name']);
        $this->assertSame('Shop with a dynamic receipt logo', $branding['tagline']);
        $this->assertStringStartsWith('data:', (string) $branding['logo']);
        $this->assertStringStartsWith('data:', (string) $order->items->first()->receipt_image);
    }

    public function test_a_multi_shop_parent_receipt_keeps_marketplace_branding(): void
    {
        $order = Order::factory()->create();
        $first = Shop::factory()->create();
        $second = Shop::factory()->create();

        OrderItem::create([
            'order_id' => $order->id,
            'shop_id' => $first->id,
            'product_name' => 'First shop product',
            'unit_price' => 10,
            'quantity' => 1,
            'line_total' => 10,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'shop_id' => $second->id,
            'product_name' => 'Second shop product',
            'unit_price' => 10,
            'quantity' => 1,
            'line_total' => 10,
        ]);

        $branding = app(ReceiptBrandingService::class)->forOrder($order->load('items.shop'));

        $this->assertSame(config('app.name', 'E-KHMER'), $branding['name']);
        $this->assertNull($branding['logo']);
    }

    public function test_receipt_view_distinguishes_unpaid_cod_pro_forma_from_paid_bakong_receipt(): void
    {
        $first = Shop::factory()->create(['name' => 'Phnom Penh Tech']);
        $second = Shop::factory()->create(['name' => 'Angkor Apparel']);

        $codOrder = Order::factory()->create([
            'order_number' => 'ORD-COD-100',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'subtotal' => 50,
            'total' => 50,
            'currency' => 'USD',
        ]);
        $codOrder->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount' => 50]);
        OrderItem::create([
            'order_id' => $codOrder->id,
            'shop_id' => $first->id,
            'product_name' => 'Extra Long Product Name With Extended Specifications For Wrap Testing',
            'variant_label' => 'Size: M · Finish: Matte',
            'sku' => 'COD-1',
            'unit_price' => 25,
            'quantity' => 1,
            'line_total' => 25,
        ]);
        OrderItem::create([
            'order_id' => $codOrder->id,
            'shop_id' => $second->id,
            'product_name' => 'Second Shop Item',
            'sku' => 'COD-2',
            'unit_price' => 25,
            'quantity' => 1,
            'line_total' => 25,
        ]);

        $codOrder->load(['items.shop', 'payment']);
        $codHtml = view('reports.receipt', [
            'order' => $codOrder,
            'branding' => app(ReceiptBrandingService::class)->forOrder($codOrder),
        ])->render();

        $this->assertStringContainsString('E-KHMER', $codHtml);
        $this->assertStringContainsString('ORDER CONFIRMATION / PRO FORMA', $codHtml);
        $this->assertStringContainsString('Pending Cash Collection', $codHtml);
        $this->assertStringContainsString('Cash on Delivery', $codHtml);
        $this->assertStringNotContainsString('>Cod<', $codHtml);
        $this->assertStringNotContainsString('OFFICIAL PAYMENT RECEIPT', $codHtml);
        $this->assertStringContainsString('Seller: Phnom Penh Tech', $codHtml);
        $this->assertStringContainsString('Seller: Angkor Apparel', $codHtml);

        $bakongOrder = Order::factory()->create([
            'order_number' => 'ORD-KHQR-200',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'subtotal' => 100,
            'total' => 100,
            'currency' => 'USD',
        ]);
        $bakongOrder->payment()->create([
            'method' => 'bakong',
            'status' => 'paid',
            'amount' => 100,
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $bakongOrder->id,
            'shop_id' => $first->id,
            'product_name' => 'Verified Bakong Item',
            'sku' => 'KHQR-1',
            'unit_price' => 100,
            'quantity' => 1,
            'line_total' => 100,
        ]);

        $bakongOrder->load(['items.shop', 'payment']);
        $bakongHtml = view('reports.receipt', [
            'order' => $bakongOrder,
            'branding' => app(ReceiptBrandingService::class)->forOrder($bakongOrder),
        ])->render();

        $this->assertStringContainsString('OFFICIAL PAYMENT RECEIPT', $bakongHtml);
        $this->assertStringContainsString('Paid (Verified)', $bakongHtml);
        $this->assertStringContainsString('Bakong / KHQR', $bakongHtml);
        $this->assertStringContainsString('Total Paid', $bakongHtml);
    }
}
