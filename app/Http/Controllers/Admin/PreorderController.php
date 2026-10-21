<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\PreorderService;
use Illuminate\Http\Request;

/**
 * Admin → Pre-orders: pre-orders still waiting for their stock, the late ones (more than a week past
 * their date, flagged daily by preorders:flag-late; the "Late pre-orders" inbox row) first, and the
 * booking-fee pre-orders whose balance is due (overdue ones: the "Pre-order balances overdue" row).
 */
class PreorderController extends Controller
{
    public function index(Request $request)
    {
        $lateOnly = $request->boolean('late');

        $items = OrderItem::query()
            ->where('is_preorder', true)
            ->whereNull('preorder_allocated_at')
            ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->when($lateOnly, fn ($q) => $q->whereNotNull('preorder_late_at'))
            ->with(['order.user', 'product.seller'])
            ->orderByRaw('preorder_late_at IS NULL')
            ->orderBy('preorder_ship_date')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        // Pre-orders with a booking fee whose stock is in and whose balance is not paid yet, the oldest due first
        $balances = Order::query()
            ->where('payment_status', 'deposit_paid')->whereNotNull('deposit_amount')->whereNotNull('balance_requested_at')
            ->whereIn('status', ['pending', 'processing'])
            ->with('user')
            ->orderBy('balance_due_on')
            ->limit(100)
            ->get();

        return view('admin.preorders.index', [
            'items' => $items,
            'lateOnly' => $lateOnly,
            'lateDays' => PreorderService::LATE_AFTER_DAYS,
            'balances' => $balances,
        ]);
    }
}
