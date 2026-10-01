<?php

namespace App\Models;

use App\Enums\PayoutBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Several shops paid in one bank bulk transfer.
 *
 * draft (pending payouts allocated, nothing sent) → exported (bank file downloaded) → paid (bank
 * reference and date recorded, every payout marked paid). A draft can be cancelled, which releases
 * its payouts so the earnings are available again.
 */
class PayoutBatch extends Model
{
    protected $fillable = ['reference', 'created_by', 'status', 'total', 'count', 'exported_at', 'paid_at', 'bank_reference', 'notes'];

    protected $casts = [
        'total' => 'decimal:2',
        'count' => 'integer',
        'exported_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function payouts(): HasMany
    {
        return $this->hasMany(SellerPayout::class, 'payout_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The next reference for a new batch: PB-<year>-<number>, numbered per year.
     */
    public static function nextReference(): string
    {
        $year = now()->format('Y');
        $last = static::where('reference', 'like', "PB-{$year}-%")->orderByDesc('reference')->value('reference');
        $n = $last ? (int) substr($last, -4) + 1 : 1;

        return sprintf('PB-%s-%04d', $year, $n);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isOpen(): bool
    {
        return (bool) $this->statusEnum()?->isOpen();
    }

    public function getStatusBadgeAttribute(): string
    {
        return PayoutBatchStatus::badgeFor($this->status);
    }

    public function statusLabel(): string
    {
        return PayoutBatchStatus::labelFor($this->status);
    }

    public function statusEnum(): ?PayoutBatchStatus
    {
        return PayoutBatchStatus::tryFrom((string) $this->status);
    }
}
