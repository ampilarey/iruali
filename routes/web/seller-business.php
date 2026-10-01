<?php

use App\Http\Controllers\Admin\PayoutBatchController;
use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Seller\SettingsController;
use Illuminate\Support\Facades\Route;

// "Shops can run a business": bank details, payout batches, onboarding, notifications, help, performance, seller terms.

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/settings/bank', [SettingsController::class, 'bank'])->name('settings.bank');
    Route::put('/settings/bank', [SettingsController::class, 'updateBank'])->name('settings.bank.update')->middleware('throttle:10,1');
    Route::get('/settings/notifications', [SettingsController::class, 'notifications'])->name('settings.notifications');
    Route::put('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications.update');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/sellers/{seller}/bank/verify', [PayoutController::class, 'toggleBankVerified'])->name('sellers.bank.verify');

    // Payout batches (one bank bulk transfer for several shops)
    Route::get('/payout-batches', [PayoutBatchController::class, 'index'])->name('payout-batches.index');
    Route::get('/payout-batches/new', [PayoutBatchController::class, 'create'])->name('payout-batches.create');
    Route::post('/payout-batches', [PayoutBatchController::class, 'store'])->name('payout-batches.store');
    Route::get('/payout-batches/{batch}', [PayoutBatchController::class, 'show'])->name('payout-batches.show');
    Route::get('/payout-batches/{batch}/file', [PayoutBatchController::class, 'download'])->name('payout-batches.file');
    Route::post('/payout-batches/{batch}/paid', [PayoutBatchController::class, 'markPaid'])->name('payout-batches.paid');
    Route::post('/payout-batches/{batch}/cancel', [PayoutBatchController::class, 'cancel'])->name('payout-batches.cancel');
});
