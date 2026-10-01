<?php

use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Seller\SettingsController;
use Illuminate\Support\Facades\Route;

// "Shops can run a business": bank details, payout batches, onboarding, notifications, help, performance, seller terms.

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/settings/bank', [SettingsController::class, 'bank'])->name('settings.bank');
    Route::put('/settings/bank', [SettingsController::class, 'updateBank'])->name('settings.bank.update')->middleware('throttle:10,1');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/sellers/{seller}/bank/verify', [PayoutController::class, 'toggleBankVerified'])->name('sellers.bank.verify');
});
