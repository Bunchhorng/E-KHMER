<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function owner(Shop $shop, string $role = 'owner'): User
    {
        $user = User::factory()->create();
        $user->shops()->attach($shop->id, ['role_in_shop' => $role, 'status' => 'active']);

        return $user;
    }

    public function test_owner_can_update_settings_and_media_but_cannot_change_admin_fields(): void
    {
        Storage::fake('public');
        $shop = Shop::factory()->create(['commission_rate' => 5]);
        $this->actingAs($this->owner($shop), 'sanctum')->post("/api/seller/shops/{$shop->id}/settings", ['_method' => 'PUT',
            'name' => 'Updated Shop', 'country' => 'KH', 'logo_file' => UploadedFile::fake()->image('logo.jpg'),
            'status' => 'suspended', 'commission_rate' => 99, 'bakong_account_id' => 'owner@bank',
            'bakong_merchant_name' => 'Owner', 'bakong_merchant_city' => 'Phnom Penh'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.name', 'Updated Shop');
        $this->assertSame('active', $shop->fresh()->status);
        $this->assertEquals(5, $shop->fresh()->commission_rate);
        $this->assertCount(1, Storage::disk('public')->allFiles('images/shops'));
    }

    public function test_other_shop_members_cannot_modify_settings_and_managers_cannot_redirect_payments(): void
    {
        $shop = Shop::factory()->create();
        $other = Shop::factory()->create();
        $this->actingAs($this->owner($other), 'sanctum')->putJson("/api/seller/shops/{$shop->id}/settings", ['name' => 'Attack', 'country' => 'KH'])->assertForbidden();
        $this->actingAs($this->owner($shop, 'manager'), 'sanctum')->putJson("/api/seller/shops/{$shop->id}/settings", ['name' => 'Attack', 'country' => 'KH', 'bakong_account_id' => 'attacker@bank'])->assertForbidden();
    }

    public function test_public_shops_hide_private_fields_and_count_only_published_products(): void
    {
        $shop = Shop::factory()->create(['bakong_account_id' => 'private@bank', 'commission_rate' => 7]);
        Product::factory()->create(['shop_id' => $shop->id]);
        Product::factory()->inactive()->create(['shop_id' => $shop->id]);
        $this->getJson("/api/shops/{$shop->slug}")->assertOk()->assertJsonPath('data.products_count', 1)
            ->assertJsonMissingPath('data.commission_rate')->assertJsonMissingPath('data.bakong_account_id');
        $shop->update(['status' => 'suspended']);
        $this->getJson("/api/shops/{$shop->slug}")->assertNotFound();
        $this->getJson('/api/shops')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/shops?per_page=999999')->assertUnprocessable();
    }

    public function test_storefront_search_stays_scoped_to_the_requested_shop(): void
    {
        $shop = Shop::factory()->create();
        $other = Shop::factory()->create();
        Product::factory()->withVariant()->create(['shop_id' => $shop->id, 'name' => 'Blue Shirt']);
        Product::factory()->withVariant()->create(['shop_id' => $other->id, 'name' => 'Blue Shirt']);
        Product::factory()->inactive()->create(['shop_id' => $shop->id, 'name' => 'Blue Hidden']);
        $this->getJson("/api/shops/{$shop->slug}/products?q=Blue")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/shops/{$shop->slug}/products?per_page=999")->assertUnprocessable();
    }

    public function test_seller_can_manage_variant_options_initial_stock_and_inactive_variants(): void
    {
        $shop = Shop::factory()->create();
        $this->actingAs($this->owner($shop), 'sanctum');
        $response = $this->postJson("/api/seller/shops/{$shop->id}/products", ['name' => 'Options', 'price' => 10, 'variants' => [
            ['name' => 'Blue / L', 'sku' => 'BLUE-L', 'price' => 12, 'quantity' => 5, 'attributes' => [['attribute' => 'Color', 'value' => 'Blue'], ['attribute' => 'Size', 'value' => 'L']]],
            ['name' => 'Red / S', 'sku' => 'RED-S', 'price' => 11, 'quantity' => 2, 'is_active' => false, 'attributes' => [['attribute' => 'Color', 'value' => 'Red'], ['attribute' => 'Size', 'value' => 'S']]],
        ]])->assertCreated()->assertJsonCount(2, 'data.variants');
        $product = Product::findOrFail($response->json('data.id'));
        $this->getJson("/api/seller/shops/{$shop->id}/products/{$product->id}")->assertOk()->assertJsonCount(2, 'data.variants');
        $this->getJson("/api/catalog/products/{$product->slug}")->assertOk()->assertJsonCount(1, 'data.variants');
        $this->assertEquals(5, $product->variants()->where('sku', 'BLUE-L')->first()->inventory->quantity);
    }

    public function test_variant_ids_from_another_product_are_rejected_and_reserved_variants_cannot_be_removed(): void
    {
        $shop = Shop::factory()->create();
        $this->actingAs($this->owner($shop), 'sanctum');
        $product = Product::factory()->withVariant()->create(['shop_id' => $shop->id]);
        $other = Product::factory()->withVariant()->create(['shop_id' => $shop->id]);
        $url = "/api/seller/shops/{$shop->id}/products/{$product->id}";
        $this->putJson($url, ['variants' => [['id' => $other->variants()->first()->id]]])->assertUnprocessable();
        $product->variants()->first()->inventory->update(['reserved_quantity' => 1]);
        $this->putJson($url, ['variants' => [['name' => 'replacement']]])->assertUnprocessable()->assertJsonValidationErrors('variants');
    }
}
