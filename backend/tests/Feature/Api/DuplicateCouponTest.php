<?php

namespace Tests\Feature\Api;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DuplicateCouponTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'sanctum');

        return $admin;
    }

    private function payload(mixed $code = 'SAVE20'): array
    {
        return ['code' => $code, 'type' => 'percentage', 'value' => 20,
            'min_order_amount' => 100, 'max_discount_amount' => 25,
            'usage_limit' => 50, 'per_user_limit' => 2, 'is_active' => true];
    }

    public function test_unique_create_normalizes_code_without_changing_supported_syntax(): void
    {
        $this->admin();
        Coupon::factory()->create(['code' => 'SAVE10']);

        $this->postJson('/api/admin/coupons', $this->payload('  welcome-10_new  '))
            ->assertCreated()->assertJsonPath('data.code', 'WELCOME-10_NEW')
            ->assertJsonPath('data.max_discount_amount', 25);

        $this->assertDatabaseCount('coupons', 2);
        $this->assertDatabaseHas('coupons', ['code' => 'WELCOME-10_NEW', 'used_count' => 0]);
        $this->assertDatabaseCount('coupon_usages', 0);
    }

    #[TestWith(['SAVE20'])]
    #[TestWith(['save20'])]
    #[TestWith(['Save20'])]
    #[TestWith(['  SAVE20  '])]
    public function test_duplicates_are_rejected_before_any_insert_with_safe_field_errors(string $code): void
    {
        $this->admin();
        $existing = Coupon::factory()->create(['code' => 'SAVE20', 'used_count' => 3]);
        $attempts = 0;
        Coupon::creating(function () use (&$attempts): void {
            $attempts++;
        });
        config(['app.debug' => true]);

        $response = $this->postJson('/api/admin/coupons', $this->payload($code))
            ->assertUnprocessable()->assertJsonPath('errors.code.0', 'The coupon code has already been taken.')
            ->assertJsonMissingPath('exception')->assertJsonMissingPath('trace');

        $this->assertSame(0, $attempts);
        $this->assertDatabaseCount('coupons', 1);
        $this->assertSame(3, (int) $existing->fresh()->used_count);
        $this->assertDatabaseCount('coupon_usages', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('coupons_code_unique', $response->getContent());
    }

    public function test_update_ignores_only_the_route_coupon_and_preserves_usage_and_ownership(): void
    {
        $this->admin();
        $shop = Shop::factory()->create();
        $coupon = Coupon::factory()->create(['shop_id' => $shop->id, 'code' => 'SAVE20', 'used_count' => 4,
            'starts_at' => '2026-01-01 00:00:00', 'expires_at' => '2027-12-31 18:30:00']);

        $this->putJson('/api/admin/coupons/'.$coupon->id, $this->payload(' save20 ') + ['id' => 999])
            ->assertOk()->assertJsonPath('data.code', 'SAVE20')->assertJsonPath('data.value', 20)
            ->assertJsonPath('data.shop_id', $shop->id)->assertJsonPath('data.usage_limit', 50)
            ->assertJsonPath('data.expires_at', '2027-12-31T18:30:00.000000Z');

        $this->assertSame(4, (int) $coupon->fresh()->used_count);
        $this->assertSame('2026-01-01 00:00:00', $coupon->fresh()->starts_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('coupons', 1);
        $this->assertDatabaseCount('coupon_usages', 0);
    }

    public function test_update_to_another_code_cannot_bypass_uniqueness_using_body_ids(): void
    {
        $this->admin();
        $coupon = Coupon::factory()->create(['code' => 'SAVE10', 'value' => 10]);
        $other = Coupon::factory()->create(['code' => 'SAVE20']);
        $attempts = 0;
        Coupon::updating(function () use (&$attempts): void {
            $attempts++;
        });

        $this->putJson('/api/admin/coupons/'.$coupon->id, $this->payload('save20') + [
            'id' => $other->id, 'coupon_id' => $other->id, 'ignore' => $other->id,
        ])->assertUnprocessable()->assertJsonPath('errors.code.0', 'The coupon code has already been taken.');

        $this->assertSame(0, $attempts);
        $this->assertSame('SAVE10', $coupon->fresh()->code);
        $this->assertSame('10.00', $coupon->fresh()->value);
        $this->assertDatabaseCount('coupons', 2);
    }

    #[TestWith(['a', 'a'])]
    #[TestWith(['a', 'b'])]
    #[TestWith(['a', 'platform'])]
    #[TestWith(['platform', 'a'])]
    #[TestWith(['platform', 'platform'])]
    public function test_codes_are_global_across_all_shop_and_platform_scopes(string $existingScope, string $newScope): void
    {
        $this->admin();
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $scopes = ['a' => $shopA->id, 'b' => $shopB->id, 'platform' => null];
        $coupon = Coupon::factory()->create(['code' => 'SAVE20', 'shop_id' => $scopes[$existingScope]]);

        $this->postJson('/api/admin/coupons', $this->payload() + ['shop_id' => $scopes[$newScope]])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->assertDatabaseCount('coupons', 1);
        $this->assertSame($scopes[$existingScope], $coupon->fresh()->shop_id);
    }

    #[TestWith(['disabled'])]
    #[TestWith(['expired'])]
    public function test_disabled_and_expired_codes_remain_reserved(string $state): void
    {
        $this->freezeTime();
        $this->admin();
        $factory = $state === 'disabled' ? Coupon::factory()->inactive() : Coupon::factory()->expired();
        $factory->create(['code' => 'SAVE20']);

        $this->postJson('/api/admin/coupons', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->assertDatabaseCount('coupons', 1);
    }

    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith([['SAVE20']])]
    #[TestWith(['AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'])]
    public function test_empty_non_string_and_overlong_codes_return_422(mixed $code): void
    {
        $this->admin();

        $this->postJson('/api/admin/coupons', $this->payload($code))->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->assertDatabaseCount('coupons', 0);
    }

    public function test_fifty_character_and_internal_space_codes_remain_supported(): void
    {
        $this->admin();
        $this->postJson('/api/admin/coupons', $this->payload(str_repeat('A', 50)))->assertCreated();
        $this->postJson('/api/admin/coupons', $this->payload('SAVE NOW'))->assertCreated()->assertJsonPath('data.code', 'SAVE NOW');
        $this->assertDatabaseCount('coupons', 2);
    }

    public function test_hard_deleted_codes_can_be_reused_according_to_the_existing_schema(): void
    {
        $this->admin();
        $coupon = Coupon::factory()->create(['code' => 'SAVE20']);
        $this->deleteJson('/api/admin/coupons/'.$coupon->id)->assertOk();
        $this->getJson('/api/admin/coupons/'.$coupon->id)->assertNotFound();

        $this->postJson('/api/admin/coupons', $this->payload())->assertCreated();

        $this->assertDatabaseCount('coupons', 1);
    }

    public function test_insert_race_after_request_validation_returns_safe_422(): void
    {
        $this->admin();
        $winningRow = Coupon::factory()->raw(['code' => 'RACE20', 'value' => 7]);
        Coupon::creating(function (Coupon $coupon) use ($winningRow): void {
            if ($coupon->code === 'RACE20') {
                DB::table('coupons')->insert($winningRow);
            }
        });
        config(['app.debug' => true]);

        $response = $this->postJson('/api/admin/coupons', $this->payload('RACE20'))
            ->assertUnprocessable()->assertJsonPath('errors.code.0', 'The coupon code has already been taken.')
            ->assertJsonMissingPath('exception')->assertJsonMissingPath('trace');

        $this->assertDatabaseCount('coupons', 1);
        $this->assertSame('7.00', Coupon::sole()->value);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('coupons_code_unique', $response->getContent());
    }

    public function test_update_race_after_validation_keeps_original_coupon_unchanged(): void
    {
        $this->admin();
        $coupon = Coupon::factory()->create(['code' => 'SAVE10', 'value' => 10]);
        $winningRow = Coupon::factory()->raw(['code' => 'RACE20']);
        Coupon::updating(function () use ($winningRow): void {
            DB::table('coupons')->insert($winningRow);
        });

        $this->putJson('/api/admin/coupons/'.$coupon->id, $this->payload('RACE20'))
            ->assertUnprocessable()->assertJsonPath('errors.code.0', 'The coupon code has already been taken.');

        $this->assertSame('SAVE10', $coupon->fresh()->code);
        $this->assertSame('10.00', $coupon->fresh()->value);
        $this->assertDatabaseCount('coupons', 2);
    }

    public function test_unrelated_database_failures_are_not_mislabeled_as_code_conflicts(): void
    {
        try {
            app(CouponService::class)->create($this->payload('VALID20') + ['shop_id' => 999999]);
            $this->fail('Expected a foreign-key failure.');
        } catch (QueryException $exception) {
            $this->assertDatabaseCount('coupons', 0);
        }
    }

    public function test_editor_loads_a_coupon_outside_the_first_list_page_with_all_settings(): void
    {
        $this->admin();
        $coupon = Coupon::factory()->create(['code' => 'SAVE20', 'usage_limit' => 12, 'is_active' => false,
            'starts_at' => '2026-01-01 00:00:00', 'expires_at' => '2027-12-31 18:30:00']);
        Coupon::factory()->count(16)->create();

        $this->getJson('/api/admin/coupons/'.$coupon->id)->assertOk()->assertJsonPath('data.id', $coupon->id)
            ->assertJsonPath('data.usage_limit', 12)->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.starts_at', '2026-01-01T00:00:00.000000Z')
            ->assertJsonPath('data.expires_at', '2027-12-31T18:30:00.000000Z');
    }

    public function test_editor_endpoint_requires_authentication_and_platform_admin_role(): void
    {
        $coupon = Coupon::factory()->create();
        $url = '/api/admin/coupons/'.$coupon->id;
        $this->getJson($url)->assertUnauthorized();
        $owner = User::factory()->create();
        $owner->shops()->attach(Shop::factory()->create(), ['role_in_shop' => 'owner', 'status' => 'active']);
        $this->actingAs($owner, 'sanctum')->getJson($url)->assertForbidden();
        $this->postJson('/api/admin/coupons', $this->payload())->assertForbidden();
    }

    public function test_public_coupon_validation_keeps_the_existing_metadata_boundary(): void
    {
        Coupon::factory()->create(['code' => 'SAVE20']);
        $variant = Product::factory()->withVariant(100, 10)->create()->variants()->sole();
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/cart', ['product_variant_id' => $variant->id, 'quantity' => 1])->assertCreated();

        $this->postJson('/api/coupons/validate', ['code' => ' save20 '])->assertOk()
            ->assertJsonPath('data.code', 'SAVE20')->assertJsonMissingPath('data.usage_limit')
            ->assertJsonMissingPath('data.starts_at')->assertJsonMissingPath('data.expires_at');
    }
}
