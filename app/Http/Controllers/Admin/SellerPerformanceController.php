<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SellerPerformanceService;

/**
 * Admin → Sellers → a shop's performance: the same numbers the shop sees, plus its late shipments.
 */
class SellerPerformanceController extends Controller
{
    public function show(User $seller, SellerPerformanceService $performance)
    {
        abort_unless($seller->is_seller || $seller->sellerOrders()->exists(), 404);

        return view('admin.sellers.performance', ['seller' => $seller, 'report' => $performance->report($seller)]);
    }
}
