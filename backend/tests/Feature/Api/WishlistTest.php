<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_wishlist_is_unique_and_scoped_to_the_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/wishlist', ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/wishlist', ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/wishlist/{$product->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/wishlist')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_inactive_products_cannot_be_wishlisted(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => false]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/wishlist', ['product_id' => $product->id])
            ->assertStatus(404);
    }
}
