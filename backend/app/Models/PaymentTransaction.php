<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['payment_id', 'shop_order_id', 'type', 'status', 'amount', 'reference', 'meta'])]
class PaymentTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function shopOrder()
    {
        return $this->belongsTo(ShopOrder::class);
    }
}
