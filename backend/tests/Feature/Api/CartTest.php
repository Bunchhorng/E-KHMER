<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function variantWithStock(int $stock): array
    {
        $product = Product::factory()->withVariant(price: 49.99, stock: $stock)->create();
        $variant = $product->variants()->first();

        return [$variant->id];
    }

    public function test_guest_cart_requires_session_identifier(): void
    {
        $this->postJson('/api/cart', ['product_variant_id' => 1, 'quantity' => 1])
            ->assertStatus(422);
    }

    public function test_guest_can_add_and_read_cart(): void
    {
        [$variantId] = $this->variantWithStock(10);

        $headers = ['X-Session-Id' => 'sess-1'];

        $add = $this->withHeaders($headers)->postJson('/api/cart', [
            'product_variant_id' => $variantId,
            'quantity' => 2,
        ])->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 2);

        $read = $this->withHeaders($headers)->getJson('/api/cart')->assertOk();
        $this->assertSame(2, $read->json('data.items.0.quantity'));
        $this->assertSame(99.98, (float) $read->json('data.totals.subtotal'));
    }

    public function test_authenticated_user_can_manage_cart(): void
    {
        $user = User::factory()->create();
        [$variantId] = $this->variantWithStock(10);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/cart', ['product_variant_id' => $variantId, 'quantity' => 3])
            ->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 3);

        $cart = Cart::where('user_id', $user->id)->firstOrFail();
        $itemId = $cart->items()->firstOrFail()->id;

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/cart/items/$itemId", ['quantity' => 5])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 5);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/cart/items/$itemId")
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_quantity_above_available_stock_is_rejected(): void
    {
        [$variantId] = $this->variantWithStock(10);

        $this->withHeaders(['X-Session-Id' => 'sess-2'])
            ->postJson('/api/cart', ['product_variant_id' => $variantId, 'quantity' => 99])
            ->assertStatus(422)
            ->assertJsonPath('errors.quantity.0', 'Only 10 item(s) are currently available.');
    }

    public function test_out_of_stock_variant_is_rejected(): void
    {
        [$variantId] = $this->variantWithStock(0);

        $this->withHeaders(['X-Session-Id' => 'sess-3'])
            ->postJson('/api/cart', ['product_variant_id' => $variantId, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('errors.message.0', 'Variant is out of stock');
    }

    public function test_missing_variant_is_rejected(): void
    {
        $this->withHeaders(['X-Session-Id' => 'sess-4'])
            ->postJson('/api/cart', ['product_variant_id' => 99999, 'quantity' => 1])
            ->assertStatus(422);
    }

    public function test_totals_endpoint_returns_totals(): void
    {
        [$variantId] = $this->variantWithStock(10);

        $this->withHeaders(['X-Session-Id' => 'sess-5'])
            ->postJson('/api/cart', ['product_variant_id' => $variantId, 'quantity' => 2])
            ->assertCreated();

        $this->withHeaders(['X-Session-Id' => 'sess-5'])
            ->getJson('/api/cart/totals')
            ->assertOk()
            ->assertJsonPath('data.subtotal', 99.98)
            ->assertJsonPath('data.items_count', 2);
    }

    public function test_guest_cart_is_merged_into_user_cart_after_login(): void
    {
        $user = User::factory()->create();
        [$variantId] = $this->variantWithStock(10);

        $headers = ['X-Session-Id' => 'sess-merge'];

        $this->withHeaders($headers)
            ->postJson('/api/cart', ['product_variant_id' => $variantId, 'quantity' => 2])
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $cart = Cart::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(2, (int) $cart->items()->firstOrFail()->quantity);

        $token = $user->createToken('api')->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 2);
    }

    public function test_guest_cart_merge_caps_quantity_at_live_stock(): void
    {
        $user = User::factory()->create();
        [$variantId] = $this->variantWithStock(5);
        $headers = ['X-Session-Id' => 'sess-merge-stock'];

        $userCart = Cart::create(['user_id' => $user->id]);
        $userCart->items()->create(['product_variant_id' => $variantId, 'quantity' => 4]);

        $this->withHeaders($headers)
            ->postJson('/api/cart', ['product_variant_id' => $variantId, 'quantity' => 3])
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();

        $this->assertSame(5, (int) $userCart->fresh()->items()->sole()->quantity);
    }

    public function test_guest_cart_merge_skips_a_variant_that_becomes_unavailable(): void
    {
        $user = User::factory()->create();
        [$variantId] = $this->variantWithStock(5);
        $headers = ['X-Session-Id' => 'sess-merge-unavailable'];

        $this->withHeaders($headers)
            ->postJson('/api/cart', ['product_variant_id' => $variantId, 'quantity' => 1])
            ->assertCreated();

        \App\Models\ProductVariant::whereKey($variantId)->update(['is_active' => false]);

        $this->withHeaders($headers)
            ->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();

        $this->assertSame(0, Cart::where('user_id', $user->id)->firstOrFail()->items()->count());
    }

    public function test_clear_empties_the_cart(): void
    {
        [$variantId] = $this->variantWithStock(10);

        $this->withHeaders(['X-Session-Id' => 'sess-6'])
            ->postJson('/api/cart', ['product_variant_id' => $variantId, 'quantity' => 1]);

        $this->withHeaders(['X-Session-Id' => 'sess-6'])
            ->deleteJson('/api/cart')
            ->assertOk();
    }

    public function test_cannot_add_a_variant_of_an_unpublished_product(): void
    {
        $product = Product::factory()->withVariant(price: 49.99, stock: 5)->create(['is_active' => false]);
        $variantId = $product->variants()->first()->id;

        $this->withHeaders(['X-Session-Id' => 'sess-hidden'])
            ->postJson('/api/cart', ['product_variant_id' => $variantId, 'quantity' => 1])
            ->assertStatus(422);
    }

    public function test_cannot_add_an_inactive_variant(): void
    {
        [$variantId] = $this->variantWithStock(5);

        $this->withHeaders(['X-Session-Id' => 'sess-inactive'])
            ->postJson('/api/cart', ['product_variant_id' => $variantId, 'quantity' => 1])
            ->assertCreated();

        \App\Models\ProductVariant::whereKey($variantId)->update(['is_active' => false]);

        $this->withHeaders(['X-Session-Id' => 'sess-inactive-2'])
            ->postJson('/api/cart', ['product_variant_id' => $variantId, 'quantity' => 1])
            ->assertStatus(422);
    }
}
