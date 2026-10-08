<?php

use App\Http\Controllers\Customer\BrandFollowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| My Account → Brands you follow
|--------------------------------------------------------------------------
| The Follow / Unfollow buttons (brands.follow, brands.unfollow) are in routes/web.php with the
| other storefront actions that also work under /dv.
*/

Route::middleware('auth')->group(function () {
    Route::get('/account/brands', [BrandFollowController::class, 'index'])->name('account.brands');
});
