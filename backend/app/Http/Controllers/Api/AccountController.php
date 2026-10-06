<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\UserResource;
use App\Services\MediaUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user()->loadCount([
            'orders',
            'reviews',
        ]);

        $wishlistCount = $user->wishlist?->items()->count() ?? 0;

        return [
            'user' => new UserResource($user),
            'orders_count' => $user->orders_count,
            'reviews_count' => $user->reviews_count,
            'wishlist_count' => $wishlistCount,
        ];
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();

        $user->update([
            'name' => $request->name,
            'phone' => $request->phone,
            'newsletter' => $request->boolean('newsletter'),
        ]);

        return new UserResource($user);
    }

    public function updateAvatar(UpdateAvatarRequest $request, MediaUploadService $mediaService)
    {
        $user = $request->user();

        $url = $mediaService->storeImage($request->file('image'), 'avatars');

        $mediaService->deleteImage($user->avatar);

        $user->update(['avatar' => $url]);

        return new UserResource($user);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => $request->password]);

        if ($user->currentAccessToken() !== null) {
            $user->tokens()
                ->where('id', '!=', $user->currentAccessToken()->id)
                ->delete();
        }

        return response()->json(['data' => ['message' => 'Password updated successfully.']]);
    }

    public function reviews(Request $request)
    {
        $reviews = $request->user()
            ->reviews()
            ->with(['product', 'product.images'])
            ->latest()
            ->get();

        return ReviewResource::collection($reviews);
    }

    public function notifications(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(50)
            ->get();

        return [
            'data' => $notifications->map(fn ($n) => [
                'id' => $n->id,
                'type' => $this->friendlyType($n->type),
                'title' => $n->data['title'] ?? null,
                'message' => $n->data['message'] ?? null,
                'read_at' => $n->read_at?->toISOString(),
                'created_at' => $n->created_at?->toISOString(),
            ])->values(),
        ];
    }

    private function friendlyType(string $notificationClass): string
    {
        return match (true) {
            str_contains($notificationClass, 'Order') => 'order',
            str_contains($notificationClass, 'Promo') => 'promo',
            default => 'system',
        };
    }

    public function markRead(Request $request, $notificationId)
    {
        $user = $request->user();

        if ($notificationId === 'all') {
            $user->unreadNotifications()->update(['read_at' => now()]);

            return response()->json(['data' => ['message' => 'All notifications marked as read.']]);
        }

        $notification = $user->notifications()->whereKey($notificationId)->first();

        if ($notification === null) {
            abort(404, 'Notification not found.');
        }

        $notification->markAsRead();

        return response()->json(['data' => ['message' => 'Notification marked as read.']]);
    }

    public function destroyNotification(Request $request, string $notificationId)
    {
        $notification = $request->user()->notifications()->whereKey($notificationId)->first();

        if ($notification === null) {
            abort(404, 'Notification not found.');
        }

        $notification->delete();

        return response()->noContent();
    }
}
