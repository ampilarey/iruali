<?php

use App\Http\Controllers\Customer\GuestCheckoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest checkout (Admin → Settings → "Allow checkout without an account")
|--------------------------------------------------------------------------
| The order pages are reached only through signed links that carry the order's guest token.
*/

Route::get('/checkout/guest', [GuestCheckoutController::class, 'index'])->name('checkout.guest');
Route::post('/checkout/guest', [GuestCheckoutController::class, 'store'])->name('checkout.guest.store')->middleware('throttle:10,1');

Route::middleware('signed')->prefix('orders/guest/{order}/{token}')->name('guest.orders.')->group(function () {
    Route::get('/', [GuestCheckoutController::class, 'show'])->name('show');
    Route::get('/receipt', [GuestCheckoutController::class, 'receipt'])->name('receipt');
    Route::post('/pay', [GuestCheckoutController::class, 'pay'])->name('pay')->middleware('throttle:10,1');
});
