<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Lifetime applied to a token when the user ticks "remember me" on login.
     * Tokens issued without it keep the framework default (no expiry).
     */
    private const REMEMBERED_TOKEN_LIFETIME_DAYS = 30;

    public function __construct(private CartService $carts)
    {
    }

    public function register(RegisterRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            // `role` is not mass assignable; the model defaults it to customer.
            return User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'newsletter' => $request->boolean('newsletter'),
            ]);
        });

        // Email verification and guest-cart merging are non-essential to account
        // creation. If any of them throw, the user is already persisted and should
        // still be able to complete registration rather than seeing a generic failure.
        try {
            if (!$user->hasVerifiedEmail()) {
                // Rendering the mail costs several seconds, which used to push the
                // whole response past the client's request timeout: the browser gave
                // up while the account was already created, and the retry came back
                // as "email already taken". Sending it after the response is flushed
                // keeps registration fast without needing a queue worker.
                Bus::dispatchAfterResponse(function () use ($user): void {
                    try {
                        $user->sendEmailVerificationNotification();
                    } catch (\Throwable $e) {
                        Log::warning('Verification email could not be sent after registration.', [
                            'user_id' => $user->id,
                            'exception' => $e->getMessage(),
                        ]);
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::warning('Registration could not schedule the verification email.', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);
        }

        try {
            $this->carts->mergeGuestIntoUser($user, $request->header('X-Session-Id'));
        } catch (\Throwable $e) {
            Log::warning('Registration succeeded but guest cart could not be merged.', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        try {
            $valid = Auth::guard('web')->validate([
                'email' => $request->email,
                'password' => $request->password,
            ]);
        } catch (\Throwable $e) {
            // A stored password hash that is not bcrypt (e.g. a legacy plaintext
            // row) makes the hasher throw. Treat it as invalid credentials rather
            // than surfacing a 500 with an internal hashing error.
            Log::info('Login rejected for malformed stored password hash.', [
                'email' => $request->email,
                'exception' => $e->getMessage(),
            ]);
            $valid = false;
        }

        if (!$valid) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        $this->carts->mergeGuestIntoUser($user, $request->header('X-Session-Id'));

        $expiresAt = $request->boolean('remember')
            ? now()->addDays(self::REMEMBERED_TOKEN_LIFETIME_DAYS)
            : null;

        $token = $user->createToken('api', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => ['message' => 'Logged out successfully.']]);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    /**
     * Verify a user's email address. Guarded by the `signed` middleware;
     * the signed URL is generated by the VerifyEmail notification.
     */
    public function verify(Request $request)
    {
        $user = User::findOrFail((int) $request->route('id'));

        if (!hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            abort(403, 'Invalid email verification link.');
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $message = 'Email verified successfully.';
        } else {
            $message = 'Your email is already verified.';
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => ['message' => $message]]);
        }

        $url = rtrim((string) config('app.frontend_url'), '/').'/auth/verify-email?verified=1';

        return redirect()->away($url);
    }

    /**
     * Resend the email verification notification to the authenticated user.
     */
    public function sendVerificationEmail(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['data' => ['message' => 'Your email is already verified.']]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['data' => ['message' => 'Verification email sent.']]);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        Password::broker('users')->sendResetLink($request->only('email'));

        return response()->json([
            'data' => ['message' => 'If an account exists for that email, a password reset link has been sent.'],
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [$status],
            ]);
        }

        return response()->json([
            'data' => ['message' => 'Password reset successfully.'],
        ]);
    }
}
