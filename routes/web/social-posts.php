<?php

use App\Http\Controllers\Admin\SocialPostController;
use App\Http\Controllers\Customer\ShareController;
use Illuminate\Support\Facades\Route;

// Admin → Social posts (admins only: admin.*)
Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/social', [SocialPostController::class, 'index'])->name('social');
    Route::post('/social', [SocialPostController::class, 'store'])->name('social.store');
    Route::put('/social/settings', [SocialPostController::class, 'settings'])->name('social.settings');
    Route::put('/social/{post}', [SocialPostController::class, 'update'])->name('social.update');
    Route::post('/social/{post}/approve', [SocialPostController::class, 'approve'])->name('social.approve');
    Route::post('/social/{post}/reject', [SocialPostController::class, 'reject'])->name('social.reject');
});

// A customer shares a product: counted, then on to the network (copy link is counted from the page)
Route::get('/share/{product}/{network}', [ShareController::class, 'go'])->name('share.go')->where('network', 'whatsapp|viber|facebook|telegram')->middleware('throttle:30,1,share.go');
Route::post('/share/{product}/copy', [ShareController::class, 'copied'])->name('share.copied')->middleware('throttle:30,1,share.copied');

// Admin → Home page: the home page's blocks, draft and publish (admins only: admin.*)
Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/home-page', [\App\Http\Controllers\Admin\HomePageController::class, 'edit'])->name('home-page');
    Route::post('/home-page/blocks', [\App\Http\Controllers\Admin\HomePageController::class, 'store'])->name('home-page.store');
    Route::put('/home-page/blocks/{id}', [\App\Http\Controllers\Admin\HomePageController::class, 'update'])->name('home-page.update')->where('id', '[a-z0-9_]+');
    Route::post('/home-page/blocks/{id}/move', [\App\Http\Controllers\Admin\HomePageController::class, 'move'])->name('home-page.move')->where('id', '[a-z0-9_]+');
    Route::post('/home-page/publish', [\App\Http\Controllers\Admin\HomePageController::class, 'publish'])->name('home-page.publish');
});
