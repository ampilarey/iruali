<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\NotificationService;
use App\Services\PreorderService;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

/**
 * "Rather not wait?": a customer cancels an order with pre-order items until anything in it has
 * been sent, from My Orders or, for a guest order, through the signed link in its emails. It is
 * the normal cancellation of the whole order (OrderService::updateOrderStatus()): stock that had
 * arrived goes back, points, voucher and wallet are returned and a card payment is flagged for refund.
 */
class PreorderCancelController extends Controller
{
    public function show(Order $order, PreorderService $preorders)
    {
        $this->ownOrder($order);

        return $this->page($order, $preorders, route('orders.preorder.cancel.store', $order), route('orders.show', $order));
    }

    public function store(Order $order, PreorderService $preorders)
    {
        $this->ownOrder($order);

        return $this->cancel($order, $preorders, route('orders.show', $order));
    }

    public function guestShow(Order $order, string $token, PreorderService $preorders)
    {
        $this->guestOrder($order, $token);

        $action = URL::signedRoute('guest.orders.preorder.cancel.store', ['order' => $order->getKey(), 'token' => $token]);

        return $this->page($order, $preorders, $action, (string) $order->guestUrl());
    }

    public function guestStore(Order $order, string $token, PreorderService $preorders)
    {
        $this->guestOrder($order, $token);

        return $this->cancel($order, $preorders, (string) $order->guestUrl());
    }

    protected function page(Order $order, PreorderService $preorders, string $action, string $back)
    {
        $order->load(['items.product' => fn ($q) => $q->withTrashed(), 'sellerOrders.seller']);

        return view('preorders.cancel', [
            'order' => $order,
            'canCancel' => $preorders->canCancel($order),
            'action' => $action,
            'back' => $back,
        ]);
    }

    protected function cancel(Order $order, PreorderService $preorders, string $back)
    {
        $card = $order->payment_status === 'paid' ? $order->cardAmount() : 0.0;

        if (! $preorders->cancel($order)) {
            NotificationService::error(__('This order can no longer be cancelled here: part of it has already been sent. Message the shop from your order if you need help.'));

            return redirect()->to($back);
        }

        NotificationService::success($card > 0
            ? __('Your order has been cancelled. We will refund :amount to your card and email you when it is sent.', ['amount' => Money::format($card)])
            : __('Your order has been cancelled.'));

        return redirect()->to($back);
    }

    protected function ownOrder(Order $order): void
    {
        abort_unless($order->user_id !== null && (int) $order->user_id === (int) Auth::id(), 403);
    }

    protected function guestOrder(Order $order, string $token): void
    {
        // The signed link keeps working after the guest makes an account (as the guest order page does)
        abort_unless($order->guest_token && hash_equals($order->guest_token, $token), 403);
    }
}
