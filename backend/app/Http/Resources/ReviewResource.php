<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'verified' => (bool) $this->verified,
            'status' => $this->status,
            'shop_id' => $this->shop_id === null ? null : (int) $this->shop_id,
            'helpful_count' => (int) $this->helpful_count,
            'created_at' => $this->created_at?->toISOString(),
            'is_featured' => (bool) $this->is_featured,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
            ]),
        ];
    }
}
