<?php

namespace App\Support;

use App\Http\Middleware\LocalePrefix;
use App\Services\LocalizationService;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\URL;

/**
 * Language in the URL: English storefront pages keep their plain URLs, Dhivehi ones live under /dv.
 *
 * Storefront routes are the ones registered in a group with the custom attribute
 * ['localized' => true] (routes/web.php). route() prefixes them with /dv whenever the app locale
 * is Dhivehi; everything else (account, checkout, admin, seller, API...) is never prefixed.
 */
class LocaleUrl
{
    /**
     * Installs the path hook on the URL generator (called from AppServiceProvider::boot).
     */
    public static function register(): void
    {
        URL::formatPathUsing(function (string $path, $route = null) {
            if ($route instanceof Route && app()->getLocale() === LocalePrefix::PREFIX && self::isLocalized($route)) {
                return '/'.LocalePrefix::PREFIX.($path === '/' ? '' : $path);
            }

            return $path;
        });
    }

    public static function isLocalized(?Route $route): bool
    {
        return (bool) $route?->getAction('localized');
    }

    /**
     * The current page in the given language. Storefront pages get the matching URL; any other
     * page is the same URL (its language follows the session).
     */
    public static function alternate(string $locale): string
    {
        $request = request();
        $route = $request->route();
        $query = $request->getQueryString();

        if (! $route instanceof Route || ! self::isLocalized($route) || ! $route->getName()) {
            return $request->fullUrl();
        }

        $url = self::inLocale($locale, fn () => route($route->getName(), $route->parameters()));

        return $query ? $url.'?'.$query : $url;
    }

    /**
     * hreflang alternates for the current page, or [] when it is not a storefront page.
     */
    public static function alternates(): array
    {
        $route = request()->route();
        if (! $route instanceof Route || ! self::isLocalized($route) || ! $route->getName()) {
            return [];
        }

        $links = [];
        foreach (LocalizationService::getAvailableLocales() as $locale) {
            $links[$locale] = self::alternate($locale);
        }
        $links['x-default'] = $links[LocalizationService::getFallbackLocale()];

        return $links;
    }

    /**
     * The current page in the given language as a site path (for the language links).
     */
    public static function alternatePath(string $locale): string
    {
        $parts = parse_url(self::alternate($locale));

        return ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * A site path (with or without /dv) as it would be in the given language: /dv is dropped,
     * then added back for Dhivehi when the path is a storefront page.
     */
    public static function alternateOf(string $path, string $locale): string
    {
        $parts = parse_url($path);
        $p = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $prefix = '/'.LocalePrefix::PREFIX;
        if ($p === $prefix || str_starts_with($p, $prefix.'/')) {
            $p = substr($p, strlen($prefix)) ?: '/';
        }

        if ($locale === LocalePrefix::PREFIX) {
            try {
                $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create($p));
            } catch (\Throwable) {
                $route = null;
            }
            if (self::isLocalized($route)) {
                $p = $prefix.($p === '/' ? '' : $p);
            }
        }

        return $p.$query;
    }

    /**
     * route() for a named route in a given language.
     */
    public static function route(string $name, mixed $parameters, string $locale): string
    {
        return self::inLocale($locale, fn () => route($name, $parameters));
    }

    protected static function inLocale(string $locale, callable $callback): string
    {
        $current = app()->getLocale();
        app()->setLocale($locale);
        try {
            return $callback();
        } finally {
            app()->setLocale($current);
        }
    }
}
