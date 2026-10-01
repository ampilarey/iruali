<?php

namespace App\Models;

use App\Services\DeliveryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * A delivery address a customer saved for checkout. One of them is the default.
 */
class Address extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'label', 'recipient_name', 'phone', 'island_id', 'atoll', 'island',
        'house_name_or_street', 'ward', 'postal_code', 'country', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function islandRecord(): BelongsTo
    {
        return $this->belongsTo(Island::class, 'island_id');
    }

    public function scopeDefaultFirst($query)
    {
        return $query->orderByDesc('is_default')->orderBy('label')->orderBy('id');
    }

    /**
     * Copy the island's name and atoll from the islands table when one was picked, so the
     * stored text always matches the record (the free-text fields are for islands not in the list).
     */
    public function syncIsland(): static
    {
        if ($this->island_id && ($island = Island::find($this->island_id))) {
            $this->island = $island->getTranslation('name', 'en', false) ?: $island->getTranslation('name', config('app.fallback_locale'), false);
            $this->atoll = $island->atoll;
        }

        return $this;
    }

    /**
     * Make this the customer's default address (the previous default steps down).
     */
    public function makeDefault(): void
    {
        DB::transaction(function () {
            static::where('user_id', $this->user_id)->whereKeyNot($this->id)->update(['is_default' => false]);
            $this->forceFill(['is_default' => true])->save();
        });
    }

    /**
     * The delivery zone this address falls in (Greater Malé or other islands).
     */
    public function deliveryZone(): string
    {
        return app(DeliveryService::class)->zoneForIsland($this->island_id ? $this->islandRecord : null, $this->island);
    }

    /**
     * The order's shipping_* fields for this address (OrderService stays unchanged).
     */
    public function toShippingData(): array
    {
        return [
            'shipping_address' => trim($this->house_name_or_street.($this->ward ? ', '.$this->ward : '')),
            'shipping_city' => $this->island,
            'shipping_state' => (string) $this->atoll,
            'shipping_zip' => $this->postal_code,
            'shipping_country' => $this->country ?: 'Maldives',
            'shipping_phone' => $this->phone,
            'delivery_zone' => $this->deliveryZone(),
        ];
    }

    /**
     * One-line summary for cards and lists.
     */
    public function summary(): string
    {
        return collect([$this->house_name_or_street, $this->ward, $this->island, $this->atoll, $this->postal_code])->filter()->join(', ');
    }

    /**
     * The first address a customer saves becomes the default; when the default is removed, the next one takes over.
     */
    protected static function booted(): void
    {
        static::creating(function (Address $address) {
            if (! static::where('user_id', $address->user_id)->exists()) {
                $address->is_default = true;
            }
        });

        static::saving(function (Address $address) {
            if ($address->is_default && $address->exists) {
                static::where('user_id', $address->user_id)->whereKeyNot($address->id)->update(['is_default' => false]);
            } elseif ($address->is_default && ! $address->exists) {
                static::where('user_id', $address->user_id)->update(['is_default' => false]);
            }
        });

        static::deleted(function (Address $address) {
            if ($address->is_default) {
                static::where('user_id', $address->user_id)->orderBy('id')->first()?->forceFill(['is_default' => true])->save();
            }
        });
    }
}
