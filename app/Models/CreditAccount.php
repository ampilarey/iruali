<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business buyer allowed to pay later: up to a credit limit, each invoice due after the terms.
 */
class CreditAccount extends Model
{
    protected $fillable = ['user_id', 'credit_limit', 'terms_days', 'status', 'approved_by', 'note'];

    protected $casts = ['credit_limit' => 'decimal:2', 'terms_days' => 'integer'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
