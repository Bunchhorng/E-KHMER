<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_reach_account_endpoints(): void
    {
        $this->getJson('/api/account/dashboard')->assertStatus(401);
        $this->putJson('/api/account/profile', ['name' => 'Jane'])->assertStatus(401);
        $this->postJson('/api/account/password', [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(401);
        $this->getJson('/api/account/notifications')->assertStatus(401);
        $this->getJson('/api/account/reviews')->assertStatus(401);
    }

    public function test_dashboard_returns_the_user_with_counts_and_never_the_password(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe']);
        Order::factory()->count(2)->create(['user_id' => $user->id]);

        $wishlist = Wishlist::create(['user_id' => $user->id]);
        $products = Product::factory()->count(3)->create();

        foreach ($products as $product) {
            WishlistItem::create([
                'wishlist_id' => $wishlist->id,
                'product_id' => $product->id,
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/account/dashboard')
            ->assertOk()
            ->assertJsonPath('user.name', 'Jane Doe')
            ->assertJsonPath('orders_count', 2)
            ->assertJsonPath('reviews_count', 0)
            ->assertJsonPath('wishlist_count', 3);

        $response->assertJsonMissingPath('user.password');
    }

    public function test_update_profile_persists_the_allowed_fields(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'phone' => null]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/account/profile', [
                'name' => 'New Name',
                'phone' => '+1 (415) 555-0111',
                'newsletter' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.phone', '+1 (415) 555-0111')
            ->assertJsonPath('data.newsletter', true);

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertTrue($user->newsletter);
    }

    public function test_update_profile_cannot_escalate_role_or_change_the_email(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/account/profile', [
                'name' => 'Jane Doe',
                'role' => User::ROLE_ADMIN,
                'email' => 'attacker@evil.test',
                'email_verified_at' => now(),
                'password' => 'hijacked123',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertSame(User::ROLE_CUSTOMER, $user->role);
        $this->assertSame('jane@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_update_profile_validates_input(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/account/profile', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/account/profile', ['name' => 'Jane', 'phone' => str_repeat('9', 31)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_change_password_requires_the_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => 'originalpass']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/account/password', [
                'current_password' => 'wrongpass',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('originalpass', $user->fresh()->password));
    }

    public function test_change_password_revokes_other_sessions_but_keeps_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'originalpass']);
        $this->createToken($user, 'other-device');

        $currentToken = $this->createToken($user, 'current-device');

        $this->withToken($currentToken)
            ->postJson('/api/account/password', [
                'current_password' => 'originalpass',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertOk()
            ->assertJsonPath('data.message', 'Password updated successfully.');

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));

        $token = $user->fresh()->tokens()->first();
        $this->assertNotNull($token, 'The current session token must survive a password change.');
        $this->assertSame('current-device', $token->name);
    }

    public function test_change_password_validates_the_new_password(): void
    {
        $user = User::factory()->create(['password' => 'originalpass']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/account/password', [
                'current_password' => 'originalpass',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/account/password', [
                'current_password' => 'originalpass',
                'password' => 'newpassword123',
                'password_confirmation' => 'different',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_notifications_are_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = $this->createOrderNotification($user);
        $theirs = $this->createOrderNotification($other);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/account/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/account/notifications/{$theirs->id}/read")
            ->assertStatus(404);

        $this->assertNull($theirs->fresh()->read_at);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/account/notifications/{$mine->id}/read")
            ->assertOk();

        $this->assertNotNull($mine->fresh()->read_at);
    }

    public function test_mark_all_notifications_read_only_touches_the_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $first = $this->createOrderNotification($user);
        $second = $this->createOrderNotification($user);
        $theirs = $this->createOrderNotification($other);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/account/notifications/all/read')
            ->assertOk();

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);
    }

    private function createToken(User $user, string $name): string
    {
        return $user->createToken($name)->plainTextToken;
    }

    private function createOrderNotification(User $user)
    {
        $order = Order::factory()->create(['user_id' => $user->id]);
        $order->user->notify(new OrderPlacedNotification($order));

        return $user->notifications()->latest()->first();
    }
}