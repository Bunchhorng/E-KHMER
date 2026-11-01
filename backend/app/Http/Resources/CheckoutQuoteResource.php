<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckoutQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $quote = $this->resource;
        $quote['coupon'] = $quote['coupon'] === null ? null : (new CouponResource($quote['coupon']))->additional([
            'subtotal' => $quote['subtotal'], 'eligible_subtotal' => $quote['eligible_subtotal'],
            'discount_amount' => $quote['discount_amount'], 'raw_discount' => $quote['raw_discount'],
        ])->resolve($request);

        return $quote;
    }
}
