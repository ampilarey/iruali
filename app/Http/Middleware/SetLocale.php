<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Get locale from session, cookie, or default to config
        $locale = session('locale')
            ?? $request->cookie('locale')
            ?? config('app.locale', 'en');
        
        // Ensure locale is valid
        $validLocales = ['en', 'dv'];
        if (!in_array($locale, $validLocales)) {
            $locale = 'en';
        }
        
        // Set the locale
        App::setLocale($locale);
        
        return $next($request);
    }
} 