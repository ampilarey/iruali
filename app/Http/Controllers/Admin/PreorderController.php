<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\PreorderConfirmService;
use App\Services\PreorderService;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Admin → Pre-orders: pre-orders still waiting for their stock, the late ones (more than a week past
 * their date, flagged daily by preorders:flag-late; the "Late pre-orders" inbox row) first, and the
 * booking-fee pre-orders whose balance is due (overdue ones: the "Pre-order balances overdue" row),
 * and the pre-orders paid once the shop confirms that wait for the shop (the "Pre-orders to confirm"
 * row) or for the customer's payment; an admin can confirm or decline them for the shop. Also the
 * switch on a shop's page that allows it pre-orders and "coming soon" (admins only).
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

        // Pre-orders paid once the shop confirms: waiting for the shop, then for the customer's payment
        $confirmFirst = Order::query()
            ->where('confirm_first', true)->whereNull('availability_declined_at')
            ->whereIn('status', ['pending', 'processing'])->where('payment_status', '!=', 'paid')
            ->with(['user', 'sellerOrders.seller'])
            ->orderBy('id')
            ->limit(200)
            ->get();

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
            'toConfirm' => $confirmFirst->filter(fn (Order $order) => $order->availability_confirmed_at === null)->values(),
            'awaitingPay' => $confirmFirst->filter(fn (Order $order) => $order->availability_confirmed_at !== null)->sortBy('pay_due_on')->values(),
        ]);
    }

    /**
     * Confirm, for the shop, that it can get a pre-order paid once it confirms: the customer is sent a
     * link to pay (PreorderConfirmService).
     */
    public function confirmOrder(Request $request, Order $order, PreorderConfirmService $confirms)
    {
        if (! $confirms->confirm($order, $request->user())) {
            return back()->with('error', 'This order is not waiting for a confirmation.');
        }

        return back()->with('success', 'Confirmed. The customer has been sent a link to pay '.\App\Support\Money::format($order->cardAmount()).' by '.$order->pay_due_on?->format('j M').', and the shop will be told when it is paid.');
    }

    /**
     * The shop can't get it: the order is cancelled and the customer told (nothing was paid).
     */
    public function declineOrder(Request $request, Order $order, PreorderConfirmService $confirms)
    {
        if (! $confirms->decline($order, $request->user())) {
            return back()->with('error', 'This order is not waiting for a confirmation.');
        }

        return back()->with('success', 'Order '.$order->order_number.' is cancelled, and the customer has been told the shop can\'t get the item.');
    }

    /**
     * Pre-orders and "coming soon" allowed for a shop, or not (Admin → Sellers → the shop's page).
     * Admin role only: the route is on no staff role's list in config/staff.php. Turning it off stops
     * new pre-orders at once; orders already placed carry on.
     */
    public function allowShop(Request $request, User $seller)
    {
        abort_unless($seller->is_seller, 404);
        $on = (bool) $request->validate(['enabled' => 'required|boolean'])['enabled'];

        if ($on !== (bool) $seller->preorders_allowed) {
            // Not mass-assignable (User::$fillable): only this admin action sets it
            $seller->forceFill([
                'preorders_allowed' => $on,
                'preorders_allowed_at' => $on ? now() : null,
                'preorders_allowed_by' => $on ? $request->user()->id : null,
            ])->save();
            Audit::record($on ? 'seller.preorders_on' : 'seller.preorders_off', $seller, ['business_name' => $seller->business_name]);
        }

        return back()->with('success', $on
            ? __(':shop can now take pre-orders and list products as "coming soon".', ['shop' => $seller->shopName()])
            : __(':shop can no longer take new pre-orders or list products as "coming soon". Orders already placed carry on.', ['shop' => $seller->shopName()]));
    }
}
