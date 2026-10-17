<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicShopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'description' => $this->description, 'logo' => $this->logo, 'banner' => $this->banner,
            'phone' => $this->phone, 'email' => $this->email,
            'address_line' => $this->address_line, 'city' => $this->city,
            'province' => $this->province, 'country' => $this->country,
            'products_count' => (int) ($this->products_count ?? 0),
            'rating_avg' => round((float) ($this->reviews_avg_rating ?? 0), 2),
            'rating_count' => (int) ($this->reviews_count ?? 0),
        ];
    }
}
