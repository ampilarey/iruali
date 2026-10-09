<?php

use App\Http\Controllers\Seller\HolidayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shops customers can trust: business verification and holiday mode
|--------------------------------------------------------------------------
| Shops send their business registration certificate and the owner's ID card (Seller Centre →
| Settings, or when applying); admins check them (Admin → Verifications) and approved shops show
| "Verified business". The documents are on the private disk and only these routes serve them: the
| admin one (staff stack; admin.verifications.* is open to the admin role alone, config/staff.php)
| and the shop's own one, which has no id in it.
| Holiday mode keeps a shop's products listed while nobody can order them.
*/

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/settings/holiday', [HolidayController::class, 'show'])->name('settings.holiday');
    Route::put('/settings/holiday', [HolidayController::class, 'update'])->name('settings.holiday.update')->middleware('throttle:20,1');
});
