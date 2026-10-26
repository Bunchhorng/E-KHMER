<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    protected $guarded = [];

    public function returnRecord(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'order_return_id');
    }

    public function bakongPayment(): BelongsTo
    {
        return $this->belongsTo(BakongPayment::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
