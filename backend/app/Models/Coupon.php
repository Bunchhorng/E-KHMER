<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['shop_id', 'code', 'type', 'value', 'min_order_amount', 'max_discount_amount', 'usage_limit', 'per_user_limit', 'used_count', 'starts_at', 'expires_at', 'is_active'])]
class Coupon extends Model
{
    use HasFactory;

    public static function normalizeCode(string $code): string
    {
        return Str::upper(trim($code));
    }

    public static function duplicateCodeMessage(): string
    {
        return __('validation.unique', ['attribute' => 'coupon code']);
    }

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function scopeActive($query, $now = null)
    {
        $now ??= now();

        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now));
    }

    public function getIsValidAttribute(): bool
    {
        return $this->is_active
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && ($this->starts_at === null || ! $this->starts_at->isFuture())
            && ($this->usage_limit === null || $this->used_count < $this->usage_limit);
    }
}
