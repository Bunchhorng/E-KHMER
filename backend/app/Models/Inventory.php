<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['product_variant_id', 'shop_id', 'quantity', 'reserved_quantity', 'low_stock_threshold', 'sold_count', 'low_stock_notified_at'])]
class Inventory extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $inventory): void {
            if ($inventory->low_stock_threshold === null) {
                $inventory->low_stock_threshold = (int) Setting::get('lowStockThreshold', 5);
            }
        });
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max($this->quantity - $this->reserved_quantity, 0);
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->available_quantity <= $this->low_stock_threshold;
    }
}
