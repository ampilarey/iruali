<?php

use App\Http\Controllers\Admin\DemoDataController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin → Settings → Sample data: take the demo shops and products off the site, or put them back
|--------------------------------------------------------------------------
| Full admins only. Support and finance have no admin.sample-data* routes in config/staff.php,
| and role:admin says so again here. The same is done on the server with php artisan demo:remove
| and demo:restore (App\Services\DemoDataService).
*/

Route::middleware(['auth', 'staff', 'staff.2fa', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/sample-data', [DemoDataController::class, 'index'])->name('sample-data');
    Route::post('/sample-data/remove', [DemoDataController::class, 'remove'])->name('sample-data.remove');
    Route::post('/sample-data/restore', [DemoDataController::class, 'restore'])->name('sample-data.restore');
});
