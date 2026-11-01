<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subtotal = $this->additional['subtotal'] ?? null;
        $discountAmount = $this->additional['discount_amount'] ?? null;
        $message = '';

        if ($subtotal !== null) {
            $message = $this->is_valid
                ? ($this->type === 'percentage' ? "{$this->value}% off" : '$'.number_format((float) $this->value, 2, '.', '').' off')
                : 'This coupon is not currently valid.';
        }

        return [
            'id' => (int) $this->id,
            'shop_id' => $this->shop_id !== null ? (int) $this->shop_id : null,
            'code' => $this->code,
            'type' => $this->type,
            'value' => (float) $this->value,
            'min_order_amount' => $this->min_order_amount !== null ? (float) $this->min_order_amount : null,
            'max_discount_amount' => $this->max_discount_amount !== null ? (float) $this->max_discount_amount : null,
            'valid' => (bool) $this->is_valid,
            'discount_amount' => $discountAmount,
            'eligible_subtotal' => $this->additional['eligible_subtotal'] ?? null,
            'message' => $message,
        ];
    }
}
