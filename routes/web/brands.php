<?php

use App\Http\Controllers\Admin\BrandController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin → Brands: tidy the shared brand list (names, logos, descriptions), merge duplicates
|--------------------------------------------------------------------------
| The storefront brand pages (/brands, /brands/{slug}) are in routes/web.php with the other
| pages that also exist under /dv.
*/

Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/brands', [BrandController::class, 'index'])->name('brands');
    Route::get('/brands/{brand:id}/edit', [BrandController::class, 'edit'])->name('brands.edit');
    Route::put('/brands/{brand:id}', [BrandController::class, 'update'])->name('brands.update');
    Route::post('/brands/{brand:id}/review', [BrandController::class, 'review'])->name('brands.review');
    Route::post('/brands/{brand:id}/merge', [BrandController::class, 'merge'])->name('brands.merge');
    Route::delete('/brands/{brand:id}', [BrandController::class, 'destroy'])->name('brands.destroy');
});
