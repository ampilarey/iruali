<?php

use App\Http\Controllers\Admin\QuoteController as AdminQuoteController;
use App\Http\Controllers\Customer\QuoteController;
use App\Http\Controllers\Seller\QuoteController as SellerQuoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bulk quotes for businesses (App\Services\QuoteService)
|--------------------------------------------------------------------------
| Customers ask a shop for a price on a quantity ("Request a bulk quote" on the product page and
| the shop page) and follow their requests under My account → Quotes, like the other account pages
| (no /dv copy: the language follows the session). Shops reply under Seller Centre → Quote requests;
| staff see everything under Admin → Quotes (admin, and support through config/staff.php).
| Asking is limited by the "quote-requests" rate limiter, messages by "quote-messages"
| (QuoteServiceProvider).
*/

Route::middleware('auth')->group(function () {
    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/request', [QuoteController::class, 'create'])->name('quotes.create');
    Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store')->middleware('throttle:quote-requests');
    Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show')->whereNumber('quote');
    Route::post('/quotes/{quote}/accept', [QuoteController::class, 'accept'])->name('quotes.accept')->whereNumber('quote');
    Route::post('/quotes/{quote}/cart', [QuoteController::class, 'addToCart'])->name('quotes.cart')->whereNumber('quote');
    Route::post('/quotes/{quote}/decline', [QuoteController::class, 'decline'])->name('quotes.decline')->whereNumber('quote');
    Route::post('/quotes/{quote}/messages', [QuoteController::class, 'message'])->name('quotes.messages')->whereNumber('quote')->middleware('throttle:quote-messages');
});

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/quotes', [SellerQuoteController::class, 'index'])->name('quotes');
    Route::get('/quotes/{quote}', [SellerQuoteController::class, 'show'])->name('quotes.show')->whereNumber('quote');
    Route::post('/quotes/{quote}/quote', [SellerQuoteController::class, 'quote'])->name('quotes.quote')->whereNumber('quote');
    Route::post('/quotes/{quote}/decline', [SellerQuoteController::class, 'decline'])->name('quotes.decline')->whereNumber('quote');
    Route::post('/quotes/{quote}/messages', [SellerQuoteController::class, 'message'])->name('quotes.messages')->whereNumber('quote')->middleware('throttle:quote-messages');
});

Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/quotes', [AdminQuoteController::class, 'index'])->name('quotes');
    Route::get('/quotes/{quote}', [AdminQuoteController::class, 'show'])->name('quotes.show')->whereNumber('quote');
    Route::post('/quotes/{quote}/close', [AdminQuoteController::class, 'close'])->name('quotes.close')->whereNumber('quote');
    Route::post('/quotes/{quote}/messages', [AdminQuoteController::class, 'message'])->name('quotes.messages')->whereNumber('quote');
});
