<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'shop_id' => $this->shop_id, 'shop_name' => $this->shop?->name,
            'incurred_on' => $this->incurred_on->toDateString(), 'category' => $this->category,
            'description' => $this->description, 'amount' => (float) $this->amount, 'currency' => $this->currency,
        ];
    }
}
