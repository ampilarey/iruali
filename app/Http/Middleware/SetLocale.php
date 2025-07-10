<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = session('locale')
            ?? $request->cookie('locale')
            ?? $request->getPreferredLanguage(['en', 'dv'])
            ?? config('app.locale');
        \Log::info('SetLocale middleware running. Locale: ' . $locale);
        \Illuminate\Support\Facades\App::setLocale($locale);
        return $next($request);
    }
} 