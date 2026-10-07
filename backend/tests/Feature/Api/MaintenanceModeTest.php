<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_mode_blocks_storefront_api_but_keeps_admin_access(): void
    {
        Setting::set('maintenanceMode', '1');

        $this->getJson('/api/catalog/products')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '300');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/catalog/products')
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/settings')
            ->assertOk();
    }

    public function test_authentication_endpoints_remain_available_during_maintenance(): void
    {
        Setting::set('maintenanceMode', '1');

        $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.test',
            'password' => 'invalid-password',
        ])->assertStatus(422);
    }
}
