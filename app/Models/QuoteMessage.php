<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One short message in a quote request's thread (QuoteService::postMessage).
 */
class QuoteMessage extends Model
{
    public const ROLES = ['customer', 'seller', 'admin'];

    protected $fillable = ['body'];

    /** @return BelongsTo<QuoteRequest, $this> */
    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * How the sender is shown: the shop's name, the customer's business (or name), or iruali support.
     */
    public function senderName(): string
    {
        $quote = $this->quoteRequest;

        return match ($this->sender_role) {
            'admin' => __('iruali support'),
            'seller' => $quote ? $quote->shopName() : __('Shop'),
            default => $quote?->business_name ?: ($this->sender->name ?? __('Customer')),
        };
    }
}
