<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private const HTML = '<h3>Features</h3><p><strong>Premium</strong> ផលិតផល &amp; warranty</p><ul><li>Bluetooth</li></ul><script>bad()</script><img src=x onerror="bad()">';

    private function owner(Shop $shop): User
    {
        $owner = User::factory()->create();
        $owner->shops()->attach($shop->id, ['role_in_shop' => 'owner', 'status' => 'active']);

        return $owner;
    }

    private function assertSafe(string $html): void
    {
        $this->assertStringContainsString('<h3>Features</h3>', $html);
        $this->assertStringContainsString('<strong>Premium</strong>', $html);
        $this->assertStringContainsString('<li>Bluetooth</li>', $html);
        $this->assertStringContainsString('ផលិតផល', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_seller_create_and_update_store_safe_html_and_public_detail_preserves_it(): void
    {
        $shop = Shop::factory()->create();
        $this->actingAs($this->owner($shop), 'sanctum');
        $response = $this->postJson("/api/seller/shops/{$shop->id}/products", ['name' => 'Rich product', 'description' => self::HTML, 'price' => 50, 'initial_stock' => 5])->assertCreated();
        $id = $response->json('data.id');
        $this->assertSafe($response->json('data.description'));
        $this->assertSafe(Product::findOrFail($id)->getRawOriginal('description'));
        $this->putJson("/api/seller/shops/{$shop->id}/products/{$id}", ['description' => self::HTML])->assertOk();
        $this->assertSafe($this->getJson('/api/catalog/products/'.$response->json('data.slug'))->assertOk()->json('data.description'));
    }

    public function test_admin_create_and_update_use_the_same_policy(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $response = $this->postJson('/api/admin/products', ['name' => 'Admin rich product', 'description' => self::HTML, 'price' => 50])->assertCreated();
        $id = $response->json('data.id');
        $this->assertSafe($response->json('data.description'));
        $this->putJson('/api/admin/products/'.$id, ['description' => self::HTML])->assertOk();
        $this->assertSafe(Product::findOrFail($id)->getRawOriginal('description'));
    }

    public function test_legacy_reads_are_safe_for_customer_seller_and_admin_without_rewriting_the_row(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->withVariant()->create(['shop_id' => $shop->id]);
        // Simulate a pre-policy row through an isolated test-only bulk update.
        Product::whereKey($product->id)->update(['description' => self::HTML]);
        $this->assertSafe($this->getJson('/api/catalog/products/'.$product->slug)->assertOk()->json('data.description'));
        $this->actingAs($this->owner($shop), 'sanctum');
        $this->assertSafe($this->getJson("/api/seller/shops/{$shop->id}/products/{$product->id}")->assertOk()->json('data.description'));
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $this->assertSafe($this->getJson('/api/admin/products/'.$product->id)->assertOk()->json('data.description'));
        $this->assertSame(self::HTML, $product->fresh()->getRawOriginal('description'));
    }

    public function test_repeated_edit_save_preserves_html_entities_and_omitted_description(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->withVariant()->create(['shop_id' => $shop->id, 'description' => '<p>Tom &amp; Jerry <strong>ផលិតផល</strong></p>']);
        $this->actingAs($this->owner($shop), 'sanctum');
        $path = "/api/seller/shops/{$shop->id}/products/{$product->id}";
        $expected = $product->description;
        for ($i = 0; $i < 3; $i++) {
            $html = $this->getJson($path)->assertOk()->json('data.description');
            $this->putJson($path, ['description' => $html])->assertOk()->assertJsonPath('data.description', $expected);
        }
        $this->putJson($path, ['price' => 49])->assertOk()->assertJsonPath('data.description', $expected);
    }

    public static function invalidDescriptions(): array
    {
        return [[['nested']], [str_repeat('a', 60001)], [str_repeat('ក', 21000)], [str_repeat('&', 20000)]];
    }

    #[DataProvider('invalidDescriptions')]
    public function test_invalid_or_over_capacity_descriptions_return_422_without_overwriting_existing_data(mixed $description): void
    {
        $product = Product::factory()->create(['description' => '<p>Original</p>']);
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $this->putJson('/api/admin/products/'.$product->id, ['description' => $description])->assertUnprocessable()->assertJsonValidationErrors('description');
        $this->assertSame('<p>Original</p>', $product->fresh()->getRawOriginal('description'));
    }

    public function test_description_updates_do_not_bypass_shop_ownership(): void
    {
        $shop = Shop::factory()->create();
        $other = Shop::factory()->create();
        $product = Product::factory()->create(['shop_id' => $other->id, 'description' => 'Original']);
        $this->actingAs($this->owner($shop), 'sanctum');
        $this->putJson("/api/seller/shops/{$shop->id}/products/{$product->id}", ['description' => self::HTML])->assertNotFound();
        $this->assertSame('Original', $product->fresh()->getRawOriginal('description'));
    }
}
