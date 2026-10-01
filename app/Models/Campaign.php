<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/**
 * A sale or event with a banner, a landing page and a time window. Shops join by putting products
 * in at a discount (at least the campaign's own percent); an admin approves each one, and the
 * campaign price then applies everywhere the product's price is shown or charged.
 */
class Campaign extends Model
{
    use HasFactory, HasTranslations;

    public const TYPES = ['sale', 'event'];

    public const PLACEMENTS = ['home_hero', 'home_strip', 'category'];

    public $translatable = ['headline', 'subheadline', 'cta_text'];

    protected $fillable = [
        'name', 'slug', 'type', 'starts_at', 'ends_at', 'banner_image', 'headline', 'subheadline', 'cta_text', 'cta_url',
        'theme_colour', 'is_active', 'discount_percent', 'placement', 'sort_order',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'discount_percent' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /** Live discounts per product for the current minute (see liveDiscounts()). */
    protected static array $discountCache = [];

    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign) {
            if (empty($campaign->slug)) {
                $campaign->slug = $campaign->uniqueSlug($campaign->name);
            }
        });
        static::saved(fn () => static::forgetDiscounts());
        static::deleted(fn () => static::forgetDiscounts());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'campaign';
        $slug = $base;
        $n = 1;
        while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    public function participations(): HasMany
    {
        return $this->hasMany(CampaignProduct::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'campaign_products')
            ->withPivot(['seller_id', 'discount_percent', 'approved_at'])
            ->withTimestamps();
    }

    /**
     * Products whose participation an admin approved.
     */
    public function approvedProducts(): BelongsToMany
    {
        return $this->products()->whereNotNull('campaign_products.approved_at');
    }

    // ---- Windows ------------------------------------------------------------------------------

    public function scopeLive($query)
    {
        return $query->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>=', now());
    }

    /**
     * Shops may join a campaign that is switched on and has not ended yet.
     */
    public function scopeJoinable($query)
    {
        return $query->where('is_active', true)->where('ends_at', '>=', now());
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('starts_at');
    }

    public function scopePlacement($query, string $placement)
    {
        return $query->where('placement', $placement);
    }

    public function isLive(): bool
    {
        return $this->is_active && $this->starts_at->isPast() && $this->ends_at->isFuture();
    }

    public function isJoinable(): bool
    {
        return $this->is_active && $this->ends_at->isFuture();
    }

    public function isUpcoming(): bool
    {
        return $this->is_active && $this->starts_at->isFuture();
    }

    /**
     * Minimum discount a shop must give to join (0 when the campaign sets none).
     */
    public function minimumDiscount(): float
    {
        return (float) ($this->discount_percent ?? 0);
    }

    /**
     * The discount that applies to a participating product: the shop's own, else the campaign's.
     */
    public function discountFor(CampaignProduct $participation): float
    {
        return (float) ($participation->discount_percent ?? $this->discount_percent ?? 0);
    }

    // ---- Price pipeline -----------------------------------------------------------------------

    /**
     * product_id => highest discount percent among live campaigns with approved participation.
     * Loaded once per request (and per minute, so time travel in tests sees fresh data).
     *
     * @return array<int, float>
     */
    public static function liveDiscounts(): array
    {
        $key = now()->format('YmdHi');
        if (! isset(static::$discountCache[$key])) {
            static::$discountCache = [$key => static::loadLiveDiscounts()];
        }

        return static::$discountCache[$key];
    }

    protected static function loadLiveDiscounts(): array
    {
        $discounts = [];

        CampaignProduct::query()
            ->whereNotNull('approved_at')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_products.campaign_id')
            ->where('campaigns.is_active', true)
            ->where('campaigns.starts_at', '<=', now())
            ->where('campaigns.ends_at', '>=', now())
            ->get(['campaign_products.product_id', 'campaign_products.discount_percent as own', 'campaigns.discount_percent as campaign'])
            ->each(function ($row) use (&$discounts) {
                $pct = (float) ($row->own ?? $row->campaign ?? 0);
                if ($pct > 0) {
                    $discounts[(int) $row->product_id] = max($discounts[(int) $row->product_id] ?? 0, $pct);
                }
            });

        return $discounts;
    }

    public static function forgetDiscounts(): void
    {
        static::$discountCache = [];
    }
}
