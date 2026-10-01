<?php

use App\Http\Controllers\Admin\ErrorEventController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Operations: error tracking, audit log, admin inbox
|--------------------------------------------------------------------------
| Loaded inside the web + SetLocale middleware (see bootstrap/app.php).
*/

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/errors', [ErrorEventController::class, 'index'])->name('errors');
    Route::get('/errors/{error}', [ErrorEventController::class, 'show'])->name('errors.show');
    Route::post('/errors/{error}/resolve', [ErrorEventController::class, 'resolve'])->name('errors.resolve');
});
