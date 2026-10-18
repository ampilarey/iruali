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

    // Admin → Team alerts: events to the Telegram group and to staff phones (admins only)
    Route::get('/alerts', [\App\Http\Controllers\Admin\TeamAlertsController::class, 'index'])->name('alerts');
    Route::put('/alerts', [\App\Http\Controllers\Admin\TeamAlertsController::class, 'update'])->name('alerts.update');
    Route::post('/alerts/test', [\App\Http\Controllers\Admin\TeamAlertsController::class, 'test'])->name('alerts.test')->middleware('throttle:10,1,alerts.test');
    Route::post('/alerts/find-chats', [\App\Http\Controllers\Admin\TeamAlertsController::class, 'findChats'])->name('alerts.find-chats')->middleware('throttle:10,1,alerts.find-chats');
    Route::post('/sms/test', [SmsController::class, 'test'])->name('sms.test')->middleware('throttle:10,1,sms.test');
    Route::put('/sms/settings', [SmsController::class, 'updateSettings'])->name('sms.settings');
    Route::put('/sms/types/{type}', [SmsController::class, 'updateType'])->name('sms.types.update')->where('type', '[a-z_]+');
    Route::post('/sms/types/{type}/test', [SmsController::class, 'testType'])->name('sms.types.test')->where('type', '[a-z_]+')->middleware('throttle:10,1,sms.types.test');
    Route::get('/sms/export', [SmsController::class, 'export'])->name('sms.export');
    Route::post('/sms/messages/{message}/resend', [SmsController::class, 'resend'])->name('sms.resend')->middleware('throttle:20,1,sms.resend');

    // Admin → SMS campaigns (admins only: admin.*)
    Route::get('/sms/campaigns', [\App\Http\Controllers\Admin\SmsCampaignController::class, 'index'])->name('sms.campaigns');
    Route::post('/sms/campaigns', [\App\Http\Controllers\Admin\SmsCampaignController::class, 'store'])->name('sms.campaigns.store');
    Route::get('/sms/campaigns/{campaign}', [\App\Http\Controllers\Admin\SmsCampaignController::class, 'show'])->name('sms.campaigns.show');
    Route::post('/sms/campaigns/{campaign}/test', [\App\Http\Controllers\Admin\SmsCampaignController::class, 'test'])->name('sms.campaigns.test')->middleware('throttle:10,1,sms.campaigns.test');
    Route::post('/sms/campaigns/{campaign}/send', [\App\Http\Controllers\Admin\SmsCampaignController::class, 'send'])->name('sms.campaigns.send');
    Route::post('/sms/campaigns/{campaign}/cancel', [\App\Http\Controllers\Admin\SmsCampaignController::class, 'cancel'])->name('sms.campaigns.cancel');
});

// The stop link at the end of a campaign SMS (App\Support\SmsOptOut)
Route::get('/stop/{code}', [\App\Http\Controllers\Customer\SmsStopController::class, 'show'])->name('sms.stop')->middleware('throttle:20,1,sms.stop');
Route::post('/stop/{code}', [\App\Http\Controllers\Customer\SmsStopController::class, 'store'])->name('sms.stop.store')->middleware('throttle:20,1,sms.stop.store');
