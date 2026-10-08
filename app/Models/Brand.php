<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/**
 * One brand, shared by every shop that sells it. Products keep the brand's name in products.brand
 * (search, feeds and filters read that column) and point at this row through products.brand_id.
 * BrandService links the two whenever a product is saved, so "SAMSUNG " typed by one shop and
 * "Samsung" typed by another end up on the same brand page.
 */
class Brand extends Model
{
    use HasTranslations;

    /** A brand page is offered to search engines once this many of its products are on sale. */
    public const INDEX_MIN_PRODUCTS = 3;

    /** What shops type when a product has no brand. These never become brands. */
    public const NO_BRAND_KEYS = ['na', 'none', 'nobrand', 'generic', 'unbranded', 'nil', 'null', 'other', 'others'];

    public $translatable = ['description'];

    protected $fillable = ['name', 'slug', 'key', 'logo', 'description', 'created_by', 'reviewed_at'];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Old names and old web addresses that still lead here (after a rename or a merge). */
    public function aliases(): HasMany
    {
        return $this->hasMany(BrandAlias::class);
    }

    /** The shop whose product first used this brand (null for brands an admin or the backfill made). */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Brands with at least one product on sale: the ones the storefront shows. */
    public function scopeListed(Builder $query): Builder
    {
        return $query->whereHas('products', fn (Builder $products) => $products->where('products.is_active', true));
    }

    public function scopeWithActiveProductCount(Builder $query): Builder
    {
        return $query->withCount(['products as active_products_count' => fn (Builder $products) => $products->where('products.is_active', true)]);
    }

    /** Products on sale: the loaded count when there is one (withActiveProductCount), else a query. */
    public function activeProductCount(): int
    {
        return (int) ($this->getAttribute('active_products_count') ?? Product::query()->active()->where('brand_id', $this->id)->count());
    }

    /**
     * Shops iruali has confirmed as authorised sellers of this brand (Admin → Brands → edit).
     *
     * @return BelongsToMany<User, $this>
     */
    public function authorisedSellers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'brand_authorised_sellers', 'brand_id', 'seller_id')
            ->withPivot('authorised_by')
            ->withTimestamps();
    }

    /**
     * Is this shop an authorised seller of the brand? Reads the loaded authorisedSellers (loading
     * just their ids the first time), so a page asking for every product or shop of a brand runs
     * one query per brand, or none when the relation was eager loaded.
     */
    public function isAuthorisedSeller(int $sellerId): bool
    {
        if (! $this->relationLoaded('authorisedSellers')) {
            $this->load('authorisedSellers:id');
        }

        return $this->authorisedSellers->contains('id', $sellerId);
    }

    /**
     * Campaigns for this brand only: just its products can go in, and they show on its page.
     *
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * How names are matched: lower case, letters, marks and digits only, so "Dr. Martens",
     * "DR MARTENS" and "dr-martens" are one brand. Thaana vowel signs are marks and are kept.
     */
    public static function keyFor(?string $name): string
    {
        return mb_strtolower((string) preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '', (string) $name));
    }

    /** The name as typed, tidied: trimmed, single spaces, at most 120 characters. */
    public static function cleanName(?string $name): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) $name)), 0, 120);
    }

    /** "N/A", "No brand", "Generic" and the like, or nothing at all. */
    public static function isNoBrand(string $key): bool
    {
        return $key === '' || in_array($key, self::NO_BRAND_KEYS, true);
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }

    /** The description in the shopper's language, falling back to English. */
    public function localizedDescription(): ?string
    {
        $text = $this->getTranslation('description', app()->getLocale(), false) ?: $this->getTranslation('description', 'en', false);

        return filled($text) ? $text : null;
    }

    /** The letter shown on a logo-less tile. */
    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }

    /** Where the brand sits in the A–Z list: its first Latin letter, or "#" for digits and other scripts. */
    public function indexLetter(): string
    {
        $first = strtoupper(substr(Str::ascii($this->name), 0, 1));

        return preg_match('/^[A-Z]$/', $first) ? $first : '#';
    }

    public function isIndexable(int $activeProducts): bool
    {
        return $activeProducts >= self::INDEX_MIN_PRODUCTS;
    }
}
