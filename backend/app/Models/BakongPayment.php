<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BakongPayment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expires_at' => 'datetime', 'paid_at' => 'datetime', 'refunded_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function shopOrder()
    {
        return $this->belongsTo(ShopOrder::class);
    }
}
