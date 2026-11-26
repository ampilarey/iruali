<?php

namespace App\Http\Middleware;

use App\Services\LocalizationService;
use App\Support\LocaleUrl;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // /dv/... is only for storefront pages (see LocalePrefix and LocaleUrl): a page that has no Dhivehi
        // address (account, checkout, sign-in...) is opened at its plain address, in the visitor's language
        if (LocalePrefix::active($request) && ! LocaleUrl::isLocalized($request->route())) {
            abort_unless($request->isMethod('GET') || $request->isMethod('HEAD'), 404);
            if (LocalizationService::dhivehiEnabled() && $request->hasSession()) {
                $request->session()->put('locale', LocalePrefix::PREFIX); // they were reading in Dhivehi
            }

            return redirect()->to(self::withoutPrefix($request), 302);
        }

        // Dhivehi switched off: a /dv page sends the visitor to the same page in English (a temporary
        // redirect, as it may come back on)
        if (LocalePrefix::active($request) && ! LocalizationService::dhivehiEnabled()) {
            return redirect()->to(LocaleUrl::alternate('en'), 302);
        }

        // Get locale from various sources
        $locale = $this->getLocale($request);

        // Validate locale
        if (! in_array($locale, LocalizationService::getAvailableLocales())) {
            $locale = LocalizationService::getFallbackLocale();
        }

        // Set the application locale (Carbon follows it so translatedFormat() gives Dhivehi month/day names)
        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    /**
     * The same address without /dv (the request's base URL ends in "/dv", see LocalePrefix).
     */
    public static function withoutPrefix(Request $request): string
    {
        $base = substr($request->getBaseUrl(), 0, -strlen('/'.LocalePrefix::PREFIX));
        $query = $request->getQueryString();

        return $request->getSchemeAndHttpHost().$base.$request->getPathInfo().($query !== null ? '?'.$query : '');
    }

    /**
     * Error pages: an address the router did not match never ran this middleware, so the language
     * comes from the /dv prefix, or the visitor's choice when the session is open.
     */
    public static function forErrorPage(Request $request): void
    {
        $locale = null;
        if (LocalePrefix::active($request)) {
            $locale = LocalizationService::dhivehiEnabled() ? LocalePrefix::PREFIX : null;
        } elseif ($request->hasSession() && $request->session()->isStarted() && $request->session()->has('locale')) {
            $locale = (string) $request->session()->get('locale');
        }

        if ($locale !== null && in_array($locale, LocalizationService::getAvailableLocales(), true)) {
            App::setLocale($locale);
            Carbon::setLocale($locale);
        }
    }

    /**
     * Get locale from request
     */
    private function getLocale(Request $request): string
    {
        // Priority order: /dv prefix > URL parameter > Session (user's choice) > Header > Default

        // 0. The language in the URL (/dv/...) wins over everything
        if (LocalePrefix::active($request)) {
            return LocalePrefix::PREFIX;
        }

        // 1. Check URL parameter (e.g., ?locale=en)
        // (a string only: ?locale[]=x is ignored rather than a 500)
        if (is_string($fromUrl = $request->get('locale')) && $fromUrl !== '') {
            return $fromUrl;
        }

        // 2. The language the user chose (locale switcher) wins over the browser's
        if (is_string($chosen = $request->session()->get('locale'))) {
            return $chosen;
        }

        // 3. Fall back to the browser's Accept-Language header
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            $locale = $this->parseAcceptLanguage($acceptLanguage);
            if ($locale) {
                return $locale;
            }
        }

        // 4. Return default locale
        return LocalizationService::getFallbackLocale();
    }

    /**
     * Parse Accept-Language header
     */
    private function parseAcceptLanguage(string $acceptLanguage): ?string
    {
        $availableLocales = LocalizationService::getAvailableLocales();

        // Parse the Accept-Language header
        $languages = [];
        foreach (explode(',', $acceptLanguage) as $lang) {
            $parts = explode(';', trim($lang));
            $locale = trim($parts[0]);
            $quality = isset($parts[1]) ? (float) str_replace('q=', '', $parts[1]) : 1.0;
            $languages[$locale] = $quality;
        }

        // Sort by quality
        arsort($languages);

        // Find the first matching locale
        foreach ($languages as $locale => $quality) {
            // Check exact match
            if (in_array($locale, $availableLocales)) {
                return $locale;
            }

            // Check language code match (e.g., 'en' matches 'en-US')
            $languageCode = substr($locale, 0, 2);
            if (in_array($languageCode, $availableLocales)) {
                return $languageCode;
            }
        }

        return null;
    }
}
