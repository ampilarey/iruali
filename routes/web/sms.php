<?php

use App\Http\Controllers\Admin\SmsController;
use Illuminate\Support\Facades\Route;

// Admin → SMS: the log of text messages and a test send
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/sms', [SmsController::class, 'index'])->name('sms');
    Route::post('/sms/test', [SmsController::class, 'test'])->name('sms.test')->middleware('throttle:10,1');
});
