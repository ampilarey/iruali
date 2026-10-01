<?php

use App\Http\Controllers\Customer\AddressController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Saved delivery addresses (My Account → Addresses)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->prefix('account/addresses')->name('account.addresses')->group(function () {
    Route::get('/', [AddressController::class, 'index']);
    Route::get('/new', [AddressController::class, 'create'])->name('.create');
    Route::post('/', [AddressController::class, 'store'])->name('.store');
    Route::get('/{address}/edit', [AddressController::class, 'edit'])->name('.edit');
    Route::put('/{address}', [AddressController::class, 'update'])->name('.update');
    Route::delete('/{address}', [AddressController::class, 'destroy'])->name('.destroy');
    Route::post('/{address}/default', [AddressController::class, 'makeDefault'])->name('.default');
});
