<?php

use App\Http\Controllers\Seller\ShopStaffController;
use App\Http\Controllers\ShopInvitationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shop staff: staff sign-ins for a shop's Seller Centre
|--------------------------------------------------------------------------
| Seller Centre → Staff is the owner's alone (seller.staff* is in config/shop_staff.php owner_only).
| Which seller pages each staff role opens is in config/shop_staff.php, enforced by role:seller
| (App\Http\Middleware\ShopAccess) on every seller route.
*/

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/staff', [ShopStaffController::class, 'index'])->name('staff');
    Route::post('/staff/invitations', [ShopStaffController::class, 'invite'])->name('staff.invite')->middleware('throttle:10,1');
    Route::delete('/staff/invitations/{invitation}', [ShopStaffController::class, 'revoke'])->name('staff.invitations.revoke')->whereNumber('invitation');
    Route::put('/staff/settings', [ShopStaffController::class, 'settings'])->name('staff.settings');
    Route::put('/staff/{member}', [ShopStaffController::class, 'update'])->name('staff.update')->whereNumber('member');
    Route::delete('/staff/{member}', [ShopStaffController::class, 'destroy'])->name('staff.destroy')->whereNumber('member');
});

// The emailed link (signed): it opens for anyone, signed in or not, and the same address accepts it
Route::middleware('signed')->group(function () {
    Route::get('/shop-invitations/{invitation}/{token}', [ShopInvitationController::class, 'show'])->name('shop-invitations.show')->whereNumber('invitation');
    Route::post('/shop-invitations/{invitation}/{token}', [ShopInvitationController::class, 'accept'])->name('shop-invitations.accept')->whereNumber('invitation')->middleware('throttle:10,1');
});
