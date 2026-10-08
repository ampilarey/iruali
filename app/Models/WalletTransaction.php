<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of store credit (MVR). The customer's wallet_balance is the sum of their rows.
 */
class WalletTransaction extends Model
{
    public const TYPES = ['refund', 'gift_card', 'purchase', 'adjustment', 'expired'];

    protected $fillable = ['user_id', 'amount', 'type', 'reference', 'order_id', 'gift_card_id', 'note', 'expires_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    /** @return BelongsTo<GiftCard, $this> */
    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'refund' => __('Refund'),
            'gift_card' => __('Gift card'),
            'purchase' => __('Paid for an order'),
            'adjustment' => __('Adjustment'),
            'expired' => __('Expired'),
            default => ucfirst($type),
        };
    }
}
