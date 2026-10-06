<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Shop extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'name', 'slug', 'code', 'branch_type', 'description', 'logo', 'banner',
        'phone', 'email', 'address_line', 'mall', 'city', 'province',
        'postal_code', 'country', 'latitude', 'longitude',
        'status', 'rejection_reason', 'is_default', 'commission_rate',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'commission_rate' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'shop_users')
            ->withPivot(['role_in_shop', 'status'])
            ->withTimestamps();
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shippingMethods()
    {
        return $this->hasMany(ShippingMethod::class);
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->trashed();
    }

    /**
     * Does the given user hold any membership (with a non-suspended status)
     * on this branch?
     */
    public function hasStaff(User $user): bool
    {
        return $this->users()
            ->wherePivot('status', 'active')
            ->whereKey($user->getKey())
            ->exists();
    }

    /**
     * Does the given user hold a management role (owner|manager) here?
     */
    public function userIsManager(User $user): bool
    {
        return $this->users()
            ->wherePivot('status', 'active')
            ->wherePivotIn('role_in_shop', ['owner', 'manager'])
            ->whereKey($user->getKey())
            ->exists();
    }

    protected static function booted(): void
    {
        static::creating(function (Shop $shop) {
            if (empty($shop->slug)) {
                $shop->slug = Str::slug($shop->name ?: $shop->code);
            }
            if (empty($shop->code)) {
                $shop->code = strtoupper(Str::slug($shop->name ?: $shop->slug, ''));
            }
            if (empty($shop->country)) {
                $shop->country = 'KH';
            }
        });
    }
}
