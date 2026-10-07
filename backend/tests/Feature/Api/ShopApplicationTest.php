<?php

namespace Tests\Feature\Api;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_submit_pending_shop_applications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/seller/application', [
            'name' => 'Tech Store Cambodia', 'email' => 'shop@example.test',
        ])->assertCreated()->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.slug', 'tech-store-cambodia');

        $shop = Shop::firstOrFail();
        $this->assertTrue($shop->hasStaff($user));
        $this->actingAs($user, 'sanctum')->postJson('/api/seller/application', ['name' => 'Second Shop'])
            ->assertCreated();

        $this->assertSame(2, $user->fresh()->shops()->wherePivot('role_in_shop', 'owner')->count());
    }

    public function test_admin_can_reject_only_with_a_reason_and_reactivate_a_shop(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_PENDING]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/shops/{$shop->id}/status", ['status' => 'rejected'])
            ->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/shops/{$shop->id}/status", [
            'status' => 'rejected', 'rejection_reason' => 'Business details are incomplete.',
        ])->assertOk()->assertJsonPath('data.rejection_reason', 'Business details are incomplete.');
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/shops/{$shop->id}/status", ['status' => 'active'])
            ->assertOk()->assertJsonPath('data.rejection_reason', null);
    }
}
