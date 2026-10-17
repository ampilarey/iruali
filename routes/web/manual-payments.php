<?php

use App\Http\Controllers\ManualPaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bank transfer and cash on delivery (Admin → Settings → Payment methods)
|--------------------------------------------------------------------------
*/

Route::post('/orders/{order}/payment-slip', [ManualPaymentController::class, 'upload'])->name('orders.payment-slip')->middleware(['auth', 'throttle:10,1']);

Route::post('/orders/guest/{order}/{token}/payment-slip', [ManualPaymentController::class, 'guestUpload'])->name('guest.orders.payment-slip')->middleware(['signed', 'throttle:10,1']);

// Confirming a payment is money: admins and finance staff (config/staff.php)
Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/orders/{order}/payment-slip', [ManualPaymentController::class, 'slip'])->name('orders.payment-slip');
    Route::post('/orders/{order}/confirm-payment', [ManualPaymentController::class, 'confirm'])->name('orders.confirm-payment');
});
