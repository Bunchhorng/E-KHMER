<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['order_number', 'user_id', 'coupon_id', 'coupon_code', 'status', 'payment_status', 'subtotal', 'discount_amount', 'tax_amount', 'shipping_amount', 'total', 'currency', 'shipping_address', 'billing_address', 'email', 'phone', 'customer_name', 'note', 'placed_at'])]
class Order extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_REFUNDED = 'refunded';

    public const PAYMENT_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'placed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shopOrders()
    {
        return $this->hasMany(ShopOrder::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function couponUsages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function trackingEvents()
    {
        return $this->hasMany(TrackingEvent::class)->whereNull('shop_order_id');
    }

    public function canBeCancelled(): bool
    {
        if (! in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_PROCESSING], true)) {
            return false;
        }
        $dispatched = [self::STATUS_SHIPPED, self::STATUS_DELIVERED];
        $hasDispatchedShop = $this->relationLoaded('shopOrders')
            ? $this->shopOrders->contains(fn ($allocation) => in_array($allocation->status, $dispatched, true))
            : $this->shopOrders()->whereIn('status', $dispatched)->exists();
        $shipmentStates = [Shipment::STATUS_SHIPPED, Shipment::STATUS_IN_TRANSIT, Shipment::STATUS_DELIVERED, Shipment::STATUS_RETURNED];
        $hasDispatchedShipment = $this->relationLoaded('shipments')
            ? $this->shipments->contains(fn ($shipment) => in_array($shipment->status, $shipmentStates, true))
            : $this->shipments()->whereIn('status', $shipmentStates)->exists();

        return ! $hasDispatchedShop && ! $hasDispatchedShipment;
    }
}
