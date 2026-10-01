<?php

use App\Http\Controllers\Admin\WalletAdminController;
use App\Http\Controllers\Customer\GiftCardController;
use App\Http\Controllers\Customer\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wallet (store credit) and gift cards
|--------------------------------------------------------------------------
*/

Route::get('/gift-cards', [GiftCardController::class, 'index'])->name('gift-cards');

Route::middleware('auth')->group(function () {
    Route::post('/gift-cards', [GiftCardController::class, 'store'])->name('gift-cards.store')->middleware('throttle:10,1');
    Route::get('/account/wallet', [WalletController::class, 'index'])->name('account.wallet');
    Route::post('/account/wallet/redeem', [WalletController::class, 'redeem'])->name('account.wallet.redeem')->middleware('throttle:10,1');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/gift-cards', [WalletAdminController::class, 'giftCards'])->name('gift-cards');
    Route::post('/gift-cards/{giftCard}/cancel', [WalletAdminController::class, 'cancelGiftCard'])->name('gift-cards.cancel');
    Route::post('/orders/{order}/refund-to-wallet', [WalletAdminController::class, 'refundOrderToWallet'])->name('orders.refund-wallet');
    Route::post('/returns/{return}/refund-to-wallet', [WalletAdminController::class, 'refundReturnToWallet'])->name('returns.refund-wallet');
});
