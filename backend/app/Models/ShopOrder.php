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

    public function codCollection(): HasOne
    {
        return $this->hasOne(PaymentTransaction::class)->where('type', 'collection')->where('status', 'success');
    }

    public function codPaymentStatus(): string
    {
        if ($this->codCollection === null) {
            return $this->status === Order::STATUS_CANCELLED ? 'cancelled' : Order::PAYMENT_UNPAID;
        }
        $refunded = PaymentRefund::whereHas('returnRecord', fn ($query) => $query->whereHas('item', fn ($items) => $items->where('shop_order_id', $this->id)))->sum('amount');

        return (float) $refunded > 0
            ? ((int) round((float) $refunded * 100) >= (int) round((float) $this->total * 100) ? 'refunded' : 'partially_refunded')
            : Order::PAYMENT_PAID;
    }

    public function canCollectCod(): bool
    {
        return $this->order?->payment?->method === 'cod'
            && $this->order->payment->status === Payment::STATUS_PENDING
            && in_array($this->order->payment_status, [Order::PAYMENT_UNPAID, 'partial'], true)
            && ! in_array($this->order->status, ['pending', 'cancelled', 'refunded'], true)
            && $this->status === Order::STATUS_DELIVERED
            && $this->shipment?->status === Shipment::STATUS_DELIVERED
            && $this->shipment->delivered_at !== null
            && $this->codCollection === null
            && (float) $this->total >= 0;
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
