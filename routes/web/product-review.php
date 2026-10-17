<?php

use App\Http\Controllers\Admin\ProductChangeController;
use App\Http\Controllers\Seller\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Product edits waiting for review, and trusted shops
|--------------------------------------------------------------------------
| A shop's changes to an approved product wait for an admin (App\Services\ProductReviewService);
| shoppers see the approved version meanwhile. Admin → Products → Changes waiting lists them
| (admin and catalogue staff, config/staff.php). An admin can make a shop trusted, so its new
| listings and edits go live without review (admin.sellers.auto-approve: the admin role only).
*/

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::post('/products/{product}/withdraw-changes', [ProductController::class, 'withdrawChange'])->name('products.changes.withdraw');
});

Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/product-changes', [ProductChangeController::class, 'index'])->name('product-changes');
    Route::get('/product-changes/{change}', [ProductChangeController::class, 'show'])->name('product-changes.show')->whereNumber('change');
    Route::post('/product-changes/{change}/approve', [ProductChangeController::class, 'approve'])->name('product-changes.approve')->whereNumber('change');
    Route::post('/product-changes/{change}/reject', [ProductChangeController::class, 'reject'])->name('product-changes.reject')->whereNumber('change');
    Route::post('/sellers/{seller}/auto-approve', [ProductChangeController::class, 'autoApprove'])->name('sellers.auto-approve')->whereNumber('seller');
});
