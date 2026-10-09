<?php

namespace App\Models;

use App\Support\ShopStaffAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * An emailed invitation to join a shop's staff. The link is signed and carries a random token, of
 * which only the hash is stored; it works until expires_at (7 days) unless the owner withdraws it
 * (revoked_at) or it is used (accepted_at).
 */
class ShopStaffInvitation extends Model
{
    /** Everything is set by ShopStaffService, never from input. */
    protected $fillable = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shop_id');
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Neither used, withdrawn nor run out.
     *
     * @param  Builder<ShopStaffInvitation>  $query
     * @return Builder<ShopStaffInvitation>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function matchesToken(string $token): bool
    {
        return hash_equals((string) $this->token_hash, self::hashToken($token));
    }

    /**
     * pending, accepted, revoked or expired.
     */
    public function status(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->revoked_at !== null => 'revoked',
            $this->expires_at === null || $this->expires_at->isPast() => 'expired',
            default => 'pending',
        };
    }

    public function isPending(): bool
    {
        return $this->status() === 'pending';
    }

    /**
     * The signed link in the email. The same link shows the invitation and accepts it.
     */
    public function url(string $token): string
    {
        return URL::signedRoute('shop-invitations.show', ['invitation' => $this->id, 'token' => $token]);
    }

    public function roleLabel(): string
    {
        return ShopStaffAccess::roleLabel((string) $this->role);
    }

    /** Emails are compared without regard to case or surrounding spaces. */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
