<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Services\PreorderService;
use Illuminate\Http\Request;

/**
 * Admin → Pre-orders: pre-orders still waiting for their stock, the late ones (more than a week past
 * their date, flagged daily by preorders:flag-late; the "Late pre-orders" inbox row) first.
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

        return view('admin.preorders.index', [
            'items' => $items,
            'lateOnly' => $lateOnly,
            'lateDays' => PreorderService::LATE_AFTER_DAYS,
        ]);
    }
}
