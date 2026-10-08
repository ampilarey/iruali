<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of loyalty points. The user's balance is the sum of their rows.
 */
class PointsTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['earned', 'redeemed', 'referral', 'refund', 'adjustment', 'expired'];

    protected $fillable = ['user_id', 'points', 'type', 'order_id', 'note', 'created_at'];

    protected $casts = [
        'points' => 'integer',
        'created_at' => 'datetime',
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

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'earned' => __('Earned on an order'),
            'redeemed' => __('Redeemed at checkout'),
            'referral' => __('Referral reward'),
            'refund' => __('Returned (order cancelled)'),
            'adjustment' => __('Adjustment'),
            'expired' => __('Expired'),
            default => ucfirst($type),
        };
    }
}
