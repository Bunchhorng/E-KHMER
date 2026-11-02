<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CartMutationTest extends TestCase
{
    use RefreshDatabase;

    public static function unavailableStates(): array
    {
        return [
            ['variant', 'This option is no longer available. Please choose another option.'],
            ['product', 'This product is no longer available.'],
            ['shop', 'Products from this shop are temporarily unavailable.'],
            ['stock', 'This product is now out of stock.'],
        ];
    }

    #[DataProvider('unavailableStates')]
    public function test_business_rejection_is_actionable_and_preserves_existing_cart(string $state, string $message): void
    {
        $this->withHeaders(['X-Session-Id' => 'f08-cart']);
        $shop = Shop::factory()->create();
        $product = Product::factory()->withVariant(50, 10)->create(['shop_id' => $shop->id]);
        $variant = $product->variants()->sole();
        $this->postJson('/api/cart', ['product_variant_id' => $variant->id, 'quantity' => 2])->assertCreated();
        match ($state) {
            'variant' => $variant->update(['is_active' => false]),
            'product' => $product->update(['is_active' => false]),
            'shop' => $shop->update(['status' => Shop::STATUS_SUSPENDED]),
            'stock' => $variant->inventory()->update(['quantity' => 0]),
        };
        $this->postJson('/api/cart', ['product_variant_id' => $variant->id, 'quantity' => 1])
            ->assertUnprocessable()->assertJsonPath('errors.message.0', $message);
        $this->assertSame(2, (int) Cart::where('session_id', 'f08-cart')->sole()->items()->sole()->quantity);
    }

    public function test_invalid_variant_returns_validation_and_creates_no_phantom_item(): void
    {
        $this->withHeaders(['X-Session-Id' => 'f08-invalid'])
            ->postJson('/api/cart', ['product_variant_id' => 999999, 'quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('product_variant_id');
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_repeated_add_merges_existing_quantity_and_rejection_preserves_it(): void
    {
        $this->withHeaders(['X-Session-Id' => 'f08-merge']);
        $product = Product::factory()->withVariant(50, 2)->create();
        $variant = $product->variants()->sole();
        $payload = ['product_variant_id' => $variant->id, 'quantity' => 1];
        $this->postJson('/api/cart', $payload)->assertCreated()->assertJsonPath('data.totals.items_count', 1);
        $this->postJson('/api/cart', $payload)->assertCreated()->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 2)->assertJsonPath('data.totals.subtotal', 100);
        $this->postJson('/api/cart', $payload)->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->getJson('/api/cart')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.quantity', 2);
    }

    public function test_failed_quantity_update_preserves_confirmed_quantity(): void
    {
        $this->withHeaders(['X-Session-Id' => 'f08-update']);
        $product = Product::factory()->withVariant(50, 2)->create();
        $response = $this->postJson('/api/cart', ['product_variant_id' => $product->variants()->sole()->id, 'quantity' => 2])->assertCreated();
        $id = $response->json('data.items.0.id');
        $this->putJson('/api/cart/items/'.$id, ['quantity' => 10])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.quantity', 2)->assertJsonPath('data.totals.items_count', 2);
    }
}
