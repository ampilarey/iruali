<?php

use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

// Order threads between the customer, the shop and iruali support
Route::middleware('auth')->group(function () {
    Route::post('/orders/{order}/parts/{part}/messages', [MessageController::class, 'storeAsCustomer'])->name('orders.messages.store')->middleware('throttle:20,1');
    Route::get('/messages/{message}/attachment', [MessageController::class, 'attachment'])->name('messages.attachment');
});

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::post('/orders/{order}/parts/{part}/messages', [MessageController::class, 'storeAsSeller'])->name('orders.messages.store')->middleware('throttle:20,1');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/messages', [\App\Http\Controllers\Admin\MessageInboxController::class, 'index'])->name('messages');
    Route::post('/orders/{order}/parts/{part}/messages', [MessageController::class, 'storeAsAdmin'])->name('orders.messages.store')->middleware('throttle:60,1');
    Route::post('/conversations/{conversation}/status', [\App\Http\Controllers\Admin\MessageInboxController::class, 'status'])->name('conversations.status');
});
