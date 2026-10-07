<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'managed_shops' => $this->whenLoaded('managedShops', fn () => $this->managedShops->map(fn ($shop) => [
                'id' => $shop->id, 'name' => $shop->name, 'slug' => $shop->slug, 'role' => $shop->pivot->role_in_shop,
            ])->values()),
            'avatar' => $this->avatar,
            'phone' => $this->phone,
            'newsletter' => (bool) $this->newsletter,
            'email_verified' => $this->hasVerifiedEmail() ? true : false,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
