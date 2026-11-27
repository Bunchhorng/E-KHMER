<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloudDeployReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_responds_ok(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_cors_config_allows_configured_cloudflare_origin(): void
    {
        config()->set('cors.allowed_origins', ['https://ekhmer.pages.dev']);
        config()->set('cors.allowed_origins_patterns', ['#^https://.*\.(pages|workers)\.dev$#']);

        $this->withHeaders([
            'Origin' => 'https://ekhmer.pages.dev',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/catalog/products')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://ekhmer.pages.dev');

        $this->withHeaders([
            'Origin' => 'https://ekhmer-storefront.demo.workers.dev',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/catalog/products')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://ekhmer-storefront.demo.workers.dev');
    }

    public function test_catalog_price_sorting_and_range_filters_work_with_legacy_and_variant_products(): void
    {
        $legacyCheap = Product::factory()->create(['price' => 8.00, 'name' => 'Legacy Cheap']);
        $variantMid = Product::factory()->withVariant(20.00, 10)->create(['price' => 25.00, 'name' => 'Variant Mid']);
        $variantHigh = Product::factory()->withVariant(60.00, 10)->create(['price' => 60.00, 'name' => 'Variant High']);

        $asc = $this->getJson('/api/catalog/products?sort=price-asc')->assertOk();
        $idsAsc = collect($asc->json('data'))->pluck('id')->all();
        $this->assertSame([$legacyCheap->id, $variantMid->id, $variantHigh->id], $idsAsc);

        $desc = $this->getJson('/api/catalog/products?sort=price-desc')->assertOk();
        $idsDesc = collect($desc->json('data'))->pluck('id')->all();
        $this->assertSame([$variantHigh->id, $variantMid->id, $legacyCheap->id], $idsDesc);

        $filtered = $this->getJson('/api/catalog/products?min=15&max=30')->assertOk();
        $this->assertSame([$variantMid->id], collect($filtered->json('data'))->pluck('id')->all());
    }
}
