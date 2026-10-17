<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerBarcodePricingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(Shop $shop): User
    {
        $owner = User::factory()->create();
        $owner->shops()->attach($shop->id, ['role_in_shop' => 'owner', 'status' => 'active']);

        return $owner;
    }

    public function test_simple_product_stores_manufacturer_barcode_purchase_and_selling_prices(): void
    {
        $shop = Shop::factory()->create();
        $this->actingAs($this->owner($shop), 'sanctum');
        $response = $this->postJson("/api/seller/shops/{$shop->id}/products", [
            'name' => 'Barcoded item', 'barcode' => '0012345678905', 'cost_price' => 4.25, 'price' => 7.5, 'initial_stock' => 12,
        ])->assertCreated()->assertJsonPath('data.barcode', '0012345678905')->assertJsonPath('data.cost_price', 4.25)
            ->assertJsonPath('data.price', 7.5)->assertJsonPath('data.variants.0.barcode', '0012345678905')
            ->assertJsonPath('data.variants.0.cost_price', 4.25)->assertJsonPath('data.variants.0.price', 7.5);
        $product = Product::findOrFail($response->json('data.id'));
        $this->assertSame('0012345678905', $product->barcode);
        $this->assertEquals(12, $product->variants()->sole()->inventory->quantity);
        $this->assertArrayNotHasKey('cost_price', $product->toArray());
        $this->assertArrayNotHasKey('cost_price', $product->variants()->sole()->toArray());
    }

    public function test_partial_edits_keep_existing_prices_visibility_and_stock_and_allow_clearing_optional_fields(): void
    {
        $shop = Shop::factory()->create();
        $this->actingAs($this->owner($shop), 'sanctum');
        $id = $this->postJson("/api/seller/shops/{$shop->id}/products", ['name' => 'Edit item', 'barcode' => '00001234', 'cost_price' => 3, 'price' => 9, 'initial_stock' => 8, 'is_active' => false])
            ->assertCreated()->json('data.id');
        $url = "/api/seller/shops/{$shop->id}/products/{$id}";
        $this->putJson($url, ['barcode' => '00005678', 'cost_price' => 4.5])->assertOk()
            ->assertJsonPath('data.price', 9)->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.variants.0.price', 9)->assertJsonPath('data.variants.0.barcode', '00005678')
            ->assertJsonPath('data.variants.0.cost_price', 4.5)->assertJsonPath('data.variants.0.quantity', 8);
        $this->putJson($url, ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.cost_price', 4.5)->assertJsonPath('data.barcode', '00005678');
        $this->putJson($url, ['barcode' => null, 'cost_price' => null])->assertOk()
            ->assertJsonPath('data.barcode', null)->assertJsonPath('data.cost_price', null)
            ->assertJsonPath('data.variants.0.barcode', null)->assertJsonPath('data.variants.0.cost_price', null);
    }

    public function test_variants_keep_their_own_barcode_and_prices_in_multipart_edits(): void
    {
        $shop = Shop::factory()->create();
        $this->actingAs($this->owner($shop), 'sanctum');
        $rows = [
            ['name' => 'Blue', 'barcode' => '00001111', 'cost_price' => 3.5, 'price' => 8, 'quantity' => 4],
            ['name' => 'Red', 'barcode' => '00002222', 'cost_price' => 4, 'price' => 10, 'quantity' => 5],
        ];
        $id = $this->post('/api/seller/shops/'.$shop->id.'/products', ['name' => 'Options', 'variants' => json_encode($rows)], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.price', 8)->json('data.id');
        $variants = Product::findOrFail($id)->variants()->orderBy('id')->get();
        $rows = [
            ['id' => $variants[0]->id, 'barcode' => ' 00003333 ', 'cost_price' => 2.75, 'price' => 7],
            ['id' => $variants[1]->id, 'barcode' => '', 'cost_price' => '', 'price' => 10],
        ];
        $this->post("/api/seller/shops/{$shop->id}/products/{$id}", ['_method' => 'PUT', 'variants' => json_encode($rows)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.variants.0.barcode', '00003333')->assertJsonPath('data.variants.0.cost_price', 2.75)
            ->assertJsonPath('data.variants.1.barcode', null)->assertJsonPath('data.variants.1.cost_price', null)
            ->assertJsonPath('data.variants.0.quantity', 4)->assertJsonPath('data.variants.1.quantity', 5);
    }

    public function test_purchase_cost_is_not_exposed_in_public_catalog_even_to_a_logged_in_owner(): void
    {
        $shop = Shop::factory()->create();
        $this->actingAs($this->owner($shop), 'sanctum');
        $response = $this->postJson("/api/seller/shops/{$shop->id}/products", ['name' => 'Private cost', 'barcode' => '00005555', 'cost_price' => 1.25, 'price' => 6, 'initial_stock' => 3])->assertCreated();
        $slug = $response->json('data.slug');
        foreach (["/api/catalog/products/{$slug}", '/api/catalog/products', "/api/shops/{$shop->slug}/products"] as $path) {
            $public = $this->getJson($path)->assertOk();
            $public->assertJsonMissingPath(str_ends_with($path, $slug) ? 'data.cost_price' : 'data.0.cost_price')
                ->assertJsonMissingPath(str_ends_with($path, $slug) ? 'data.variants.0.cost_price' : 'data.0.variants.0.cost_price');
        }
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson("/api/seller/shops/{$shop->id}/products/".$response->json('data.id'))->assertForbidden();
    }

    public function test_barcode_search_is_shop_scoped_for_products_and_stock(): void
    {
        $first = Shop::factory()->create();
        $second = Shop::factory()->create();
        foreach ([$first, $second] as $shop) {
            $this->actingAs($this->owner($shop), 'sanctum')->postJson("/api/seller/shops/{$shop->id}/products", [
                'name' => 'Scanned item', 'barcode' => '00009999', 'price' => 4, 'initial_stock' => 2,
                'variants' => [['barcode' => '00008888', 'price' => 4, 'quantity' => 2]],
            ])->assertCreated();
        }
        foreach (['00009999', '00008888'] as $barcode) {
            $this->getJson("/api/seller/shops/{$second->id}/products?q={$barcode}")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.shop_id', $second->id);
            $this->getJson("/api/seller/shops/{$second->id}/inventory?q={$barcode}")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.shop_id', $second->id);
        }
    }

    public function test_invalid_cost_and_barcode_values_are_rejected_before_writing(): void
    {
        $shop = Shop::factory()->create();
        $this->actingAs($this->owner($shop), 'sanctum');
        foreach ([['cost_price' => -1], ['cost_price' => 1.234], ['cost_price' => 100000000], ['barcode' => 12345], ['barcode' => "123\n456"], ['variants' => [['barcode' => 123, 'cost_price' => -1]]]] as $invalid) {
            $this->postJson("/api/seller/shops/{$shop->id}/products", ['name' => 'Invalid'] + $invalid)->assertUnprocessable();
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_purchase_price_can_be_zero_or_higher_than_selling_price(): void
    {
        $shop = Shop::factory()->create();
        $this->actingAs($this->owner($shop), 'sanctum');
        foreach ([0, 9] as $cost) {
            $this->postJson("/api/seller/shops/{$shop->id}/products", ['name' => 'Priced item', 'barcode' => '0', 'cost_price' => $cost, 'price' => 5])
                ->assertCreated()->assertJsonPath('data.barcode', '0')->assertJsonPath('data.cost_price', $cost);
        }
    }
}
