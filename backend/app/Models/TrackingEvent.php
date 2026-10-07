<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingEvent extends Model
{
    public $timestamps = false;

    protected $table = 'tracking_events';

    protected $fillable = ['order_id', 'shop_order_id', 'from_status', 'status', 'description', 'changed_by', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function shopOrder(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class);
    }
}
