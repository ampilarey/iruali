<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * The secret in the product feed URLs (Google Merchant, Facebook catalogue), so scrapers can't pull
 * the whole catalogue freely. Generated once and kept in Settings; Admin → Settings shows the URLs.
 */
class FeedToken
{
    public static function get(): string
    {
        $token = (string) Setting::get('feed_token');
        if ($token === '') {
            $token = Str::random(40);
            Setting::set(['feed_token' => $token]);
        }

        return $token;
    }

    public static function valid(?string $given): bool
    {
        return is_string($given) && $given !== '' && hash_equals(self::get(), $given);
    }

    public static function url(string $route): string
    {
        return route($route, ['token' => self::get()]);
    }
}
