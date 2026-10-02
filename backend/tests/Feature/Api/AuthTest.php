<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_customer_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonPath('data.user.email', 'jane@example.com');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'role' => 'customer',
        ]);
    }

    public function test_register_requires_match_and_length(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422);

        $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ])->assertStatus(422);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Jane Again',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422);
    }

    public function test_login_returns_token(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'secretpass']);

        $this->postJson('/api/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'secretpass',
        ])->assertStatus(200)
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'secretpass']);

        $this->postJson('/api/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'wrongpass',
        ])->assertStatus(422);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/auth/me')->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_forgot_password_sends_reset_link_and_stores_token(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);

        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com'])
            ->assertOk()
            ->assertJsonPath('data.message', 'If an account exists for that email, a password reset link has been sent.');

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'reset@example.com']);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_does_not_reveal_whether_email_exists(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk();

        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_forgot_password_requires_valid_email(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422);
    }

    public function test_reset_password_with_valid_token_updates_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create(['email' => 'resetme@example.com', 'password' => 'oldpassword']);
        $user->createToken('mobile')->plainTextToken;

        $token = Password::broker('users')->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'resetme@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk()
            ->assertJsonPath('data.message', 'Password reset successfully.');

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'resetme@example.com']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create(['email' => 'resetme@example.com']);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'resetme@example.com',
            'token' => 'invalid-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(422);
    }

    public function test_reset_password_requires_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'resetme@example.com']);
        $token = Password::broker('users')->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'resetme@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'different',
        ])->assertStatus(422);
    }

    public function test_register_sends_email_verification_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'verify@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $user = User::where('email', 'verify@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);

        // The mail is deliberately deferred until after the response is flushed so
        // rendering it cannot push registration past the client timeout. The test
        // kernel runs the terminating callbacks for us, so it has already been sent.
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verification_link_marks_email_as_verified(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create(['email' => 'verify@example.com']);
        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user, 'sanctum')->getJson($verificationUrl)
            ->assertOk()
            ->assertJsonPath('data.message', 'Email verified successfully.');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_of_an_already_verified_email_is_idempotent(): void
    {
        $user = User::factory()->create(['email' => 'verify@example.com']);
        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->getJson($verificationUrl)
            ->assertOk()
            ->assertJsonPath('data.message', 'Your email is already verified.');
    }

    public function test_verification_rejects_an_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'verify@example.com']);
        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('wrong-email@example.com'),
        ]);

        $this->getJson($verificationUrl)->assertStatus(403);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_rejects_an_expired_link(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'verify@example.com']);
        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->subMinutes(5), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->getJson($verificationUrl)->assertStatus(403);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_unverified_user_can_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('data.message', 'Verification email sent.');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_user_cannot_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('data.message', 'Your email is already verified.');

        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    public function test_verification_link_requires_authentication_for_resend(): void
    {
        $this->postJson('/api/auth/email/verification-notification')->assertStatus(401);
    }

    public function test_register_ignores_an_attempt_to_self_assign_a_role(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_ADMIN,
        ])->assertStatus(201)
            ->assertJsonPath('data.user.role', User::ROLE_CUSTOMER);

        $this->assertDatabaseHas('users', [
            'email' => 'mallory@example.com',
            'role' => User::ROLE_CUSTOMER,
        ]);
    }

    public function test_register_never_returns_the_password_or_a_hash(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $response->assertJsonMissingPath('data.user.password');

        $this->postJson('/api/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ])->assertOk()->assertJsonMissingPath('data.user.password');

        $this->actingAs(User::where('email', 'jane@example.com')->firstOrFail(), 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonMissingPath('data.password');
    }

    public function test_email_is_normalised_on_register_and_login(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'email' => '  Jane.Doe@Example.COM ',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201)
            ->assertJsonPath('data.user.email', 'jane.doe@example.com');

        $this->assertDatabaseHas('users', ['email' => 'jane.doe@example.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'JANE.DOE@EXAMPLE.COM',
            'password' => 'password123',
        ])->assertStatus(200)
            ->assertJsonPath('data.user.email', 'jane.doe@example.com');
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'secretpass']);

        foreach (range(1, 10) as $attempt) {
            $this->postJson('/api/auth/login', [
                'email' => 'jane@example.com',
                'password' => 'wrongpass',
            ])->assertStatus(422);
        }

        $response = $this->postJson('/api/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'secretpass',
        ])->assertStatus(429);

        $response->assertHeader('Retry-After');
    }

    public function test_password_reset_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'reset@example.com']);

        foreach (range(1, 3) as $attempt) {
            $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com'])
                ->assertOk();
        }

        $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com'])
            ->assertStatus(429);
    }

    public function test_remember_me_extends_the_token_lifetime(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'secretpass']);

        $this->postJson('/api/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'secretpass',
            'remember' => true,
        ])->assertOk();

        $token = $user->fresh()->tokens()->latest()->firstOrFail();
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->isFuture());
        $this->assertGreaterThanOrEqual(29, (int) now()->diffInDays($token->expires_at));
        $this->assertLessThanOrEqual(30, (int) now()->diffInDays($token->expires_at));
    }

    public function test_login_without_remember_me_issues_a_token_without_expiry(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'secretpass']);

        $this->postJson('/api/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'secretpass',
        ])->assertOk();

        $this->assertNull($user->fresh()->tokens()->latest()->firstOrFail()->expires_at);
    }

    public function test_user_seeder_creates_a_verified_admin(): void
    {
        $this->seed(UserSeeder::class);

        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();

        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('password', $admin->password));

        $customers = User::where('role', User::ROLE_CUSTOMER)->get();
        $this->assertCount(6, $customers);
        $customers->each(fn (User $customer) => $this->assertNotNull($customer->email_verified_at));
    }

    public function test_seeded_admin_can_log_in_and_reach_admin_routes(): void
    {
        $this->seed(UserSeeder::class);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'admin@ekhmer.dev',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('data.user.role', User::ROLE_ADMIN);

        $this->withToken($login->json('data.token'))
            ->getJson('/api/admin/dashboard/overview')
            ->assertOk();
    }
}