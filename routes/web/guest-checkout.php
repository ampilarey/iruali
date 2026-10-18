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
Route::post('/checkout/guest', [GuestCheckoutController::class, 'store'])->name('checkout.guest.store')->middleware('throttle:10,1,checkout.guest.store');

Route::middleware('signed')->prefix('orders/guest/{order}/{token}')->name('guest.orders.')->group(function () {
    Route::get('/', [GuestCheckoutController::class, 'show'])->name('show');
    Route::get('/receipt', [GuestCheckoutController::class, 'receipt'])->name('receipt');
    Route::get('/receipt.pdf', [GuestCheckoutController::class, 'receiptPdf'])->name('receipt-pdf')->middleware('throttle:10,1,guest.orders.receipt-pdf');
    Route::post('/receipt/email', [GuestCheckoutController::class, 'emailReceipt'])->name('receipt-email')->middleware('throttle:3,10,guest.orders.receipt-email');
    Route::get('/live', [GuestCheckoutController::class, 'live'])->name('live')->middleware('throttle:60,1,guest.orders.live');
    Route::post('/pay', [GuestCheckoutController::class, 'pay'])->name('pay')->middleware('throttle:10,1,pay');
});
