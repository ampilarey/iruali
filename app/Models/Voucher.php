<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'type', 'amount', 'min_order', 'max_uses', 'used_count', 'valid_from', 'valid_until', 'is_active',
        'is_automatic', 'free_delivery', 'batch',
        'user_id', // set when the voucher was issued to one customer (abandoned-cart nudges)
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'min_order' => 'decimal:2',
        'is_automatic' => 'boolean',
        'free_delivery' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * The one customer this voucher was issued to, or null when anyone may use it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usableBy(?int $userId): bool
    {
        return $this->user_id === null || $this->user_id === $userId;
    }
}
