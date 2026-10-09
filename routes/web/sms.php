<?php

use App\Http\Controllers\Admin\SmsController;
use App\Http\Controllers\Customer\AuthController;
use App\Http\Controllers\Customer\NotificationPreferencesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Phone verification by SMS (the code itself is checked by auth.verify.phone.otp in routes/web.php)
    Route::post('/auth/send/phone/otp', [AuthController::class, 'sendPhoneOTP'])->name('auth.send.phone.otp')->middleware('throttle:10,1');

    // My Account → Notifications: email, SMS or both per kind of message
    Route::get('/account/notifications', [NotificationPreferencesController::class, 'edit'])->name('account.notifications');
    Route::put('/account/notifications', [NotificationPreferencesController::class, 'update'])->name('account.notifications.update');
});

// Admin → SMS: the log of text messages and a test send
Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/sms', [SmsController::class, 'index'])->name('sms');
    Route::post('/sms/test', [SmsController::class, 'test'])->name('sms.test')->middleware('throttle:10,1');
});
