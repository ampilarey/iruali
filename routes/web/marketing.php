<?php

use App\Http\Controllers\Customer\MarketingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketing: abandoned-cart email opt-out, campaigns and the referral programme
|--------------------------------------------------------------------------
*/

// Signed one-click opt-out from the footer of a marketing email (works without signing in)
Route::get('/marketing/unsubscribe/{user}', [MarketingController::class, 'unsubscribe'])->name('marketing.unsubscribe')->middleware('signed')->whereNumber('user');

Route::middleware('auth')->group(function () {
    Route::put('/account/marketing', [MarketingController::class, 'update'])->name('account.marketing');
});
