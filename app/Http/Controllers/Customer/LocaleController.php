<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request)
    {
        $locale = $request->input('locale', 'en');
        if (!in_array($locale, ['en', 'dv'])) {
            $locale = 'en';
        }
        session(['locale' => $locale]);
        $redirect = $request->input('redirect', url('/'));
        return redirect($redirect)->withCookie(cookie('locale', $locale, 60 * 24 * 30)); // 30 days
    }
} 