<?php

use App\Http\Controllers\Admin\ComplaintController as AdminComplaintController;
use App\Http\Controllers\Customer\ComplaintController;
use App\Http\Controllers\Customer\OrderFeedbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Feedback and complaints: rating an order, the complaint form, Admin → Complaints
|--------------------------------------------------------------------------
*/

// Signed links (delivered email, order page), so guests can rate without signing in
Route::get('/feedback/{order}', [OrderFeedbackController::class, 'show'])->name('orders.feedback')->middleware('signed');
Route::post('/feedback/{order}', [OrderFeedbackController::class, 'store'])->name('orders.feedback.store')->middleware(['signed', 'throttle:10,1,orders.feedback.store']);

Route::get('/complaints/new', [ComplaintController::class, 'create'])->name('complaints.create');
Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store')->middleware('throttle:5,60,complaints.store');

Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/complaints', [AdminComplaintController::class, 'index'])->name('complaints');
    Route::get('/complaints/{complaint}', [AdminComplaintController::class, 'show'])->name('complaints.show');
    Route::put('/complaints/{complaint}', [AdminComplaintController::class, 'update'])->name('complaints.update');
    Route::get('/complaints/{complaint}/photos/{index}', [AdminComplaintController::class, 'photo'])->name('complaints.photo')->whereNumber('index');
});
