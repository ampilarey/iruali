<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\LocalizationService;
use App\Support\LocaleUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LocaleController extends Controller
{
    /**
     * Switch application locale: remembers the choice in the session (and on the account) and
     * sends the visitor to the same page in that language (`to`, a path on this site).
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

        $to = $this->localPath($request->get('to'));
        if ($to === null && ($referer = $request->headers->get('referer'))) {
            // Old-style switch (no `to`): the page we came from, in the new language
            $to = $this->localPath(parse_url($referer, PHP_URL_PATH).(parse_url($referer, PHP_URL_QUERY) ? '?'.parse_url($referer, PHP_URL_QUERY) : ''));
            $to = $to !== null ? LocaleUrl::alternateOf($to, $locale) : null;
        }

        return redirect($to ?? url('/'))->with('success', 'Language changed successfully.');
    }

    /**
     * Only a path on this site may be the target (never another host).
     */
    protected function localPath(?string $to): ?string
    {
        if (! is_string($to) || $to === '' || $to[0] !== '/' || str_starts_with($to, '//') || str_starts_with($to, '/\\')) {
            return null;
        }

        return $to;
    }
}
