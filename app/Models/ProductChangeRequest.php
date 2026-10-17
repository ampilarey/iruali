<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An edit a shop made to an approved product, waiting for an admin (Admin → Products → Changes
 * waiting). The live product keeps the approved version until the request is approved; then
 * ProductReviewService applies the payload through the same code the product form uses.
 *
 * The payload is the whole reviewed state the shop asked for (ProductReviewService::snapshot()
 * format): product columns, variant rows, the multi-buy fieldset, the main image path, and
 * "staged", the files uploaded for this request (deleted when it is rejected, replaced or withdrawn).
 */
class ProductChangeRequest extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const WITHDRAWN = 'withdrawn';

    protected $fillable = ['product_id', 'seller_id', 'submitted_by', 'payload'];

    protected $casts = [
        'payload' => 'array',
        'reviewed_at' => 'datetime',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @param  Builder<ProductChangeRequest>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /**
     * Files uploaded for this request (main image, variant images).
     *
     * @return list<string>
     */
    public function stagedFiles(): array
    {
        return array_values(array_filter(array_map('strval', (array) ($this->payload['staged'] ?? []))));
    }
}
