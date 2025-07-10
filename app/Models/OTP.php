<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
        'expires_at'
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'expires_at' => 'datetime'
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
        return !$this->is_used && !$this->isExpired();
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
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
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
            'expires_at' => Carbon::now()->addMinutes(10)
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
            'expires_at' => Carbon::now()->addMinutes(10)
        ]);
    }

    /**
     * Verify OTP code
     */
    public static function verify(string $identifier, string $code, string $purpose = 'verification'): ?self
    {
        $otp = self::where(function($query) use ($identifier) {
            $query->where('phone', $identifier)
                  ->orWhere('email', $identifier);
        })
        ->where('code', $code)
        ->where('purpose', $purpose)
        ->where('is_used', false)
        ->where('expires_at', '>', Carbon::now())
        ->first();

        if ($otp) {
            $otp->markAsUsed();
        }

        return $otp;
    }
}
