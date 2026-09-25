<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected const CACHE_KEY = 'app_settings';

    /**
     * Editable settings and their defaults.
     */
    public const DEFAULTS = [
        'announcement_text' => 'Shop local sellers from every island, delivered across the Maldives.',
        'contact_email' => '',
        'contact_phone' => '',
        // Business details BML asks to see on the website (policies, footer, checkout)
        'company_legal_name' => '',
        'company_trading_name' => 'iruali',
        'company_registration_no' => '',
        'company_address' => '',
        'company_postal_address' => '',
        'customer_service_hours' => 'Sunday to Thursday, 9:00 to 17:00 (Maldives time)',
        'return_window_days' => 7,
        // Legal pages: optional owner-written text that replaces the built-in policy, and the "Last updated" date
        'legal_last_updated_date' => '',
        'legal_terms_body' => '',
        'legal_refunds_body' => '',
        'legal_delivery_body' => '',
        'legal_privacy_body' => '',
        'legal_security_body' => '',
        'whatsapp_number' => '',
        'loyalty_spend_per_point' => 100,
        'referral_referrer_points' => 100,
        'referral_referee_points' => 50,
        'delivery_fee_greater_male' => 25,
        'delivery_fee_islands' => 75,
        'free_delivery_over' => 1000,
        'payment_cod_enabled' => '1',
        'bank_name' => 'Bank of Maldives',
        'bank_account_name' => '',
        'bank_account_number' => '',
    ];

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $values = static::values();

        // Only fall back when the setting was never saved; a saved blank value stays blank.
        if (! array_key_exists($key, $values)) {
            return $default ?? (static::DEFAULTS[$key] ?? null);
        }

        return $values[$key];
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value ?? '']);
        }

        Cache::forget(static::CACHE_KEY);
    }

    /**
     * All stored settings as key => value, cached.
     */
    protected static function values(): array
    {
        if (Cache::has(static::CACHE_KEY)) {
            return Cache::get(static::CACHE_KEY);
        }

        // Before the key/value migration has run, fall back to defaults without caching.
        if (! Schema::hasColumn('settings', 'key')) {
            return [];
        }

        return Cache::rememberForever(static::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
    }
}
