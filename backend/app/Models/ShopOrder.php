<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['order_id', 'shop_id', 'shop_order_number', 'status', 'subtotal', 'discount_amount', 'tax_amount', 'shipping_amount', 'total'])]
class ShopOrder extends Model
{
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function allowedTransitions(): array
    {
        if (! $this->relationLoaded('order') || $this->order === null
            || in_array($this->order->status, [Order::STATUS_PENDING, Order::STATUS_CANCELLED, Order::STATUS_REFUNDED], true)
            || ($this->relationLoaded('shipment') && $this->shipment?->status === Shipment::STATUS_RETURNED)) {
            return [];
        }

        return match ($this->status) {
            Order::STATUS_PENDING => [Order::STATUS_CONFIRMED],
            Order::STATUS_CONFIRMED => [Order::STATUS_PROCESSING],
            Order::STATUS_PROCESSING => [Order::STATUS_SHIPPED],
            Order::STATUS_SHIPPED => [Order::STATUS_DELIVERED],
            default => [],
        };
    }
}
