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
        'announcement_text' => '🎉 Free Shipping on Orders Over $49 | Expert Support Available 24/7',
        'contact_email' => '',
        'contact_phone' => '',
        'loyalty_spend_per_point' => 100,
        'referral_referrer_points' => 100,
        'referral_referee_points' => 50,
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
