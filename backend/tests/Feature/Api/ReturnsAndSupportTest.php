<?php

namespace Tests\Feature\Api;

use App\Models\NewsletterSubscription;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\NewsletterWelcomeNotification;
use App\Notifications\SupportReplyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReturnsAndSupportTest extends TestCase
{
    use RefreshDatabase;

    private function delivered(): array
    {
        $buyer = User::factory()->create();
        $shop = Shop::factory()->create();
        $seller = User::factory()->create();
        $seller->shops()->attach($shop->id, ['role_in_shop' => 'owner', 'status' => 'active']);
        $product = Product::factory()->withVariant(10, 8)->create(['shop_id' => $shop->id]);
        $order = Order::factory()->delivered()->create(['user_id' => $buyer->id, 'subtotal' => 20, 'tax_amount' => 0, 'total' => 20]);
        Payment::create(['order_id' => $order->id, 'method' => 'cod', 'status' => 'completed', 'amount' => 20]);
        $item = OrderItem::create(['order_id' => $order->id, 'shop_id' => $shop->id, 'product_id' => $product->id,
            'product_variant_id' => $product->variants()->first()->id, 'product_name' => 'Return item', 'unit_price' => 10, 'quantity' => 2, 'line_total' => 20]);
        Shipment::create(['order_id' => $order->id, 'status' => 'delivered', 'delivered_at' => now(), 'address_snapshot' => $order->shipping_address]);

        return [$buyer, $seller, $shop, $order, $item];
    }

    public function test_return_is_scoped_and_private_evidence_is_not_public(): void
    {
        Storage::fake('local');
        [$buyer, $seller, $shop, $order, $item] = $this->delivered();
        $response = $this->actingAs($buyer, 'sanctum')->post('/api/returns', ['order_item_id' => $item->id, 'quantity' => 1,
            'reason' => 'The item arrived damaged.', 'photos' => [UploadedFile::fake()->image('evidence.jpg')]], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.photo_count', 1)->assertJsonMissingPath('data.photos');
        $id = $response->json('data.id');
        $this->getJson("/api/returns/{$id}/photos/0")->assertOk();
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson("/api/returns/{$id}/photos/0")->assertNotFound();
        $this->getJson('/api/returns')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($seller, 'sanctum')->getJson("/api/seller/shops/{$shop->id}/returns/{$id}/photos/0")->assertOk();
    }

    public function test_return_state_machine_stock_decision_and_cash_receipt_are_audited_once(): void
    {
        [$buyer, $seller, $shop, $order, $item] = $this->delivered();
        $response = $this->actingAs($buyer, 'sanctum')->postJson('/api/returns', ['order_item_id' => $item->id, 'quantity' => 1, 'reason' => 'Wrong size was delivered.'])->assertCreated();
        $url = "/api/seller/shops/{$shop->id}/returns/".$response->json('data.id');
        $this->actingAs($seller, 'sanctum')->putJson($url, ['status' => 'refunded', 'reference' => 'CASH-1'])->assertUnprocessable();
        $this->putJson($url, ['status' => 'approved'])->assertOk();
        $this->putJson($url, ['status' => 'received', 'restock' => true])->assertOk();
        $this->assertEquals(9, $item->variant->inventory->fresh()->quantity);
        $this->putJson($url, ['status' => 'received', 'restock' => true])->assertUnprocessable();
        $this->putJson($url, ['status' => 'refunded', 'reference' => 'CASH-1'])->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->putJson($url, ['status' => 'refunded', 'reference' => 'CASH-1'])->assertUnprocessable();
        $this->assertDatabaseCount('payment_refunds', 1);
        $this->assertEquals(10, PaymentRefund::sole()->amount);
        $this->assertSame('partially_refunded', $order->fresh()->payment_status);
    }

    public function test_returns_reject_another_customers_order_excess_quantity_and_expired_window(): void
    {
        [$buyer, $seller, $shop, $order, $item] = $this->delivered();
        $payload = ['order_item_id' => $item->id, 'quantity' => 1, 'reason' => 'I received the wrong item.'];
        $this->actingAs($seller, 'sanctum')->postJson('/api/returns', $payload)->assertNotFound();
        $this->actingAs($buyer, 'sanctum')->postJson('/api/returns', array_replace($payload, ['quantity' => 3]))->assertUnprocessable();
        $this->postJson('/api/returns', $payload)->assertCreated();
        $this->postJson('/api/returns', array_replace($payload, ['quantity' => 2]))->assertUnprocessable();
        $this->travel(15)->days();
        $this->postJson('/api/returns', $payload)->assertUnprocessable();
        $return = OrderReturn::sole();
        $otherShop = Shop::factory()->create();
        $otherSeller = User::factory()->create();
        $otherSeller->shops()->attach($otherShop->id, ['role_in_shop' => 'owner', 'status' => 'active']);
        $this->actingAs($otherSeller, 'sanctum')->putJson("/api/seller/shops/{$otherShop->id}/returns/{$return->id}", ['status' => 'approved'])->assertNotFound();
    }

    public function test_receiving_damaged_goods_does_not_require_restocking_them(): void
    {
        [$buyer, $seller, $shop, $order, $item] = $this->delivered();
        $id = $this->actingAs($buyer, 'sanctum')->postJson('/api/returns', ['order_item_id' => $item->id, 'quantity' => 1, 'reason' => 'The product is broken.'])->assertCreated()->json('data.id');
        $url = "/api/seller/shops/{$shop->id}/returns/{$id}";
        $this->actingAs($seller, 'sanctum')->putJson($url, ['status' => 'approved'])->assertOk();
        $this->putJson($url, ['status' => 'received', 'restock' => false])->assertOk();
        $this->assertEquals(8, $item->variant->inventory->fresh()->quantity);
    }

    public function test_partial_returns_allocate_fractional_cents_without_exceeding_the_paid_goods(): void
    {
        [$buyer, $seller, $shop, $order, $item] = $this->delivered();
        $order->update(['subtotal' => 3, 'discount_amount' => 2, 'tax_amount' => 0, 'total' => 1]);
        $order->payment->update(['amount' => 1]);
        $item->update(['unit_price' => 1, 'quantity' => 3, 'line_total' => 3]);
        foreach ([0.33, 0.34, 0.33] as $index => $expected) {
            $id = $this->actingAs($buyer, 'sanctum')->postJson('/api/returns', ['order_item_id' => $item->id, 'quantity' => 1, 'reason' => 'The product is damaged.'])
                ->assertCreated()->json('data.id');
            $this->assertEquals($expected, OrderReturn::findOrFail($id)->refund_amount);
            $url = "/api/seller/shops/{$shop->id}/returns/{$id}";
            $this->actingAs($seller, 'sanctum')->putJson($url, ['status' => 'approved'])->assertOk();
            $this->putJson($url, ['status' => 'received'])->assertOk();
            $this->putJson($url, ['status' => 'refunded', 'reference' => 'PARTIAL-'.$index])->assertOk();
        }
        $this->assertEquals(1, PaymentRefund::sum('amount'));
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public function test_support_requests_persist_and_only_admins_can_reply(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $ticket = $this->actingAs($user, 'sanctum')->postJson('/api/support', ['email' => $user->email, 'subject' => 'Order question', 'message' => 'Where can I find my order?'])
            ->assertCreated()->json('data.id');
        $this->getJson('/api/support')->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/admin/support/{$ticket}", ['reply' => 'Hello there', 'status' => 'answered'])->assertForbidden();
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/support')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')->putJson("/api/admin/support/{$ticket}", ['reply' => 'Please open My Orders in your account.', 'status' => 'answered'])->assertOk();
        Notification::assertSentOnDemand(SupportReplyNotification::class);
    }

    public function test_newsletter_requires_consent_persists_and_can_be_unsubscribed(): void
    {
        Notification::fake();
        $this->postJson('/api/newsletter', ['email' => 'buyer@example.com', 'consent' => false])->assertUnprocessable();
        $this->postJson('/api/newsletter', ['email' => 'buyer@example.com', 'consent' => true])->assertOk();
        $this->assertDatabaseCount('newsletter_subscriptions', 1);
        $token = '';
        Notification::assertSentOnDemand(NewsletterWelcomeNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $this->get('/api/newsletter/unsubscribe/'.$token)->assertOk();
        $this->assertNotNull(NewsletterSubscription::sole()->unsubscribed_at);
    }
}
