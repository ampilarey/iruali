<?php

use App\Http\Controllers\Customer\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Installable app: the offline fallback page and browser push subscriptions
|--------------------------------------------------------------------------
*/

// Shown by the service worker (public/sw.js) when a page can't be loaded
Route::view('/offline', 'pages.offline')->name('offline');

Route::middleware('auth')->group(function () {
    Route::post('/account/push', [PushSubscriptionController::class, 'store'])->name('account.push.store')->middleware('throttle:20,1');
    Route::delete('/account/push', [PushSubscriptionController::class, 'destroy'])->name('account.push.destroy')->middleware('throttle:20,1');
});
