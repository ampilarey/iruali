<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A gift card: bought like an order, issued (code + email to the recipient) once paid, redeemed
 * into the recipient's wallet, valid 12 months.
 */
class GiftCard extends Model
{
    use HasFactory;

    public const AMOUNTS = [100, 250, 500, 1000];

    public const MIN_CUSTOM = 50;

    public const MAX_CUSTOM = 5000;

    public const VALID_MONTHS = 12;

    protected $fillable = [
        'code', 'amount', 'balance', 'purchaser_id', 'order_id', 'recipient_email', 'recipient_name', 'message',
        'status', 'expires_at', 'delivered_at', 'redeemed_by', 'redeemed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'expires_at' => 'datetime',
        'delivered_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    public function isRedeemable(): bool
    {
        return $this->status === 'active' && (float) $this->balance > 0 && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * "IRU-XXXX-XXXX-XXXX" without look-alike characters.
     */
    public static function freshCode(): string
    {
        do {
            $raw = strtoupper(Str::random(16));
            $raw = preg_replace('/[^A-Z0-9]/', '', str_replace(['O', '0', 'I', '1', 'L'], ['P', '2', 'J', '3', 'M'], $raw));
            $raw = str_pad($raw, 12, 'X');
            $code = 'IRU-'.substr($raw, 0, 4).'-'.substr($raw, 4, 4).'-'.substr($raw, 8, 4);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public static function normaliseCode(string $input): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));
        if (str_starts_with($clean, 'IRU')) {
            $clean = substr($clean, 3);
        }

        return 'IRU-'.implode('-', str_split(str_pad(substr($clean, 0, 12), 12, 'X'), 4));
    }
}
