<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => $this->code,
            'branch_type' => $this->branch_type,
            'description' => $this->description,
            'logo' => $this->logo,
            'banner' => $this->banner,
            'phone' => $this->phone,
            'email' => $this->email,
            'address_line' => $this->address_line,
            'mall' => $this->mall,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'is_default' => $this->is_default,
            'commission_rate' => $this->commission_rate,
            'products_count' => $this->whenCounted('products'),
            'inventories_count' => $this->whenCounted('inventories'),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
