<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subtotal = $this->additional['subtotal'] ?? null;
        $discountAmount = null;
        $message = '';

        if ($subtotal !== null) {
            $discountAmount = $this->computeDiscount((float) $subtotal);
            $message = $this->is_valid
                ? ($this->type === 'percentage' ? "{$this->value}% off" : '$' . number_format((float) $this->value, 2, '.', '') . ' off')
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
            'message' => $message,
        ];
    }

    protected function computeDiscount(float $subtotal): float
    {
        if ($this->type === 'percentage') {
            $discount = round($subtotal * (float) $this->value / 100, 2);
            $max = $this->max_discount_amount !== null ? (float) $this->max_discount_amount : $subtotal;
            return min($discount, $max);
        }

        return min((float) $this->value, $subtotal);
    }
}
