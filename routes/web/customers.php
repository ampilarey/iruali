<?php

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerReportController;
use App\Http\Controllers\Customer\AccountDeletionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customers: the staff customer page, customer reports, deleting an account
|--------------------------------------------------------------------------
| Admin routes sit behind the same gate as the admin group (config/staff.php says which role
| opens which); merging, erasing and changing a phone stay with admins.
*/

Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/customers/{user}', [CustomerController::class, 'show'])->name('customers.show');
    Route::put('/customers/{user}/tags', [CustomerController::class, 'tags'])->name('customers.tags');
    Route::post('/customers/{user}/notes', [CustomerController::class, 'note'])->name('customers.notes');
    Route::put('/customers/{user}/phone', [CustomerController::class, 'phone'])->name('customers.phone');
    Route::post('/customers/{user}/merge', [CustomerController::class, 'merge'])->name('customers.merge');
    Route::post('/customers/{user}/erase', [CustomerController::class, 'erase'])->name('customers.erase');

    Route::get('/customer-report', [CustomerReportController::class, 'index'])->name('customer-report');
    Route::get('/customer-report/export', [CustomerReportController::class, 'export'])->name('customer-report.export');
});

Route::middleware('auth')->group(function () {
    Route::delete('/account', [AccountDeletionController::class, 'destroy'])->name('account.delete')->middleware('throttle:5,10,account.delete');
});
