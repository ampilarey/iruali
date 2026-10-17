<?php

use App\Http\Controllers\Admin\SmsController;
use App\Http\Controllers\Customer\AuthController;
use App\Http\Controllers\Customer\NotificationPreferencesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Phone verification by SMS (the code itself is checked by auth.verify.phone.otp in routes/web.php)
    Route::post('/auth/send/phone/otp', [AuthController::class, 'sendPhoneOTP'])->name('auth.send.phone.otp')->middleware('throttle:10,1,auth.send.phone.otp');

    // My Account → Notifications: email, SMS or both per kind of message
    Route::get('/account/notifications', [NotificationPreferencesController::class, 'edit'])->name('account.notifications');
    Route::put('/account/notifications', [NotificationPreferencesController::class, 'update'])->name('account.notifications.update');
});

// Admin → SMS: the control centre (all SMS on/off, monthly limit, quiet hours, each message's
// switch and wording, previews and tests) and the log
Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/sms', [SmsController::class, 'index'])->name('sms');
    Route::post('/sms/test', [SmsController::class, 'test'])->name('sms.test')->middleware('throttle:10,1,sms.test');
    Route::put('/sms/settings', [SmsController::class, 'updateSettings'])->name('sms.settings');
    Route::put('/sms/types/{type}', [SmsController::class, 'updateType'])->name('sms.types.update')->where('type', '[a-z_]+');
    Route::post('/sms/types/{type}/test', [SmsController::class, 'testType'])->name('sms.types.test')->where('type', '[a-z_]+')->middleware('throttle:10,1,sms.types.test');
    Route::get('/sms/export', [SmsController::class, 'export'])->name('sms.export');
    Route::post('/sms/messages/{message}/resend', [SmsController::class, 'resend'])->name('sms.resend')->middleware('throttle:20,1,sms.resend');
});
