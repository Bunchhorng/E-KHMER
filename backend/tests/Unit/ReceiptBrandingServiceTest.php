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
}
