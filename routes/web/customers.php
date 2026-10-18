<?php

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerReportController;
use App\Http\Controllers\Customer\AccountDeletionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customers: the staff customer page, customer reports, deleting an account
|--------------------------------------------------------------------------
| Admin routes sit behind the same gate as the admin group (config/staff.php says which role
| opens which); merging, erasing and changing a phone stay with admins.
*/

Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/customers/{user}', [CustomerController::class, 'show'])->name('customers.show');
    Route::put('/customers/{user}/tags', [CustomerController::class, 'tags'])->name('customers.tags');
    Route::post('/customers/{user}/notes', [CustomerController::class, 'note'])->name('customers.notes');
    Route::put('/customers/{user}/phone', [CustomerController::class, 'phone'])->name('customers.phone');
    Route::post('/customers/{user}/merge', [CustomerController::class, 'merge'])->name('customers.merge');
    Route::post('/customers/{user}/erase', [CustomerController::class, 'erase'])->name('customers.erase');
    Route::put('/customers/{user}/credit', [CustomerController::class, 'credit'])->name('customers.credit');

    Route::get('/customer-report', [CustomerReportController::class, 'index'])->name('customer-report');
    Route::get('/customer-report/export', [CustomerReportController::class, 'export'])->name('customer-report.export');

    // Admin → Exports and Admin → Finance (admins and finance staff)
    Route::get('/exports', [\App\Http\Controllers\Admin\ExportController::class, 'index'])->name('exports');
    Route::get('/exports/{kind}', [\App\Http\Controllers\Admin\ExportController::class, 'export'])->name('exports.download')->where('kind', '[a-z_]+');
    Route::get('/finance', [\App\Http\Controllers\Admin\FinanceController::class, 'index'])->name('finance');
    Route::post('/finance/expenses', [\App\Http\Controllers\Admin\FinanceController::class, 'storeExpense'])->name('finance.expenses.store');
    Route::delete('/finance/expenses/{expense}', [\App\Http\Controllers\Admin\FinanceController::class, 'destroyExpense'])->name('finance.expenses.destroy');
    Route::get('/finance/expenses/{expense}/receipt', [\App\Http\Controllers\Admin\FinanceController::class, 'receipt'])->name('finance.expenses.receipt');
    Route::put('/finance/settings', [\App\Http\Controllers\Admin\FinanceController::class, 'settings'])->name('finance.settings');
    Route::get('/finance/export', [\App\Http\Controllers\Admin\FinanceController::class, 'exportPnl'])->name('finance.export');
    Route::get('/finance/xero/invoices', [\App\Http\Controllers\Admin\FinanceController::class, 'xeroInvoices'])->name('finance.xero.invoices');
    Route::get('/finance/xero/bills', [\App\Http\Controllers\Admin\FinanceController::class, 'xeroBills'])->name('finance.xero.bills');

    // Admin → Settlements: the BML statement matched to orders (admins and finance staff)
    Route::get('/settlements', [\App\Http\Controllers\Admin\SettlementController::class, 'index'])->name('settlements');
    Route::post('/settlements/import', [\App\Http\Controllers\Admin\SettlementController::class, 'import'])->name('settlements.import')->middleware('throttle:10,1,settlements.import');
    Route::post('/settlements/{line}/confirm', [\App\Http\Controllers\Admin\SettlementController::class, 'confirm'])->name('settlements.confirm');
    Route::post('/settlements/{line}/match', [\App\Http\Controllers\Admin\SettlementController::class, 'match'])->name('settlements.match');
    Route::post('/settlements/{line}/ignore', [\App\Http\Controllers\Admin\SettlementController::class, 'ignore'])->name('settlements.ignore');
});

Route::middleware('auth')->group(function () {
    Route::delete('/account', [AccountDeletionController::class, 'destroy'])->name('account.delete')->middleware('throttle:5,10,account.delete');
    Route::get('/account/pay-later', [\App\Http\Controllers\Customer\CreditStatementController::class, 'show'])->name('account.credit');
});
