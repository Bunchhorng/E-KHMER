<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Setting;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function storagePath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;
        $path = ltrim($path, '/');

        return str_starts_with($path, 'storage/') ? substr($path, strlen('storage/')) : $path;
    }

    public function test_customer_can_list_their_reviews(): void
    {
        $user = User::factory()->create();
        $product = \App\Models\Product::factory()->withVariant()->create(['price' => 29.99]);

        $order = Order::factory()->delivered()->create(['user_id' => $user->id]);
        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $product->variants()->first()->id,
            'product_name' => $product->name,
            'variant_label' => 'Default',
            'sku' => $product->variants()->first()->sku,
            'unit_price' => 29.99,
            'quantity' => 1,
            'line_total' => 29.99,
        ]);

        Review::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
            'rating' => 5,
            'title' => 'Loved it',
            'body' => 'Great product.',
            'status' => Review::STATUS_APPROVED,
            'verified' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/account/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.product.name', $product->name);
    }

    public function test_admin_can_read_and_write_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = Product::factory()->withVariant(10, 5)->create()->variants()->firstOrFail()->inventory;

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.storeName', 'E-KHMER');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/settings', [
                'storeName' => 'My Shop',
                'lowStockThreshold' => 3,
                'emailLowStockAlerts' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.storeName', 'My Shop')
            ->assertJsonPath('data.lowStockThreshold', 3)
            ->assertJsonPath('data.emailLowStockAlerts', false);

        $this->assertSame('My Shop', Setting::get('storeName'));
        $this->assertSame(3, $inventory->fresh()->low_stock_threshold);

        $newInventory = Inventory::create([
            'product_variant_id' => ProductVariant::factory()->create()->id,
            'quantity' => 0,
            'reserved_quantity' => 0,
        ]);
        $this->assertSame(3, $newInventory->low_stock_threshold);
    }

    public function test_admin_can_delete_a_review(): void
    {
        $user = User::factory()->create();
        $product = \App\Models\Product::factory()->withVariant()->create(['price' => 39.99]);
        $order = Order::factory()->delivered()->create(['user_id' => $user->id]);

        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
            'rating' => 4,
            'title' => 'Nice',
            'body' => 'Good.',
            'status' => Review::STATUS_APPROVED,
            'verified' => true,
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/reviews/{$review->id}")
            ->assertOk();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_tracking_events_recorded_on_transition(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        TrackingEvent::create([
            'order_id' => $order->id,
            'status' => Order::STATUS_PENDING,
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/orders/{$order->id}/transition", ['status' => Order::STATUS_CONFIRMED])
            ->assertOk();

        $this->assertNotNull(
            TrackingEvent::where('order_id', $order->id)->where('status', Order::STATUS_CONFIRMED)->first()
        );
    }

    public function test_customer_can_upload_avatar(): void
    {
        Storage::fake('public');

        $response = $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/account/avatar', [
                'image' => UploadedFile::fake()->image('avatar.jpg', 10, 10),
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['avatar']]);

        $user = User::first();
        $this->assertSame($response->json('data.avatar'), $user->avatar);
        $this->assertStringContainsString('/storage/images/avatars/', $user->avatar);
        $this->assertTrue(Storage::disk('public')->exists($this->storagePath($user->avatar)));
    }

    public function test_customer_avatar_replacement_deletes_old_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $oldUrl = Storage::disk('public')->url('images/avatars/old-avatar.jpg');
        Storage::disk('public')->put('images/avatars/old-avatar.jpg', 'binary');
        $user->update(['avatar' => $oldUrl]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/account/avatar', [
                'image' => UploadedFile::fake()->image('new-avatar.jpg', 10, 10),
            ])
            ->assertOk();

        $newAvatar = $response->json('data.avatar');
        $this->assertNotSame($oldUrl, $newAvatar);
        $this->assertSame($newAvatar, $user->fresh()->avatar);
        $this->assertTrue(Storage::disk('public')->exists($this->storagePath($newAvatar)));
        $this->assertFalse(Storage::disk('public')->exists('images/avatars/old-avatar.jpg'));
    }

    public function test_customer_avatar_rejects_a_non_image_file(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/account/avatar', [
                'image' => UploadedFile::fake()->create('document.pdf', 200, 'application/pdf'),
            ])->assertStatus(422);
    }
}
