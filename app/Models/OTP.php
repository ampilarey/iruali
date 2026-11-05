<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OTP extends Model
{
    use HasFactory;

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

    /**
     * Create a new OTP for phone verification
     */
    public static function createForPhone(string $phone, string $purpose = 'verification'): self
    {
        // Invalidate existing OTPs
        self::where('phone', $phone)
            ->where('purpose', $purpose)
            ->update(['is_used' => true]);

        return self::create([
            'phone' => $phone,
            'code' => self::generateCode(),
            'type' => 'sms',
            'purpose' => $purpose,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);
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

        return self::create([
            'email' => $email,
            'code' => self::generateCode(),
            'type' => 'email',
            'purpose' => $purpose,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);
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
            if (hash_equals((string) $otp->code, $code)) {
                $otp->markAsUsed();

                return $otp;
            }

            $attempts = (int) $otp->attempts + 1;
            $otp->forceFill(['attempts' => $attempts, 'is_used' => $attempts >= self::MAX_ATTEMPTS])->save();

            return null;
        });
    }
}
