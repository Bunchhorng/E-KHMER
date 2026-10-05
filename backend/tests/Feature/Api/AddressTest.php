<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Jane Doe',
            'phone' => '+1 (415) 555-0100',
            'address_line1' => '1 Market Street',
            'city' => 'San Francisco',
            'state' => 'CA',
            'postal_code' => '94105',
            'country' => 'US',
        ], $overrides);
    }

    public function test_guest_cannot_reach_address_endpoints(): void
    {
        $address = Address::factory()->create();

        $this->getJson('/api/addresses')->assertStatus(401);
        $this->postJson('/api/addresses', $this->payload())->assertStatus(401);
        $this->putJson("/api/addresses/{$address->id}", $this->payload())->assertStatus(401);
        $this->deleteJson("/api/addresses/{$address->id}")->assertStatus(401);
        $this->postJson("/api/addresses/{$address->id}/default")->assertStatus(401);
    }

    public function test_list_returns_only_the_owners_addresses_with_the_default_first(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Address::factory()->for($user)->create(['is_default' => false, 'city' => 'Oakland']);
        $default = Address::factory()->for($user)->create(['is_default' => true, 'city' => 'Berkeley']);
        Address::factory()->for($other)->create(['is_default' => true, 'city' => 'Elsewhere']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/addresses')->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $default->id)
            ->assertJsonPath('data.0.is_default', true)
            ->assertJsonPath('data.1.city', 'Oakland');

        $response->assertJsonMissing(['city' => 'Elsewhere']);
    }

    public function test_first_address_becomes_the_default_even_when_false_is_sent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/addresses', $this->payload(['is_default' => false]))
            ->assertStatus(201)
            ->assertJsonPath('data.is_default', true);
    }

    public function test_second_address_is_not_default_unless_requested(): void
    {
        $user = User::factory()->create();
        $first = Address::factory()->for($user)->create(['is_default' => true]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/addresses', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.is_default', false);

        $this->assertTrue($first->fresh()->is_default);
    }

    public function test_creating_an_address_as_default_demotes_the_previous_one(): void
    {
        $user = User::factory()->create();
        $first = Address::factory()->for($user)->create(['is_default' => true]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/addresses', $this->payload(['is_default' => true]))
            ->assertStatus(201)
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
    }

    public function test_update_persists_changes(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create(['is_default' => true, 'city' => 'Oakland']);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/addresses/{$address->id}", $this->payload(['city' => 'San Jose']))
            ->assertOk()
            ->assertJsonPath('data.city', 'San Jose')
            ->assertJsonPath('data.is_default', true);

        $this->assertSame('San Jose', $address->fresh()->city);
    }

    public function test_update_without_is_default_keeps_the_current_default_flag(): void
    {
        $user = User::factory()->create();
        $default = Address::factory()->for($user)->create(['is_default' => true]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/addresses/{$default->id}", $this->payload(['city' => 'Reno']))
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertTrue($default->fresh()->is_default);
    }

    public function test_promoting_another_address_demotes_the_current_default(): void
    {
        $user = User::factory()->create();
        $default = Address::factory()->for($user)->create(['is_default' => true]);
        $other = Address::factory()->for($user)->create(['is_default' => false]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/addresses/{$other->id}", $this->payload(['is_default' => true]))
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($default->fresh()->is_default);
        $this->assertTrue($other->fresh()->is_default);
    }

    public function test_demoting_the_only_default_promotes_another_address(): void
    {
        $user = User::factory()->create();
        $default = Address::factory()->for($user)->create(['is_default' => true]);
        $other = Address::factory()->for($user)->create(['is_default' => false]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/addresses/{$default->id}", $this->payload(['is_default' => false]))
            ->assertOk()
            ->assertJsonPath('data.is_default', false);

        $this->assertTrue($other->fresh()->is_default);
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
    }

    public function test_deleting_the_default_promotes_a_replacement(): void
    {
        $user = User::factory()->create();
        $default = Address::factory()->for($user)->create(['is_default' => true]);
        $other = Address::factory()->for($user)->create(['is_default' => false]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/addresses/{$default->id}")
            ->assertOk();

        $this->assertDatabaseMissing('addresses', ['id' => $default->id]);
        $this->assertTrue($other->fresh()->is_default);
    }

    public function test_deleting_the_only_default_address_leaves_no_default_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create(['is_default' => true]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/addresses/{$address->id}")
            ->assertOk();

        $this->assertSame(0, $user->addresses()->count());
        $this->assertSame(0, $user->addresses()->where('is_default', true)->count());
    }

    public function test_set_default_moves_the_flag(): void
    {
        $user = User::factory()->create();
        $first = Address::factory()->for($user)->create(['is_default' => true]);
        $second = Address::factory()->for($user)->create(['is_default' => false]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/addresses/{$second->id}/default")
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->fresh()->is_default);
    }

    public function test_user_cannot_touch_another_users_address(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();
        $address = Address::factory()->for($victim)->create(['is_default' => true]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/addresses/{$address->id}", $this->payload(['city' => 'Hacked']))
            ->assertStatus(404);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/addresses/{$address->id}")
            ->assertStatus(404);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/addresses/{$address->id}/default")
            ->assertStatus(404);

        $address->refresh();
        $this->assertNotSame('Hacked', $address->city);
        $this->assertTrue($address->is_default);
        $this->assertSame(1, $victim->addresses()->count());
    }

    public function test_create_validates_required_fields_and_column_limits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/addresses', ['full_name' => 'Jane Doe'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['address_line1', 'city', 'state', 'postal_code']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/addresses', $this->payload(['city' => str_repeat('a', 256)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['city']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/addresses', $this->payload(['postal_code' => str_repeat('9', 21)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['postal_code']);

        $this->assertSame(0, $user->addresses()->count());
    }

    public function test_create_ignores_a_supplied_user_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/addresses', $this->payload(['user_id' => 9999]))
            ->assertStatus(201);

        $this->assertNull($user->addresses()->where('user_id', 9999)->first());
        $this->assertSame($user->id, $user->addresses()->sole()->user_id);
    }
}
