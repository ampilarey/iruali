<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One address an issue goes to. Unique per issue and address, so an address is listed once even
 * when it is both a customer and a subscriber. A batch claims a row (pending → sending) before it
 * sends, so a retried batch never sends the same row again. Status: pending, sending, sent,
 * failed, or skipped (unsubscribed after the send started).
 */
class NewsletterDelivery extends Model
{
    protected $fillable = ['newsletter_issue_id', 'email', 'locale', 'user_id', 'newsletter_subscriber_id', 'status', 'error', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /** @return BelongsTo<NewsletterIssue, $this> */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(NewsletterIssue::class, 'newsletter_issue_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<NewsletterSubscriber, $this> */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'newsletter_subscriber_id');
    }
}
