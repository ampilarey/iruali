<?php

use App\Http\Controllers\Seller\DiscountCodeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shop deals: Seller Centre → Discount codes (the shop's own codes, the shop pays)
|--------------------------------------------------------------------------
| Multi-buy offers are set on the product form. The cart's "Shop code" box is in routes/web.php
| with the other cart routes (it also exists under /dv).
*/

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/discounts', [DiscountCodeController::class, 'index'])->name('discounts');
    Route::get('/discounts/new', [DiscountCodeController::class, 'create'])->name('discounts.create');
    Route::post('/discounts', [DiscountCodeController::class, 'store'])->name('discounts.store');
    Route::get('/discounts/{discount}/edit', [DiscountCodeController::class, 'edit'])->name('discounts.edit')->whereNumber('discount');
    Route::put('/discounts/{discount}', [DiscountCodeController::class, 'update'])->name('discounts.update')->whereNumber('discount');
    Route::post('/discounts/{discount}/toggle', [DiscountCodeController::class, 'toggle'])->name('discounts.toggle')->whereNumber('discount');
});
