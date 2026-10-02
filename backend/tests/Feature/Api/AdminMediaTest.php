<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMediaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function storagePath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;
        $path = ltrim($path, '/');

        return str_starts_with($path, 'storage/') ? substr($path, strlen('storage/')) : $path;
    }

    public function test_upload_requires_authentication(): void
    {
        Storage::fake('public');

        $this->postJson('/api/admin/uploads/image', [
            'image' => UploadedFile::fake()->image('photo.jpg', 10, 10),
        ])->assertStatus(401);
    }

    public function test_customer_is_forbidden_from_upload(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/admin/uploads/image', [
                'image' => UploadedFile::fake()->image('photo.jpg', 10, 10),
            ])->assertStatus(403);
    }

    public function test_admin_can_upload_an_image(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/uploads/image', [
                'image' => UploadedFile::fake()->image('photo.jpg', 10, 10),
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['path']]);

        $path = $response->json('data.path');
        $this->assertStringContainsString('/storage/images/products/', $path);
        $this->assertTrue(Storage::disk('public')->exists($this->storagePath($path)));
    }

    public function test_upload_rejects_a_non_image_file(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/uploads/image', [
                'image' => UploadedFile::fake()->create('document.pdf', 200, 'application/pdf'),
            ])->assertStatus(422);
    }

    public function test_upload_rejects_an_invalid_context(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/uploads/image', [
                'image' => UploadedFile::fake()->image('photo.jpg', 10, 10),
                'context' => 'avatars',
            ])->assertStatus(422);
    }

    public function test_product_gallery_image_can_be_attached_and_removed(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/products/{$product->id}/images", [
                'image' => UploadedFile::fake()->image('gallery.jpg', 10, 10),
                'alt_text' => 'Front view',
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'image_path', 'is_cover']])
            ->assertJsonPath('data.is_cover', true);

        $imageId = $response->json('data.id');
        $savedPath = $this->storagePath($response->json('data.image_path'));
        $this->assertTrue(Storage::disk('public')->exists($savedPath));

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/products/{$product->id}/images/{$imageId}")
            ->assertOk()
            ->assertJsonPath('data.message', 'Image deleted.');

        $this->assertFalse(Storage::disk('public')->exists($savedPath));
        $this->assertDatabaseMissing('product_images', ['id' => $imageId]);
    }

    public function test_brand_logo_can_be_uploaded_and_replaces_old_logo(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $oldUrl = Storage::disk('public')->url('images/brands/old-logo.png');
        Storage::disk('public')->put('images/brands/old-logo.png', 'binary');
        $brand = Brand::factory()->create(['logo' => $oldUrl]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/brands/{$brand->id}/logo", [
                'image' => UploadedFile::fake()->image('new-logo.png', 10, 10),
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['logo']]);

        $logo = $response->json('data.logo');
        $this->assertStringContainsString('/storage/images/brands/', $logo);
        $this->assertTrue(Storage::disk('public')->exists($this->storagePath($logo)));
        $this->assertFalse(Storage::disk('public')->exists('images/brands/old-logo.png'));
        $this->assertSame($logo, $brand->fresh()->logo);
    }

    public function test_category_image_can_be_uploaded(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/categories/{$category->id}/image", [
                'image' => UploadedFile::fake()->image('category.jpg', 10, 10),
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['image']]);

        $image = $response->json('data.image');
        $this->assertStringContainsString('/storage/images/categories/', $image);
        $this->assertTrue(Storage::disk('public')->exists($this->storagePath($image)));
        $this->assertSame($image, $category->fresh()->image);
    }

    public function test_product_store_with_images_creates_gallery(): void
    {
        Storage::fake('public');

        $urlA = Storage::disk('public')->url('images/products/a.jpg');
        $urlB = Storage::disk('public')->url('images/products/b.jpg');
        Storage::disk('public')->put('images/products/a.jpg', 'a');
        Storage::disk('public')->put('images/products/b.jpg', 'b');

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/products', [
                'name' => 'Gallery Product',
                'sku' => 'GAL-1',
                'price' => 49.99,
                'images' => [$urlB, $urlA],
            ])
            ->assertStatus(201);

        $product = Product::where('sku', 'GAL-1')->firstOrFail();
        $gallery = $response->json('data.gallery');

        $this->assertCount(2, $gallery);
        $this->assertSame($urlB, $gallery[0]['image_path']);
        $this->assertTrue($gallery[0]['is_cover']);
        $this->assertSame($urlA, $gallery[1]['image_path']);
        $this->assertFalse($gallery[1]['is_cover']);
        $this->assertSame(2, $product->images()->count());
    }

    public function test_product_update_syncs_gallery_and_deletes_removed_files(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $urlA = Storage::disk('public')->url('images/products/a.jpg');
        $urlB = Storage::disk('public')->url('images/products/b.jpg');
        Storage::disk('public')->put('images/products/a.jpg', 'a');
        Storage::disk('public')->put('images/products/b.jpg', 'b');

        $product = Product::factory()->create();
        $product->images()->create(['image_path' => $urlA, 'sort_order' => 0, 'is_cover' => true]);
        $product->images()->create(['image_path' => $urlB, 'sort_order' => 1, 'is_cover' => false]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/products/{$product->id}", [
                'name' => $product->name,
                'slug' => $product->slug,
                'images' => [$urlB],
            ])
            ->assertOk();

        $gallery = $response->json('data.gallery');
        $this->assertCount(1, $gallery);
        $this->assertSame($urlB, $gallery[0]['image_path']);
        $this->assertTrue($gallery[0]['is_cover']);
        $this->assertFalse(Storage::disk('public')->exists('images/products/a.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('images/products/b.jpg'));
    }

    public function test_product_update_with_empty_images_clears_gallery(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $urlA = Storage::disk('public')->url('images/products/a.jpg');
        Storage::disk('public')->put('images/products/a.jpg', 'a');

        $product = Product::factory()->create();
        $product->images()->create(['image_path' => $urlA, 'sort_order' => 0, 'is_cover' => true]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/products/{$product->id}", [
                'name' => $product->name,
                'slug' => $product->slug,
                'images' => [],
            ])
            ->assertOk();

        $this->assertSame(0, $product->images()->count());
        $this->assertFalse(Storage::disk('public')->exists('images/products/a.jpg'));
    }

    public function test_uploading_a_new_cover_demotes_the_previous_cover(): void
    {
        Storage::fake('public');

        $urlA = Storage::disk('public')->url('images/products/a.jpg');
        Storage::disk('public')->put('images/products/a.jpg', 'a');

        $product = Product::factory()->create();
        $first = $product->images()->create(['image_path' => $urlA, 'sort_order' => 0, 'is_cover' => true]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/products/{$product->id}/images", [
                'image' => UploadedFile::fake()->image('new-cover.jpg', 10, 10),
                'is_cover' => true,
            ])->assertCreated();

        // A gallery has exactly one cover: the old flag has to be cleared or the
        // storefront renders two cover images.
        $this->assertSame(1, $product->images()->where('is_cover', true)->count());
        $this->assertFalse($first->fresh()->is_cover);
        $this->assertTrue($product->images()->orderByDesc('id')->first()->is_cover);
    }

    public function test_uploading_a_non_cover_keeps_the_existing_cover(): void
    {
        Storage::fake('public');

        $urlA = Storage::disk('public')->url('images/products/a.jpg');
        Storage::disk('public')->put('images/products/a.jpg', 'a');

        $product = Product::factory()->create();
        $first = $product->images()->create(['image_path' => $urlA, 'sort_order' => 0, 'is_cover' => true]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/products/{$product->id}/images", [
                'image' => UploadedFile::fake()->image('second.jpg', 10, 10),
            ])->assertCreated();

        $this->assertSame(1, $product->images()->where('is_cover', true)->count());
        $this->assertTrue($first->fresh()->is_cover);
    }
}