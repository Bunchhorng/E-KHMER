<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'avatar', 'phone', 'newsletter'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SUPER_ADMIN = 'super_admin';

    /**
     * `role` and `email_verified_at` are intentionally absent from $fillable:
     * a request payload must never be able to promote itself to admin or mark
     * itself verified. Only trusted code (seeder, factory, artisan commands)
     * writes them, through forceFill.
     *
     * The role default still has to be applied in PHP because the column default
     * only fires when the attribute is omitted entirely.
     */
    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $user->role ??= self::ROLE_CUSTOMER;
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'newsletter' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function wishlist()
    {
        return $this->hasOne(Wishlist::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function couponsUsed()
    {
        return $this->hasMany(CouponUsage::class, 'user_id');
    }

    public function shops()
    {
        return $this->belongsToMany(Shop::class, 'shop_users')
            ->withPivot(['role_in_shop', 'status'])
            ->withTimestamps();
    }

    public function shopMemberships()
    {
        return $this->hasMany(ShopUser::class);
    }

    public function ownsShop(Shop $shop): bool
    {
        return $this->shops()
            ->where('shops.id', $shop->getKey())
            ->wherePivotIn('role_in_shop', ['owner', 'manager'])
            ->wherePivot('status', 'active')
            ->exists();
    }

    public function belongsToShop(Shop $shop): bool
    {
        return $this->shops()
            ->where('shops.id', $shop->getKey())
            ->wherePivot('status', 'active')
            ->exists();
    }
}
