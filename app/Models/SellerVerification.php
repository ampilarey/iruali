<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A shop's business verification: its registration certificate and number, and its owner's national
 * ID card number and a photo of the card's front. iruali checks them (Admin → Verifications); an
 * approved shop shows "Verified business" next to its name. Sending anything new puts it back to
 * pending (SellerVerificationService::submit).
 *
 * The files are on the private "local" disk (storage/app/private), never under public/, and are only
 * served by the admin route and the shop's own Settings page, both of which check who is asking.
 */
class SellerVerification extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /** storage/app/private (config/filesystems.php): nothing on it can be reached from the web */
    public const DISK = 'local';

    /** The documents a shop sends, and the column holding each file's path */
    public const DOCUMENTS = ['certificate' => 'certificate_path', 'id_card' => 'id_card_path'];

    /** Admin → Payouts: "Require verified business before payouts" (off by default) */
    public const PAYOUT_SETTING = 'payouts_require_verified_business';

    protected $fillable = [
        'user_id', 'status', 'business_registration_number', 'certificate_path', 'national_id_number',
        'id_card_path', 'submitted_at', 'reviewed_at', 'reviewed_by', 'rejection_reason',
    ];

    /** Never in JSON or logs: the ID number and where the files are */
    protected $hidden = ['national_id_number', 'certificate_path', 'id_card_path'];

    protected $casts = [
        'national_id_number' => 'encrypted',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @param  Builder<SellerVerification>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::PENDING);
    }

    /**
     * Only current shops: not someone whose application was turned down, nor a deleted account.
     *
     * @param  Builder<SellerVerification>  $query
     */
    public function scopeForShops(Builder $query): void
    {
        $query->whereHas('user', fn ($q) => $q->where('is_seller', true));
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::REJECTED;
    }

    public function documentPath(string $document): ?string
    {
        $column = self::DOCUMENTS[$document] ?? null;

        return $column ? $this->getAttribute($column) : null;
    }

    public function documentExists(string $document): bool
    {
        $path = $this->documentPath($document);

        return $path !== null && $path !== '' && Storage::disk(self::DISK)->exists($path);
    }

    /** The stored extension comes from the detected content type, so it can be trusted here. */
    public function isPdf(string $document): bool
    {
        return strtolower(pathinfo((string) $this->documentPath($document), PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * The owner's ID card number, or null when it can't be read (e.g. a copy of the database
     * running with another APP_KEY).
     */
    public function nationalIdNumber(): ?string
    {
        try {
            return $this->getAttribute('national_id_number');
        } catch (DecryptException) {
            return null;
        }
    }

    /** "A•••456": enough for the shop to recognise the number it sent. */
    public function maskedNationalId(): string
    {
        $number = (string) $this->nationalIdNumber();
        if (mb_strlen($number) <= 4) {
            return str_repeat('•', mb_strlen($number));
        }

        return mb_substr($number, 0, 1).str_repeat('•', mb_strlen($number) - 4).mb_substr($number, -3);
    }

    /** Whether payouts are held for shops that are not verified (Admin → Payouts). */
    public static function requiredForPayouts(): bool
    {
        return (bool) Setting::get(self::PAYOUT_SETTING, 0);
    }

    /** This shop's payouts wait: the rule is on and its business is not verified. */
    public static function payoutHeld(User $shop): bool
    {
        return self::requiredForPayouts() && ! $shop->hasVerifiedBusiness();
    }
}
