<?php

use App\Http\Controllers\Admin\PreorderController as AdminPreorderController;
use App\Http\Controllers\Customer\PreorderCancelController;
use App\Http\Controllers\Seller\PreorderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pre-orders: customers pay now for stock coming on a later shipment
|--------------------------------------------------------------------------
| Shops switch them on in the product form; Seller Centre → Pre-orders lists the open ones,
| records the stock when it arrives and moves the expected date. Customers can cancel a pre-order
| (the whole order, through the normal cancellation and refund) until it is sent: from My Orders,
| or a guest through the signed link in their emails. Admin → Pre-orders lists the late ones.
*/

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/preorders', [PreorderController::class, 'index'])->name('preorders');
    Route::post('/preorders/{product:id}/arrived', [PreorderController::class, 'stockArrived'])->name('preorders.arrived')->whereNumber('product')->middleware('throttle:30,1');
    Route::put('/preorders/{product:id}/date', [PreorderController::class, 'moveDate'])->name('preorders.date')->whereNumber('product')->middleware('throttle:30,1');
});

Route::middleware('auth')->group(function () {
    Route::get('/orders/{order}/preorder/cancel', [PreorderCancelController::class, 'show'])->name('orders.preorder.cancel');
    Route::post('/orders/{order}/preorder/cancel', [PreorderCancelController::class, 'store'])->name('orders.preorder.cancel.store')->middleware('throttle:10,1');
});

Route::middleware('signed')->prefix('orders/guest/{order}/{token}')->name('guest.orders.')->group(function () {
    Route::get('/preorder/cancel', [PreorderCancelController::class, 'guestShow'])->name('preorder.cancel');
    Route::post('/preorder/cancel', [PreorderCancelController::class, 'guestStore'])->name('preorder.cancel.store')->middleware('throttle:10,1');
});

Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/preorders', [AdminPreorderController::class, 'index'])->name('preorders');
});
