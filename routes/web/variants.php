<?php

use App\Http\Controllers\Seller\StockController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Variants: the shop's stock page (products and their variants in one table)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/stock', [StockController::class, 'index'])->name('stock');
    Route::put('/stock', [StockController::class, 'update'])->name('stock.update');
});
