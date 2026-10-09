<?php

use Illuminate\Support\Facades\Route;

// Customers open a dispute on one shop's part of their order
Route::middleware('auth')->group(function () {
    Route::post('/orders/{order}/parts/{part}/disputes', [\App\Http\Controllers\Customer\DisputeController::class, 'store'])->name('orders.disputes.store')->middleware('throttle:5,1');
});

// Admin → Disputes
Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/disputes', [\App\Http\Controllers\Admin\DisputeController::class, 'index'])->name('disputes');
    Route::get('/disputes/{dispute}', [\App\Http\Controllers\Admin\DisputeController::class, 'show'])->name('disputes.show');
    Route::post('/disputes/{dispute}/resolve', [\App\Http\Controllers\Admin\DisputeController::class, 'resolve'])->name('disputes.resolve');
    Route::post('/disputes/{dispute}/request-info', [\App\Http\Controllers\Admin\DisputeController::class, 'requestInfo'])->name('disputes.request-info');
});
