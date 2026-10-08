<?php

use App\Http\Controllers\Admin\BrandSellerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin → Brands → Authorised sellers: shops iruali has confirmed as authorised sellers of a brand
|--------------------------------------------------------------------------
| The same admin group as routes/web/brands.php: staff only, two-step sign-in, and admin.brands.*
| is open to the admin role alone (config/staff.php).
*/

Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/brands/{brand:id}/sellers', [BrandSellerController::class, 'store'])->name('brands.sellers.store');
    // Any shop, not one looked up through the brand (an authorisation may outlive the shop's listings)
    Route::delete('/brands/{brand:id}/sellers/{seller}', [BrandSellerController::class, 'destroy'])->name('brands.sellers.destroy')->withoutScopedBindings();
});
