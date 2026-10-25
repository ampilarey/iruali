<?php

use App\Http\Controllers\Admin\ItemRequestController as AdminItemRequestController;
use App\Http\Controllers\Customer\ItemRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| "Can't find it? We'll bring it for you" (App\Services\ItemRequestService)
|--------------------------------------------------------------------------
| Customers ask for an item iruali doesn't list and follow it under My account → Item requests;
| admins answer under Admin → Item requests (admins only: the routes are not in config/staff.php).
*/

Route::middleware('auth')->group(function () {
    Route::get('/request-an-item', [ItemRequestController::class, 'create'])->name('item-requests.create');
    Route::post('/request-an-item', [ItemRequestController::class, 'store'])->name('item-requests.store')->middleware('throttle:10,60,item-requests.store');
    Route::get('/account/item-requests', [ItemRequestController::class, 'index'])->name('item-requests.index');
    Route::get('/account/item-requests/{itemRequest}', [ItemRequestController::class, 'show'])->name('item-requests.show')->whereNumber('itemRequest');
    Route::post('/account/item-requests/{itemRequest}/accept', [ItemRequestController::class, 'accept'])->name('item-requests.accept')->whereNumber('itemRequest');
    Route::post('/account/item-requests/{itemRequest}/cancel', [ItemRequestController::class, 'cancel'])->name('item-requests.cancel')->whereNumber('itemRequest');
});

Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/item-requests', [AdminItemRequestController::class, 'index'])->name('item-requests');
    Route::get('/item-requests/{itemRequest}', [AdminItemRequestController::class, 'show'])->name('item-requests.show')->whereNumber('itemRequest');
    Route::post('/item-requests/{itemRequest}/quote', [AdminItemRequestController::class, 'quote'])->name('item-requests.quote')->whereNumber('itemRequest');
    Route::post('/item-requests/{itemRequest}/decline', [AdminItemRequestController::class, 'decline'])->name('item-requests.decline')->whereNumber('itemRequest');
});
