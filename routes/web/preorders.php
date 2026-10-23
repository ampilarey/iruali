<?php

use App\Http\Controllers\Admin\PreorderController as AdminPreorderController;
use App\Http\Controllers\Customer\PreorderCancelController;
use App\Http\Controllers\Seller\PreorderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pre-orders: customers pay now for stock coming on a later shipment
|--------------------------------------------------------------------------
| Shops an admin allows (Admin → Sellers → the shop's page) switch them on in the product form;
| Seller Centre → Pre-orders lists the open ones, records the stock when it arrives, moves the
| expected date, and confirms (or declines) the pre-orders paid once the shop confirms. Customers
| can cancel a pre-order (the whole order, through the normal cancellation and refund) until it is
| sent: from My Orders, or a guest through the signed link in their emails. Admin → Pre-orders
| lists the late ones and those waiting for a shop to confirm.
*/

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    // A product filled from its page on an online shop abroad (ProductImportService), for shops allowed pre-orders
    Route::get('/products/from-link', [\App\Http\Controllers\Seller\ProductLinkImportController::class, 'create'])->name('products.from-link');
    Route::post('/products/from-link', [\App\Http\Controllers\Seller\ProductLinkImportController::class, 'store'])->name('products.from-link.store')->middleware('throttle:30,60,products.from-link');
    Route::get('/preorders', [PreorderController::class, 'index'])->name('preorders');
    Route::post('/preorders/{product:id}/arrived', [PreorderController::class, 'stockArrived'])->name('preorders.arrived')->whereNumber('product')->middleware('throttle:30,1,preorders.arrived');
    Route::put('/preorders/{product:id}/date', [PreorderController::class, 'moveDate'])->name('preorders.date')->whereNumber('product')->middleware('throttle:30,1,preorders.date');
    // Pre-orders paid once the shop confirms (PreorderConfirmService): "I can get it" or "I can't get it"
    Route::post('/preorders/orders/{order}/confirm', [PreorderController::class, 'confirm'])->name('preorders.confirm')->whereNumber('order')->middleware('throttle:30,1,preorders.confirm');
    Route::post('/preorders/orders/{order}/decline', [PreorderController::class, 'decline'])->name('preorders.decline')->whereNumber('order')->middleware('throttle:30,1,preorders.decline');
});

Route::middleware('auth')->group(function () {
    Route::get('/orders/{order}/preorder/cancel', [PreorderCancelController::class, 'show'])->name('orders.preorder.cancel');
    Route::post('/orders/{order}/preorder/cancel', [PreorderCancelController::class, 'store'])->name('orders.preorder.cancel.store')->middleware('throttle:10,1,orders.preorder.cancel.store');
});

Route::middleware('signed')->prefix('orders/guest/{order}/{token}')->name('guest.orders.')->group(function () {
    Route::get('/preorder/cancel', [PreorderCancelController::class, 'guestShow'])->name('preorder.cancel');
    Route::post('/preorder/cancel', [PreorderCancelController::class, 'guestStore'])->name('preorder.cancel.store')->middleware('throttle:10,1,preorder.cancel.store');
});

Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/preorders', [AdminPreorderController::class, 'index'])->name('preorders');
    // Pre-orders, and "coming soon", allowed for a shop each on its own: admins only (on no staff role's list)
    Route::post('/sellers/{seller}/preorders', [AdminPreorderController::class, 'allowShop'])->name('sellers.preorders')->whereNumber('seller');
    Route::post('/sellers/{seller}/coming-soon', [AdminPreorderController::class, 'allowComingSoon'])->name('sellers.coming-soon')->whereNumber('seller');
    // A pre-order paid once the shop confirms, confirmed or declined for the shop
    Route::post('/orders/{order}/preorder-confirm', [AdminPreorderController::class, 'confirmOrder'])->name('orders.preorder-confirm')->whereNumber('order');
    Route::post('/orders/{order}/preorder-decline', [AdminPreorderController::class, 'declineOrder'])->name('orders.preorder-decline')->whereNumber('order');
});
