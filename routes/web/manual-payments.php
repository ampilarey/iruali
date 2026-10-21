<?php

use App\Http\Controllers\ManualPaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bank transfer and cash on delivery (Admin → Settings → Payment methods)
|--------------------------------------------------------------------------
*/

Route::post('/orders/{order}/payment-slip', [ManualPaymentController::class, 'upload'])->name('orders.payment-slip')->middleware(['auth', 'throttle:10,1,orders.payment-slip']);

Route::post('/orders/guest/{order}/{token}/payment-slip', [ManualPaymentController::class, 'guestUpload'])->name('guest.orders.payment-slip')->middleware(['signed', 'throttle:10,1,guest.orders.payment-slip']);

// Payment link (App\Support\PayLink): a signed page to pay an unpaid order by card or bank transfer, no sign-in
Route::middleware('signed')->prefix('pay/{order}')->name('pay.')->whereNumber('order')->group(function () {
    Route::get('/', [\App\Http\Controllers\Customer\PayLinkController::class, 'show'])->name('show');
    Route::post('/card', [\App\Http\Controllers\Customer\PayLinkController::class, 'card'])->name('card')->middleware('throttle:10,1,pay.card');
    Route::post('/slip', [\App\Http\Controllers\Customer\PayLinkController::class, 'slip'])->name('slip')->middleware('throttle:10,1,pay.slip');
});

// Confirming a payment is money: admins and finance staff (config/staff.php)
Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/orders/{order}/payment-slip', [ManualPaymentController::class, 'slip'])->name('orders.payment-slip');
    Route::post('/orders/{order}/confirm-payment', [ManualPaymentController::class, 'confirm'])->name('orders.confirm-payment');
    Route::post('/orders/{order}/payment-link', [\App\Http\Controllers\Admin\PaymentLinkController::class, 'send'])->name('orders.payment-link')->middleware('throttle:10,1,admin.orders.payment-link');
    // A pre-order with a booking fee: more time to pay the balance (App\Services\BookingFeeService)
    Route::post('/orders/{order}/balance-due', [\App\Http\Controllers\Admin\BookingFeeController::class, 'moveDueDate'])->name('orders.balance-due')->middleware('throttle:10,1,admin.orders.balance-due');
});
