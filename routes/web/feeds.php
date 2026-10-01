<?php

use App\Http\Controllers\FeedController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Product feeds: Google Merchant Center (RSS) and the Facebook catalogue (CSV)
|--------------------------------------------------------------------------
| Both need ?token=<feed_token setting>. Admin → Settings shows the full URLs.
*/

Route::get('/feeds/google-merchant.xml', [FeedController::class, 'googleMerchant'])->name('feeds.google-merchant');
Route::get('/feeds/facebook-catalog.csv', [FeedController::class, 'facebookCatalog'])->name('feeds.facebook-catalog');
