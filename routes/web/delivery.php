<?php

use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\OrderDeliveryController as AdminOrderDeliveryController;
use App\Http\Controllers\Customer\DeliveryIslandController;
use App\Http\Controllers\Seller\DeliverySettingsController;
use App\Http\Controllers\Seller\OrderDeliveryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Delivery and checkout options
|--------------------------------------------------------------------------
| Delivery fees and transit days per area (Admin → Delivery), the product page's "Delivery to
| <island>" box, pickup from the shop, gift packing slips and Malé delivery time slots.
*/

// Shoppers: the island the product page quotes delivery for (kept in the session)
Route::post('/delivery/island', [DeliveryIslandController::class, 'store'])->name('delivery.island')->middleware('throttle:30,1');

// Shops: delivery & pickup settings, their own parts' pickup and packing slip
Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/settings/delivery', [DeliverySettingsController::class, 'edit'])->name('settings.delivery');
    Route::put('/settings/delivery', [DeliverySettingsController::class, 'update'])->name('settings.delivery.update');
    Route::post('/orders/{order}/pickup/ready', [OrderDeliveryController::class, 'readyForPickup'])->name('orders.pickup.ready')->middleware('throttle:10,1');
    Route::post('/orders/{order}/pickup/collected', [OrderDeliveryController::class, 'confirmPickup'])->name('orders.pickup.collected')->middleware('throttle:10,1');
    Route::get('/orders/{order}/packing-slip', [OrderDeliveryController::class, 'packingSlip'])->name('orders.packing-slip');
});

// Admin: the Delivery page, and pickups / packing slips on the order page
Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/delivery', [DeliveryController::class, 'index'])->name('delivery');
    Route::put('/delivery', [DeliveryController::class, 'update'])->name('delivery.update');
    Route::post('/orders/{order}/parts/{part}/collected', [AdminOrderDeliveryController::class, 'collected'])->name('orders.parts.collected');
    Route::get('/orders/{order}/parts/{part}/packing-slip', [AdminOrderDeliveryController::class, 'packingSlip'])->name('orders.parts.packing-slip');
});
