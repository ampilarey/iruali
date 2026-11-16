<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A one-time code (sign-in, verification). Only a keyed hash of the code is stored (code), so the
 * database, its backups and the staging copy never hold a code that works; the code itself is on the
 * new model as plainCode, for the text or email that sends it, and is gone after that request.
 */
class OTP extends Model
{
    use HasFactory;

    /** The code just made (never saved): what the notification sends. */
    public ?string $plainCode = null;

    /** @var array<string, string> tests only: the last code made per number or email and purpose */
    protected static array $issuedInTests = [];

    protected $table = 'otps';

    protected $fillable = [
        'phone',
        'email',
        'code',
        'type',
        'purpose',
        'is_used',
        'attempts',
        'expires_at',
    ];

    /** Wrong tries a code takes before it is spent (one code, wherever the guesses come from). */
    public const MAX_ATTEMPTS = 5;

    protected $casts = [
        'is_used' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Check if OTP is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if OTP is valid (not used and not expired)
     */
    public function isValid(): bool
    {
        return ! $this->is_used && ! $this->isExpired();
    }

    /**
     * Mark OTP as used
     */
    public function markAsUsed(): void
    {
        $this->update(['is_used' => true]);
    }

    /**
     * Generate a new OTP code
     */
    public static function generateCode(): string
    {
        return sprintf('%06d', random_int(0, 999999));
    }

    /** What is stored for a code: an HMAC with the app key, compared with hash_equals. */
    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', trim($code), (string) config('app.key'));
    }

    /** A new code for this number or email, saved hashed, with the code itself on plainCode. */
    protected static function issue(array $attributes): self
    {
        $code = self::generateCode();
        $otp = self::create($attributes + ['code' => self::hashCode($code), 'expires_at' => Carbon::now()->addMinutes(10)]);
        $otp->plainCode = $code;
        if (app()->runningUnitTests()) {
            self::$issuedInTests[($attributes['phone'] ?? $attributes['email'] ?? '').'|'.$attributes['purpose']] = $code;
            self::$issuedInTests['*|'.$attributes['purpose']] = $code;
        }

        return $otp;
    }

    /** Tests only: the last code made for this number or email (any, when null) and purpose (the database has its hash). */
    public static function lastCodeFor(?string $identifier, string $purpose): ?string
    {
        return self::$issuedInTests[($identifier ?? '*').'|'.$purpose] ?? null;
    }

    /**
     * Create a new OTP for phone verification
     */
    public static function createForPhone(string $phone, string $purpose = 'verification'): self
    {
        // Invalidate existing OTPs
        self::where('phone', $phone)
            ->where('purpose', $purpose)
            ->update(['is_used' => true]);

        return self::issue(['phone' => $phone, 'type' => 'sms', 'purpose' => $purpose]);
    }

    /**
     * Create a new OTP for email verification
     */
    public static function createForEmail(string $email, string $purpose = 'verification'): self
    {
        // Invalidate existing OTPs
        self::where('email', $email)
            ->where('purpose', $purpose)
            ->update(['is_used' => true]);

        return self::issue(['email' => $email, 'type' => 'email', 'purpose' => $purpose]);
    }

    /**
     * Check a code against the live one for this number or email and purpose. A right code is spent at once;
     * each wrong one counts against that code, which is spent after MAX_ATTEMPTS wrong tries, so guessing gets
     * at most a handful of tries per code across every sign-in page and account.
     */
    public static function verify(string $identifier, string $code, string $purpose = 'verification'): ?self
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($identifier, $code, $purpose) {
            $otp = self::where(function ($query) use ($identifier) {
                $query->where('phone', $identifier)
                    ->orWhere('email', $identifier);
            })
                ->where('purpose', $purpose)
                ->where('is_used', false)
                ->where('expires_at', '>', Carbon::now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $otp) {
                return null;
            }
            if (hash_equals((string) $otp->code, self::hashCode($code))) {
                $otp->markAsUsed();

                return $otp;
            }

            $attempts = (int) $otp->attempts + 1;
            $otp->forceFill(['attempts' => $attempts, 'is_used' => $attempts >= self::MAX_ATTEMPTS])->save();

            return null;
        });
    }
}
