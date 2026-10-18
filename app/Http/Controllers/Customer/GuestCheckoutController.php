<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CartService;
use App\Services\DiscountService;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Throwable;

/**
 * Orders placed as a guest before guest checkout was removed (every order now needs a signed-in
 * customer: a password, or a code by SMS or email). Their pages stay open through signed links
 * that carry the order's guest token.
 */
class GuestCheckoutController extends Controller
{
    public function __construct(protected CartService $cartService, protected DiscountService $discountService, protected OrderService $orderService) {}

    /**
     * The guest's order page (confirmation and tracking). The link is signed and carries the order's token.
     */
    public function show(Order $order, string $token)
    {
        $this->guard($order, $token);

        $order->load(['items.product' => fn ($q) => $q->withTrashed()->with(['mainImage', 'seller']), 'sellerOrders.seller', 'paymentTransactions']);

        return view('orders.guest', compact('order'));
    }

    public function live(Order $order, string $token)
    {
        $this->guard($order, $token);

        return response()->json(['state' => $order->liveState(), 'settled' => $order->isSettled()])->header('Cache-Control', 'no-store');
    }

    public function receipt(Order $order, string $token)
    {
        $this->guard($order, $token);

        $order->load(['items.product.seller', 'paymentTransactions']);

        return view('orders.receipt', ['order' => $order, 'pdfUrl' => $order->guestUrl('receipt-pdf'), 'emailUrl' => $order->guestUrl('receipt-email')]);
    }

    public function receiptPdf(Order $order, string $token)
    {
        $this->guard($order, $token);

        return response(\App\Support\ReceiptPdf::make($order), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.\App\Support\ReceiptPdf::filename($order).'"',
        ]);
    }

    public function emailReceipt(Order $order, string $token)
    {
        $this->guard($order, $token);
        abort_unless(filled($order->guest_email), 404);
        \Illuminate\Support\Facades\Notification::route('mail', $order->guest_email)->notify(new \App\Notifications\OrderReceipt($order));

        return back()->with('success', __('We have emailed the receipt to you.'));
    }

    public function pay(Order $order, string $token, PaymentService $payments)
    {
        $this->guard($order, $token);

        if (! $payments->canPayOnline($order)) {
            return redirect()->to($order->guestUrl());
        }

        try {
            return redirect()->away($payments->startBmlPayment($order));
        } catch (Throwable $e) {
            report($e);
            NotificationService::error(__('We could not reach the payment page. Please try again in a moment.'));

            return redirect()->to($order->guestUrl());
        }
    }

    protected function guard(Order $order, string $token): void
    {
        // The link keeps working after the guest creates an account and the order is attached to it.
        abort_unless($order->guest_token && hash_equals($order->guest_token, $token), 403);
    }
}
