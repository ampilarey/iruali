<?php

use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

// Delivery details (courier / tracking / boat or flight / expected date) on a shop's part of an order
Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::post('/orders/{order}/parts/{part}/tracking', [TrackingController::class, 'updateAsSeller'])->name('orders.tracking');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/orders/{order}/parts/{part}/tracking', [TrackingController::class, 'updateAsAdmin'])->name('orders.parts.tracking');
});
