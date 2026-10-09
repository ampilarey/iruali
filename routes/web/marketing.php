<?php

use App\Http\Controllers\Customer\MarketingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketing: abandoned-cart email opt-out, campaigns and the referral programme
|--------------------------------------------------------------------------
*/

// Signed one-click opt-out from the footer of a marketing email (works without signing in)
Route::get('/marketing/unsubscribe/{user}', [MarketingController::class, 'unsubscribe'])->name('marketing.unsubscribe')->middleware('signed')->whereNumber('user');

Route::middleware('auth')->group(function () {
    Route::put('/account/marketing', [MarketingController::class, 'update'])->name('account.marketing');
    Route::get('/account/rewards', [\App\Http\Controllers\Customer\RewardsController::class, 'index'])->name('account.rewards');
});

// A shared referral link: remembers the code for 30 days, then shows the home page
Route::get('/r/{code}', [\App\Http\Controllers\Customer\RewardsController::class, 'visit'])->name('referral.visit')->where('code', '[A-Za-z0-9]{4,20}');

Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/rewards', [\App\Http\Controllers\Admin\RewardsReportController::class, 'index'])->name('rewards');
});

// Campaign landing pages (sales and events)
Route::get('/campaigns', [\App\Http\Controllers\Customer\CampaignController::class, 'index'])->name('campaigns.index');
Route::get('/campaigns/{campaign}', [\App\Http\Controllers\Customer\CampaignController::class, 'show'])->name('campaigns.show');

// Shops join campaigns with their products
Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/campaigns', [\App\Http\Controllers\Seller\CampaignController::class, 'index'])->name('campaigns');
    Route::get('/campaigns/{campaign}', [\App\Http\Controllers\Seller\CampaignController::class, 'show'])->name('campaigns.show');
    Route::post('/campaigns/{campaign}', [\App\Http\Controllers\Seller\CampaignController::class, 'store'])->name('campaigns.store');
    Route::delete('/campaigns/{campaign}/products/{participation}', [\App\Http\Controllers\Seller\CampaignController::class, 'destroy'])->name('campaigns.leave');
});

// Admin → Campaigns
Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('campaigns', \App\Http\Controllers\Admin\CampaignController::class)->except(['show']);
    Route::post('/campaigns/{campaign}/products/{participation}/approve', [\App\Http\Controllers\Admin\CampaignController::class, 'approve'])->name('campaigns.approve');
    Route::delete('/campaigns/{campaign}/products/{participation}', [\App\Http\Controllers\Admin\CampaignController::class, 'reject'])->name('campaigns.reject');
});
