<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One newsletter an admin writes (Admin → Moderation → Newsletter): a subject and an opening text
 * in English and Dhivehi, plus sections the site fills in (new arrivals, deals, a running
 * campaign, featured brands). Draft until sent, then sending while the queue works through the
 * batches, then sent. See NewsletterService.
 */
class NewsletterIssue extends Model
{
    public const STATUSES = ['draft', 'sending', 'sent'];

    /** Days of new arrivals offered when the section is ticked. */
    public const DEFAULT_NEW_ARRIVAL_DAYS = 7;

    protected $fillable = ['subject_en', 'subject_dv', 'intro_en', 'intro_dv', 'sections', 'created_by'];

    protected $casts = [
        'sections' => 'array',
        'content' => 'array',
        'recipients_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'test_sent_at' => 'datetime',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /** @return HasMany<NewsletterDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NewsletterDelivery::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** The subject in a language: the Dhivehi one for Dhivehi readers when written, else the English one. */
    public function subjectFor(string $locale): string
    {
        return $locale === 'dv' && filled($this->subject_dv) ? (string) $this->subject_dv : (string) $this->subject_en;
    }

    public function introFor(string $locale): string
    {
        return $locale === 'dv' && filled($this->intro_dv) ? (string) $this->intro_dv : (string) $this->intro_en;
    }

    /** Both languages written: needed before it can be sent, as each reader gets their own. */
    public function hasDhivehi(): bool
    {
        return filled($this->subject_dv) && filled($this->intro_dv);
    }

    /** Days of new arrivals to include, or null when that section is off. */
    public function newArrivalDays(): ?int
    {
        $days = data_get($this->sections, 'new_arrivals_days');

        return $days ? (int) $days : null;
    }

    public function wantsDeals(): bool
    {
        return (bool) data_get($this->sections, 'deals', false);
    }

    public function campaignId(): ?int
    {
        $id = data_get($this->sections, 'campaign_id');

        return $id ? (int) $id : null;
    }

    public function wantsBrands(): bool
    {
        return (bool) data_get($this->sections, 'brands', false);
    }
}
