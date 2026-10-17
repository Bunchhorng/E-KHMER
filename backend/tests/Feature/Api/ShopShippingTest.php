<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopShippingTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_methods_and_fees_are_validated_for_each_shop(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $shops = [];
        foreach ([2, 5] as $fee) {
            $shop = Shop::factory()->create();
            $method = ShippingMethod::factory()->create(['shop_id' => $shop->id, 'price' => $fee]);
            $product = Product::factory()->withVariant(10, 10)->create(['shop_id' => $shop->id]);
            $this->postJson('/api/cart', ['product_variant_id' => $product->variants()->first()->id, 'quantity' => 1])->assertCreated();
            $shops[$shop->id] = $method->id;
        }
        $payload = ['shipping_methods' => $shops, 'payment_method' => 'cod',
            'address' => ['full_name' => 'Buyer', 'address_line1' => 'Street 1', 'city' => 'PP', 'state' => 'PP', 'postal_code' => '12000']];
        $ids = array_keys($shops);
        $this->postJson('/api/checkout', array_replace($payload, ['shipping_methods' => [$ids[0] => $shops[$ids[1]], $ids[1] => $shops[$ids[1]]]]))->assertUnprocessable();
        $response = $this->postJson('/api/checkout', $payload)->assertCreated()->assertJsonPath('data.shipping_amount', 7);
        $order = Order::where('order_number', $response->json('data.order_number'))->sole();
        $this->assertEquals(7, $order->shopOrders()->sum('shipping_amount'));
        foreach ($order->shopOrders as $allocation) {
            $this->assertEquals($shops[$allocation->shop_id], $allocation->shipment->shipping_method_id);
        }
    }
}
