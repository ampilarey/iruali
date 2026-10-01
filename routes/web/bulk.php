<?php

use App\Http\Controllers\Seller\BulkProductController;
use App\Http\Controllers\Seller\ProductImportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bulk product tools for shops: CSV export/import, bulk edit, duplicate
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:seller'])->prefix('seller/products')->name('seller.products.')->group(function () {
    Route::get('/export.csv', [ProductImportController::class, 'export'])->name('export');
    Route::get('/import', [ProductImportController::class, 'form'])->name('import');
    Route::get('/import/sample.csv', [ProductImportController::class, 'sample'])->name('import.sample');
    Route::post('/import/preview', [ProductImportController::class, 'preview'])->name('import.preview')->middleware('throttle:20,1');
    Route::post('/import/apply', [ProductImportController::class, 'apply'])->name('import.apply')->middleware('throttle:20,1');

    Route::post('/bulk', [BulkProductController::class, 'confirm'])->name('bulk');
    Route::post('/bulk/apply', [BulkProductController::class, 'apply'])->name('bulk.apply');
    Route::post('/{product}/duplicate', [BulkProductController::class, 'duplicate'])->name('duplicate');
});
