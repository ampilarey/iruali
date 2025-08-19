<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'date_of_birth',
        'gender',
        'profile_picture',
        'is_seller',
        'seller_approved',
        'seller_approved_at',
        'email_verified_at',
        'phone_verified_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_enabled',
        'last_login_at',
        'last_login_ip',
        'login_count',
        'is_active',
        'banned_until',
        'banned_reason',
        'loyalty_points',
        'referral_code', 'referred_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'seller_approved_at' => 'datetime',
        'last_login_at' => 'datetime',
        'banned_until' => 'datetime',
        'is_seller' => 'boolean',
        'seller_approved' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'is_active' => 'boolean',
        'loyalty_points' => 'integer',
        'referred_by' => 'integer',
    ];

    /**
     * Get the roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Get the user's cart.
     */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /**
     * Get the user's wishlist.
     */
    public function wishlist(): HasOne
    {
        return $this->hasOne(Wishlist::class);
    }

    /**
     * Get the user's orders.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the user's products (if seller).
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the user's reviews.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Get the user's notifications.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get all carts for the user.
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Check if user has all of the given roles.
     */
    public function hasAllRoles(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->count() === count($roles);
    }

    /**
     * Check if user is a seller.
     */
    public function isSeller(): bool
    {
        return $this->is_seller && $this->seller_approved;
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if user is banned.
     */
    public function isBanned(): bool
    {
        return $this->banned_until && $this->banned_until->isFuture();
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->is_active && !$this->isBanned();
    }

    /**
     * Check if email is verified.
     */
    public function isEmailVerified(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Check if phone is verified.
     */
    public function isPhoneVerified(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    /**
     * Check if 2FA is enabled.
     */
    public function isTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && !empty($this->two_factor_secret);
    }

    /**
     * Enable 2FA for user.
     */
    public function enableTwoFactor(): void
    {
        $this->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => encrypt(random_bytes(32)),
            'two_factor_recovery_codes' => encrypt(json_encode($this->generateRecoveryCodes()))
        ]);
    }

    /**
     * Disable 2FA for user.
     */
    public function disableTwoFactor(): void
    {
        $this->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null
        ]);
    }

    /**
     * Generate recovery codes for 2FA.
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(substr(md5(uniqid()), 0, 8));
        }
        return $codes;
    }

    /**
     * Get recovery codes.
     */
    public function getRecoveryCodes(): array
    {
        if (!$this->two_factor_recovery_codes) {
            return [];
        }
        return json_decode(decrypt($this->two_factor_recovery_codes), true);
    }

    /**
     * Update login tracking.
     */
    public function updateLoginTracking(string $ip): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'login_count' => $this->login_count + 1
        ]);
    }

    /**
     * Ban user.
     */
    public function ban(string $reason, ?\DateTime $until = null): void
    {
        $this->update([
            'banned_until' => $until ?? now()->addYear(),
            'banned_reason' => $reason
        ]);
    }

    /**
     * Unban user.
     */
    public function unban(): void
    {
        $this->update([
            'banned_until' => null,
            'banned_reason' => null
        ]);
    }

    public function referredBy()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }
    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    /**
     * Scope to include only soft deleted users
     */
    public function scopeOnlyTrashed($query)
    {
        return $query->onlyTrashed();
    }

    /**
     * Scope to include both active and soft deleted users
     */
    public function scopeWithTrashed($query)
    {
        return $query->withTrashed();
    }

    /**
     * Check if user is soft deleted
     */
    public function isTrashed(): bool
    {
        return $this->trashed();
    }

    /**
     * Restore a soft deleted user
     */
    public function restoreUser(): bool
    {
        return $this->restore();
    }

    /**
     * Force delete a user (permanently remove)
     */
    public function forceDeleteUser(): bool
    {
        return $this->forceDelete();
    }
}
