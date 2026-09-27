<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id === null ? null : (int) $this->shop_id,
            'shop' => $this->relationLoaded('shop') && $this->shop !== null
                ? [
                    'id' => (int) $this->shop->id,
                    'name' => $this->shop->name,
                    'slug' => $this->shop->slug,
                ]
                : null,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'price' => (float) $this->price,
            'estimated_days_min' => $this->estimated_days_min,
            'estimated_days_max' => $this->estimated_days_max,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
