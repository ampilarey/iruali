<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone waiting for new shop applications to reopen ("Tell me when you reopen" while they are
 * paused or hidden). They get one email when applications open again.
 *
 * @property string $email
 * @property string $locale
 * @property \Illuminate\Support\Carbon|null $notified_at
 */
class SellerWaitlist extends Model
{
    protected $table = 'seller_waitlist';

    protected $fillable = ['email', 'user_id', 'locale'];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Still waiting for the reopening email. */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->whereNull('notified_at');
    }
}
