<?php

use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Customer\BusinessDetailsController;
use App\Http\Controllers\Seller\TaxSettingsController;
use App\Http\Controllers\TaxInvoiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GST-ready: tax settings, invoices, the monthly GST report, business buyers
|--------------------------------------------------------------------------
| Logic in App\Services\GstService and GstReportService; see docs/FEATURES.md → "Tax (GST-ready)".
*/

// Customers: business details for invoices, and each shop's invoice for their own paid orders
Route::middleware('auth')->group(function () {
    Route::put('/account/business', [BusinessDetailsController::class, 'update'])->name('account.business.update');
    Route::delete('/account/business', [BusinessDetailsController::class, 'destroy'])->name('account.business.destroy');
    Route::get('/orders/{order}/invoices/{part}', [TaxInvoiceController::class, 'customer'])->name('orders.invoice')->whereNumber('part');
});

// Guests: the signed order link with the order's token
Route::get('/orders/guest/{order}/{token}/invoices/{part}', [TaxInvoiceController::class, 'guest'])->name('guest.orders.invoice')->middleware('signed')->whereNumber('part');

// Shops: tax details, their invoice for each paid order, iruali's commission invoice for each payout
Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/settings/tax', [TaxSettingsController::class, 'edit'])->name('settings.tax');
    Route::put('/settings/tax', [TaxSettingsController::class, 'update'])->name('settings.tax.update')->middleware('throttle:10,1');
    Route::get('/orders/{order}/invoice', [TaxInvoiceController::class, 'seller'])->name('orders.invoice');
    Route::get('/payouts/{payout}/invoice', [TaxInvoiceController::class, 'sellerCommission'])->name('payouts.invoice');
});

// Staff: iruali's GST settings and monthly report (admin, finance), invoices from the order and payout pages
Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/tax', [TaxController::class, 'edit'])->name('tax');
    Route::put('/tax', [TaxController::class, 'update'])->name('tax.update');
    Route::get('/tax/report', [TaxController::class, 'report'])->name('tax.report');
    Route::get('/orders/{order}/invoices/{part}', [TaxInvoiceController::class, 'admin'])->name('orders.invoice')->whereNumber('part');
    Route::get('/payouts/{payout}/invoice', [TaxInvoiceController::class, 'adminCommission'])->name('payouts.invoice');
});
