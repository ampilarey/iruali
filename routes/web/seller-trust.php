<?php

use App\Http\Controllers\Admin\VerificationController as AdminVerificationController;
use App\Http\Controllers\Seller\HolidayController;
use App\Http\Controllers\Seller\VerificationController;
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
    Route::get('/settings/verification', [VerificationController::class, 'show'])->name('settings.verification');
    Route::put('/settings/verification', [VerificationController::class, 'update'])->name('settings.verification.update')->middleware('throttle:10,1');
    Route::get('/settings/verification/documents/{document}', [VerificationController::class, 'document'])->name('settings.verification.document')->whereIn('document', ['certificate', 'id_card']);
    Route::get('/settings/holiday', [HolidayController::class, 'show'])->name('settings.holiday');
    Route::put('/settings/holiday', [HolidayController::class, 'update'])->name('settings.holiday.update')->middleware('throttle:20,1');
});

Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/verifications', [AdminVerificationController::class, 'index'])->name('verifications');
    Route::put('/verifications/payout-rule', [AdminVerificationController::class, 'updatePayoutRule'])->name('verifications.payout-rule');
    Route::get('/verifications/{verification}/documents/{document}', [AdminVerificationController::class, 'document'])->name('verifications.document')->whereNumber('verification')->whereIn('document', ['certificate', 'id_card']);
    Route::post('/verifications/{verification}/approve', [AdminVerificationController::class, 'approve'])->name('verifications.approve')->whereNumber('verification');
    Route::post('/verifications/{verification}/reject', [AdminVerificationController::class, 'reject'])->name('verifications.reject')->whereNumber('verification');
});
