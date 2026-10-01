<?php

use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Seller\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reviews: the shop's replies (Seller Centre) and admin moderation of replies
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews');
    Route::post('/reviews/{review}/reply', [ReviewController::class, 'reply'])->name('reviews.reply')->middleware('throttle:30,1');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::delete('/reviews/{review}/reply', [ModerationController::class, 'removeReply'])->name('reviews.reply.destroy');
});
