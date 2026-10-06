<?php

namespace Tests\Feature\Api;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_percentage_coupon_validates(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'SAVE10', 'value' => 10]);

        $this->postJson('/api/coupons/validate', [
            'code' => 'save10',
            'subtotal' => 249.99,
        ])->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonStructure(['data' => ['discount_amount', 'code']]);
    }

    public function test_expired_coupon_is_rejected(): void
    {
        $coupon = Coupon::factory()->expired()->create(['code' => 'OLD10']);

        $this->postJson('/api/coupons/validate', [
            'code' => 'OLD10',
            'subtotal' => 100,
        ])->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'This coupon has expired.');
    }

    public function test_not_yet_active_coupon_is_rejected(): void
    {
        $coupon = Coupon::factory()->notStarted()->create(['code' => 'FUTURE10']);

        $this->postJson('/api/coupons/validate', [
            'code' => 'FUTURE10',
            'subtotal' => 100,
        ])->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'This coupon is not yet active.');
    }

    public function test_inactive_coupon_is_rejected(): void
    {
        $coupon = Coupon::factory()->inactive()->create(['code' => 'GONE10']);

        $this->postJson('/api/coupons/validate', [
            'code' => 'GONE10',
            'subtotal' => 100,
        ])->assertStatus(422);
    }

    public function test_minimum_order_amount_is_enforced(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'BIGSPEND', 'min_order_amount' => 200]);

        $this->postJson('/api/coupons/validate', [
            'code' => 'BIGSPEND',
            'subtotal' => 100,
        ])->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'This coupon requires a minimum order amount of 200.00.');

        $this->postJson('/api/coupons/validate', [
            'code' => 'BIGSPEND',
            'subtotal' => 200,
        ])->assertOk();
    }

    public function test_usage_limit_is_enforced(): void
    {
        $coupon = Coupon::factory()->usageLimitReached(5)->create(['code' => 'USED UP']);

        $this->postJson('/api/coupons/validate', [
            'code' => 'USED UP',
            'subtotal' => 100,
        ])->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'This coupon has reached its usage limit.');
    }

    public function test_per_user_limit_is_enforced(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create(['code' => 'ONEPERSON', 'per_user_limit' => 1]);

        $coupon->usages()->create(['user_id' => $user->id, 'redeemed_at' => now()]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/coupons/validate', [
                'code' => 'ONEPERSON',
                'subtotal' => 100,
            ])->assertStatus(422);
    }

    public function test_admin_cannot_create_an_invalid_percentage_coupon(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/coupons', [
                'code' => 'TOO-MUCH',
                'type' => 'percentage',
                'value' => 101,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('value');
    }
}
