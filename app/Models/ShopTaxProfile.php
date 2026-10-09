<?php

namespace App\Models;

use App\Services\GstService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shop's GST details (Seller Centre → Settings → Tax). They are copied onto each order part
 * when the order is placed (App\Services\GstService), so later edits never change old invoices.
 */
class ShopTaxProfile extends Model
{
    protected $fillable = ['user_id', 'gst_registered', 'tin', 'registered_name', 'business_address'];

    protected $casts = ['gst_registered' => 'boolean'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forUser(?int $userId): ?self
    {
        return $userId ? static::where('user_id', $userId)->first() : null;
    }

    /**
     * Registered with a TIN in MIRA's format: only then do the shop's invoices show GST.
     */
    public function isRegistered(): bool
    {
        return $this->gst_registered && GstService::isValidTin($this->tin);
    }
}
