<?php

use App\Http\Controllers\Admin\WebhookController;
use Illuminate\Support\Facades\Route;

// Admin → Webhooks (admins only: admin.*)
Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/webhooks', [WebhookController::class, 'index'])->name('webhooks');
    Route::post('/webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
    Route::post('/webhooks/{endpoint}/test', [WebhookController::class, 'test'])->name('webhooks.test')->middleware('throttle:10,1,webhooks.test');
    Route::post('/webhooks/{endpoint}/toggle', [WebhookController::class, 'toggle'])->name('webhooks.toggle');
    Route::delete('/webhooks/{endpoint}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
});
