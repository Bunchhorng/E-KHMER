<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerProductPhotosTest extends TestCase
{
    use RefreshDatabase;

    private function signInOwner(Shop $shop): void
    {
        $owner = User::factory()->create();
        $owner->shops()->attach($shop->id, ['role_in_shop' => 'owner', 'status' => 'active']);
        $this->actingAs($owner, 'sanctum');
        Storage::fake('public');
    }

    public function test_owner_can_create_a_product_with_uploaded_and_camera_photos(): void
    {
        $shop = Shop::factory()->create();
        $this->signInOwner($shop);

        $response = $this->post("/api/seller/shops/{$shop->id}/products", [
            'name' => 'Photographed product',
            'price' => '19.50',
            'initial_stock' => '5',
            'is_active' => '1',
            'images' => json_encode(['upload:1', 'upload:0']),
            'image_files' => [UploadedFile::fake()->image('gallery.png'), UploadedFile::fake()->image('camera.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonCount(2, 'data.gallery');

        $product = Product::findOrFail($response->json('data.id'));
        $photos = $product->images()->orderBy('sort_order')->get();
        $this->assertTrue($photos[0]->is_cover);
        $this->assertFalse($photos[1]->is_cover);
        $this->assertStringEndsWith('.jpg', $photos[0]->image_path);
        $this->assertStringEndsWith('.png', $photos[1]->image_path);
        $this->assertCount(2, Storage::disk('public')->allFiles('images/products'));
        foreach ($photos as $photo) {
            Storage::disk('public')->assertExists('images/products/'.basename($photo->image_path));
        }
    }

    public function test_multipart_update_keeps_retained_photos_and_replaces_the_cover(): void
    {
        $shop = Shop::factory()->create();
        $this->signInOwner($shop);
        $product = Product::factory()->withVariant(20, 10)->create(['shop_id' => $shop->id]);
        Storage::disk('public')->put('images/products/old.jpg', 'old');
        Storage::disk('public')->put('images/products/keep.jpg', 'keep');
        $removed = $product->images()->create(['image_path' => '/storage/images/products/old.jpg', 'is_cover' => true]);
        $kept = $product->images()->create(['image_path' => '/storage/images/products/keep.jpg', 'sort_order' => 1]);

        $this->post("/api/seller/shops/{$shop->id}/products/{$product->id}", [
            '_method' => 'PUT',
            'price' => '25',
            'images' => json_encode(['upload:0', $kept->image_path]),
            'image_files' => [UploadedFile::fake()->image('new-cover.jpg')],
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(2, 'data.gallery');

        $this->assertDatabaseMissing('product_images', ['id' => $removed->id]);
        $this->assertFalse($kept->fresh()->is_cover);
        $this->assertStringEndsWith('.jpg', $product->images()->where('is_cover', true)->sole()->image_path);
        Storage::disk('public')->assertMissing('images/products/old.jpg');
        Storage::disk('public')->assertExists('images/products/keep.jpg');
        $this->assertEquals(25, $product->variants()->first()->price);
        $this->assertEquals(10, $product->variants()->first()->inventory->quantity);

        $this->putJson("/api/seller/shops/{$shop->id}/products/{$product->id}", ['images' => []])->assertOk()->assertJsonCount(0, 'data.gallery');
        $this->assertSame([], Storage::disk('public')->allFiles('images/products'));
    }

    public function test_existing_cover_can_be_changed_without_uploading_again(): void
    {
        $shop = Shop::factory()->create();
        $this->signInOwner($shop);
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $first = $product->images()->create(['image_path' => '/storage/first.jpg', 'is_cover' => true]);
        $second = $product->images()->create(['image_path' => '/storage/second.jpg', 'sort_order' => 1]);
        $this->putJson("/api/seller/shops/{$shop->id}/products/{$product->id}", ['images' => [$second->image_path, $first->image_path]])->assertOk();
        $this->assertTrue($second->fresh()->is_cover);
        $this->assertFalse($first->fresh()->is_cover);
    }

    public function test_seller_cannot_attach_another_products_image_or_an_arbitrary_path(): void
    {
        $shop = Shop::factory()->create();
        $this->signInOwner($shop);
        $other = Product::factory()->create();
        $image = $other->images()->create(['image_path' => '/storage/images/products/other.jpg']);
        Storage::disk('public')->put('images/products/unattached.jpg', 'unattached');
        foreach ([$image->image_path, '/storage/images/products/unattached.jpg', 'https://example.com/photo.jpg', 'upload:9'] as $path) {
            $this->postJson("/api/seller/shops/{$shop->id}/products", ['name' => 'Invalid gallery', 'images' => [$path]])
                ->assertUnprocessable()->assertJsonValidationErrors('images.0');
        }
        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
        $this->assertDatabaseMissing('products', ['name' => 'Invalid gallery']);
    }

    public function test_upload_rejects_non_images_oversized_files_and_excess_total_size(): void
    {
        $shop = Shop::factory()->create();
        $this->signInOwner($shop);
        $url = "/api/seller/shops/{$shop->id}/products";
        foreach ([UploadedFile::fake()->create('script.php', 1, 'text/plain'), UploadedFile::fake()->image('large.jpg')->size(5121)] as $file) {
            $this->postJson($url, ['name' => 'Invalid photo', 'images' => ['upload:0'], 'image_files' => [$file]])
                ->assertUnprocessable()->assertJsonValidationErrors('image_files.0');
        }
        $this->postJson($url, [
            'name' => 'Too much data', 'images' => ['upload:0', 'upload:1', 'upload:2'],
            'image_files' => array_map(fn () => UploadedFile::fake()->image('photo.jpg')->size(4000), range(1, 3)),
        ])->assertUnprocessable()->assertJsonValidationErrors('image_files');
        $this->postJson($url, ['name' => 'Too many photos', 'images' => array_fill(0, 21, 'upload:0')])
            ->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_failed_product_save_cleans_up_new_uploads_and_keeps_existing_photos(): void
    {
        $shop = Shop::factory()->create();
        $this->signInOwner($shop);
        $product = Product::factory()->withVariant()->create(['shop_id' => $shop->id]);
        $photo = $product->images()->create(['image_path' => '/storage/images/products/existing.jpg', 'is_cover' => true]);
        Storage::disk('public')->put('images/products/existing.jpg', 'existing');
        $this->putJson("/api/seller/shops/{$shop->id}/products/{$product->id}", [
            'images' => ['upload:0'], 'image_files' => [UploadedFile::fake()->image('new.jpg')],
            'variants' => [['sku' => 'DUPLICATE'], ['sku' => 'DUPLICATE']],
        ])->assertUnprocessable();
        $this->assertDatabaseHas('product_images', ['id' => $photo->id]);
        $this->assertSame(['images/products/existing.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_upload_requires_authentication_and_access_to_the_exact_shop_and_product(): void
    {
        $shop = Shop::factory()->create();
        $other = Shop::factory()->create();
        $this->postJson("/api/seller/shops/{$shop->id}/products", [])->assertUnauthorized();
        $this->signInOwner($shop);
        $payload = ['name' => 'Unauthorized', 'images' => ['upload:0'], 'image_files' => [UploadedFile::fake()->image('photo.jpg')]];
        $this->postJson("/api/seller/shops/{$other->id}/products", $payload)->assertForbidden();
        $product = Product::factory()->create(['shop_id' => $other->id]);
        $this->putJson("/api/seller/shops/{$shop->id}/products/{$product->id}", $payload)->assertNotFound();
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson("/api/seller/shops/{$shop->id}/products", $payload)->assertForbidden();
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
