<?php

namespace App\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements HasLocalePreference
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
        'business_name',
        'business_description',
        'seller_applied_at',
        'payout_bank_name',
        'payout_account_name',
        'payout_account_number',
        'preferred_language',
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
        'referral_code', 'referred_by', 'referral_rewarded_at',
        'shop_logo', 'shop_banner', 'delivery_notes', 'ships_to_islands', 'onboarding_completed_at',
        'notification_preferences',
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
        'commission_rate' => 'decimal:2',
        'seller_applied_at' => 'datetime',
        'last_login_at' => 'datetime',
        'banned_until' => 'datetime',
        'is_seller' => 'boolean',
        'seller_approved' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'referral_rewarded_at' => 'datetime',
        'is_active' => 'boolean',
        'loyalty_points' => 'integer',
        'referred_by' => 'integer',
        'ships_to_islands' => 'boolean',
        'onboarding_completed_at' => 'datetime',
        'notification_preferences' => 'array',
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
    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class, 'seller_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(SellerPayout::class, 'seller_id');
    }

    /**
     * Commission iruali keeps from this shop's item sales, in percent.
     */
    public function effectiveCommissionRate(): float
    {
        return (float) ($this->commission_rate ?? Setting::get('default_commission_rate'));
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    /**
     * Get the user's reviews.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
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
        // Loaded once per request instead of a query per call (layouts ask several times per page)
        if (! $this->relationLoaded('roles')) {
            $this->load('roles');
        }

        return $this->roles->contains('name', $role);
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
        return $this->is_seller && $this->seller_approved && $this->status !== 'suspended';
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
        return $this->is_active && ! $this->isBanned();
    }

    /**
     * Check if email is verified.
     */
    public function isEmailVerified(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    /**
     * Check if phone is verified.
     */
    public function isPhoneVerified(): bool
    {
        return ! is_null($this->phone_verified_at);
    }

    /**
     * Check if 2FA is enabled.
     */
    public function isTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && ! empty($this->two_factor_secret);
    }

    /**
     * Enable 2FA for user.
     */
    public function enableTwoFactor(): void
    {
        $this->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => encrypt(random_bytes(32)),
            'two_factor_recovery_codes' => encrypt(json_encode($this->generateRecoveryCodes())),
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
            'two_factor_recovery_codes' => null,
        ]);
    }

    /**
     * Generate recovery codes for 2FA.
     */
    /**
     * Eight one-time recovery codes. The plain codes are shown to the user once; only hashes are stored.
     *
     * @return string[]
     */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn () => strtoupper(Str::random(5).'-'.Str::random(5)))->all();
    }

    public function setRecoveryCodes(array $plainCodes): void
    {
        $this->forceFill(['two_factor_recovery_codes' => encrypt(json_encode(array_map(fn ($c) => Hash::make($c), $plainCodes)))])->save();
    }

    /**
     * Use up a recovery code. Codes saved before hashing was introduced still match as plain text.
     */
    public function useRecoveryCode(string $code): bool
    {
        $stored = $this->getRecoveryCodes();
        foreach ($stored as $i => $hash) {
            if ((str_starts_with($hash, '$2y$') && Hash::check($code, $hash)) || hash_equals($hash, $code)) {
                unset($stored[$i]);
                $this->forceFill(['two_factor_recovery_codes' => encrypt(json_encode(array_values($stored)))])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Get recovery codes.
     */
    public function getRecoveryCodes(): array
    {
        if (! $this->two_factor_recovery_codes) {
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
            'login_count' => $this->login_count + 1,
        ]);
    }

    /**
     * Ban user.
     */
    public function ban(string $reason, ?\DateTime $until = null): void
    {
        $this->update([
            'banned_until' => $until ?? now()->addYear(),
            'banned_reason' => $reason,
        ]);
    }

    /**
     * Unban user.
     */
    public function unban(): void
    {
        $this->update([
            'banned_until' => null,
            'banned_reason' => null,
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

    /**
     * Emails go out in the user's chosen language (en or dv).
     */
    public function preferredLocale(): ?string
    {
        return in_array($this->preferred_language, ['en', 'dv'], true) ? $this->preferred_language : null;
    }

    /**
     * Staff can open the admin area: admin, support or finance (config/staff.php says which pages).
     */
    public function isStaff(): bool
    {
        return \App\Support\StaffAccess::staffRoles($this) !== [];
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Kinds of notification a customer can route to email, SMS or both
     * (stored in notification_preferences.customer.*).
     */
    public const NOTIFICATION_TYPES = ['order_updates', 'delivery_updates', 'marketing', 'security'];

    /**
     * 'email', 'sms' or 'both' for one kind of notification. SMS needs a verified phone,
     * so an SMS choice falls back to email until the number is verified.
     */
    public function notificationPreference(string $type): string
    {
        $value = data_get($this->notification_preferences, 'customer.'.$type, 'email');
        if (! in_array($value, ['email', 'sms', 'both'], true)) {
            return 'email';
        }

        return $value !== 'email' && ! $this->isPhoneVerified() ? 'email' : $value;
    }

    /**
     * Notification channels for one kind of notification, from the customer's preference.
     */
    public function notificationChannels(string $type): array
    {
        return match ($this->notificationPreference($type)) {
            'sms' => ['sms'],
            'both' => ['mail', 'sms'],
            default => ['mail'],
        };
    }

    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }

    /**
     * The bank account iruali pays this shop's earnings to (Seller Centre → Settings → Bank).
     */
    public function bankAccount(): HasOne
    {
        return $this->hasOne(SellerBankAccount::class);
    }

    public function shopName(): string
    {
        return $this->business_name ?: $this->name;
    }

    /**
     * Has the shop finished its onboarding checklist (logo, banner, about, phone, delivery, bank, a product)?
     */
    public function isOnboarded(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    /**
     * The shop emails this user can turn off, all on unless saved otherwise.
     */
    public const SELLER_NOTIFICATION_TYPES = ['new_order', 'return', 'payout', 'low_stock'];

    /**
     * @return array<string, bool>
     */
    public function notificationPreferences(): array
    {
        $saved = is_array($this->notification_preferences) ? $this->notification_preferences : [];

        return collect(self::SELLER_NOTIFICATION_TYPES)->mapWithKeys(fn ($type) => [$type => (bool) ($saved[$type] ?? true)])->all();
    }

    public function wantsNotification(string $type): bool
    {
        return $this->notificationPreferences()[$type] ?? true;
    }
}
