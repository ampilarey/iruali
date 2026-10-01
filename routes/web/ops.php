<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ErrorEventController;
use App\Http\Controllers\Admin\StaffRoleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Operations: error tracking, audit log, staff roles, admin inbox
|--------------------------------------------------------------------------
| Loaded inside the web + SetLocale middleware (see bootstrap/app.php). Same gate as the
| admin group in routes/web.php: staff only, routes per role in config/staff.php, 2FA on.
*/

Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/errors', [ErrorEventController::class, 'index'])->name('errors');
    Route::get('/errors/{error}', [ErrorEventController::class, 'show'])->name('errors.show');
    Route::post('/errors/{error}/resolve', [ErrorEventController::class, 'resolve'])->name('errors.resolve');

    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit');

    Route::post('/users/{user}/role', [StaffRoleController::class, 'update'])->name('users.role');
});
