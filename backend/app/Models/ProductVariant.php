<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['product_id', 'image_id', 'shop_id', 'name', 'sku', 'price', 'compare_at_price', 'is_default', 'is_active'])]
class ProductVariant extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function image()
    {
        return $this->belongsTo(ProductImage::class, 'image_id');
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }

    public function attributeValues()
    {
        return $this->hasMany(VariantAttributeValue::class, 'product_variant_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }
}