<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['category_id', 'brand_id', 'shop_id', 'name', 'slug', 'description', 'short_description', 'price', 'compare_at_price', 'cost_price', 'sku', 'barcode', 'weight', 'is_featured', 'is_active', 'rating_avg', 'rating_count', 'meta_title', 'meta_description', 'seo_keywords'])]
class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Shopper-visible scope: the product is published *and* its owning shop is
     * still active. Platform products have no shop and stay visible.
     *
     * Previously the catalog filtered on `is_active` only, so products belonging
     * to a suspended or pending shop stayed purchasable.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where(function (Builder $q) {
            $q->whereNull('shop_id')->orWhereHas('shop', fn (Builder $s) => $s->active());
        });
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function inventory()
    {
        return $this->hasManyThrough(Inventory::class, ProductVariant::class, 'product_id', 'product_variant_id', 'id', 'id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function wishlistItems()
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getCoverImageAttribute(): ?string
    {
        $image = $this->images()->where('is_cover', true)->first() ?? $this->images()->first();

        return $image?->image_path;
    }

    public function getLowestPriceAttribute(): ?string
    {
        return $this->variants()->min('price') ?? $this->price;
    }
}