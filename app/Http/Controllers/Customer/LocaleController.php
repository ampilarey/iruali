<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\LocalizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LocaleController extends Controller
{
    /**
     * Switch application locale
     */
    public function switch(Request $request)
    {
        $locale = $request->get('locale');

        // Validate locale
        if (! in_array($locale, LocalizationService::getAvailableLocales())) {
            return back()->withErrors(['locale' => 'Invalid locale selected.']);
        }

        // Set locale in session
        session(['locale' => $locale]);

        // Remember it for signed-in users, so emails come in the same language
        if ($request->user()) {
            $request->user()->update(['preferred_language' => $locale]);
        }

        // Set application locale
        App::setLocale($locale);

        return back()->with('success', 'Language changed successfully.');
    }

    /**
     * Get current locale
     */
    public function getCurrentLocale()
    {
        return response()->json([
            'current_locale' => LocalizationService::getCurrentLocale(),
            'fallback_locale' => LocalizationService::getFallbackLocale(),
            'available_locales' => LocalizationService::getAvailableLocales(),
        ]);
    }

    /**
     * Get available locales
     */
    public function getAvailableLocales()
    {
        return response()->json([
            'locales' => LocalizationService::getAvailableLocales(),
            'current' => LocalizationService::getCurrentLocale(),
        ]);
    }
}
