<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_id', 'shop_order_id', 'product_id', 'product_variant_id', 'shop_id', 'product_name', 'variant_label', 'sku', 'image_path', 'unit_price', 'unit_cost', 'quantity', 'line_total'])]
class OrderItem extends Model
{
    protected $hidden = ['unit_cost'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function shopOrder()
    {
        return $this->belongsTo(ShopOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
