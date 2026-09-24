<?php

namespace App\Http\Middleware;

use App\Services\LocalizationService;
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
        // Get locale from various sources
        $locale = $this->getLocale($request);

        // Validate locale
        if (! in_array($locale, LocalizationService::getAvailableLocales())) {
            $locale = LocalizationService::getFallbackLocale();
        }

        // Set the application locale
        App::setLocale($locale);

        return $next($request);
    }

    /**
     * Get locale from request
     */
    private function getLocale(Request $request): string
    {
        // Priority order: URL parameter > Session (user's choice) > Header > Default

        // 1. Check URL parameter (e.g., ?locale=en)
        if ($request->has('locale')) {
            return $request->get('locale');
        }

        // 2. The language the user chose (locale switcher) wins over the browser's
        if ($request->session()->has('locale')) {
            return $request->session()->get('locale');
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
