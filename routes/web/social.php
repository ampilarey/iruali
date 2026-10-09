<?php

use App\Http\Controllers\Customer\SocialLoginController;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
|--------------------------------------------------------------------------
| Sign in with Google, Facebook and Apple (docs/SOCIAL_LOGIN.md)
|--------------------------------------------------------------------------
| The callback URLs registered with each provider are https://<site>/auth/<provider>/callback.
*/

Route::middleware(['guest', 'throttle:20,1'])->group(function () {
    Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])->name('social.redirect')->whereIn('provider', ['google', 'facebook', 'apple']);
    Route::get('/auth/{provider}/callback', [SocialLoginController::class, 'callback'])->name('social.callback')->whereIn('provider', ['google', 'facebook', 'apple']);
});

// Apple answers with a form post from appleid.apple.com. The session cookie does not come with a
// cross-site post, so only this route runs without the session, and so without CSRF (the sign-in's
// state, checked in the callback it hands over to, protects it).
Route::post('/auth/apple/callback', [SocialLoginController::class, 'applePost'])->name('social.apple.post')
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class, SetLocale::class])
    ->middleware('throttle:20,1');

// My Account → Security: connected accounts
Route::middleware('auth')->group(function () {
    Route::delete('/account/social/{account}', [SocialLoginController::class, 'unlink'])->name('account.social.unlink')->whereNumber('account');
    Route::post('/account/password/link', [SocialLoginController::class, 'passwordLink'])->name('account.password.link')->middleware('throttle:3,10');
});
