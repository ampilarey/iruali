<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\SellerPayout;
use App\Services\GstService;
use App\Support\CurrentShop;
use Illuminate\Http\Request;

/**
 * Printable invoices. A shop's part of a paid order: a tax invoice when the shop was GST-registered
 * when the order was placed, otherwise a receipt (for the customer, a guest's signed link, the shop
 * and staff); with ?booking=1 the invoice for a pre-order's booking fee, from when the fee is paid.
 * iruali's commission invoice for a payout (for the shop and staff).
 */
class TaxInvoiceController extends Controller
{
    public function __construct(protected GstService $gst) {}

    /**
     * The customer who placed the order.
     */
    public function customer(Request $request, Order $order, SellerOrder $part)
    {
        abort_unless($order->user_id !== null && $order->user_id === $request->user()->id, 403);

        return $this->invoice($order, $part, $order->customerUrl());
    }

    /**
     * A guest's order: the signed link carries the order's token.
     */
    public function guest(Order $order, string $token, SellerOrder $part)
    {
        abort_unless($order->guest_token && hash_equals($order->guest_token, $token), 403);

        return $this->invoice($order, $part, (string) $order->guestUrl());
    }

    /**
     * A shop: its own part of the order only.
     */
    public function seller(Request $request, Order $order)
    {
        $part = $order->sellerOrders()->where('seller_id', CurrentShop::id())->first();
        abort_if($part === null, 404);

        return $this->invoice($order, $part, route('seller.orders.show', $order));
    }

    /**
     * Staff (the admin route group checks the role).
     */
    public function admin(Order $order, SellerOrder $part)
    {
        return $this->invoice($order, $part, route('admin.orders.show', $order));
    }

    public function sellerCommission(Request $request, SellerPayout $payout)
    {
        abort_unless($payout->seller_id === CurrentShop::id(), 403);

        return $this->commission($payout, route('seller.earnings'));
    }

    public function adminCommission(SellerPayout $payout)
    {
        return $this->commission($payout, route('admin.payouts.show', $payout));
    }

    protected function invoice(Order $order, SellerOrder $part, string $back)
    {
        abort_unless($part->order_id === $order->id, 404);
        if (request()->boolean('booking')) {
            return $this->bookingFeeInvoice($order, $part, $back);
        }
        // Only paid orders have invoices (gift cards are not a sale of goods)
        abort_unless($order->payment_status === 'paid' && ! $order->isGiftCardOrder(), 404);

        if (! $part->invoice_number) {
            $this->gst->assignInvoiceNumbers($order); // one missed when the payment came in
            $part->refresh();
        }
        abort_unless((bool) $part->invoice_number, 404);

        $part->loadMissing('seller');
        $order->loadMissing('user');

        return view('tax.invoice', [
            'order' => $order,
            'part' => $part,
            'items' => $part->items()->with('product')->orderBy('id')->get(),
            'taxInvoice' => $this->gst->isTaxInvoice($part),
            'back' => $back,
        ]);
    }

    /**
     * The booking fee's own invoice (App\Services\BookingFeeService): once the fee is paid, and kept
     * after the order is cancelled (it says whether the fee was refunded or kept).
     */
    protected function bookingFeeInvoice(Order $order, SellerOrder $part, string $back)
    {
        if (! $part->deposit_invoice_number && $order->bookingFeePaid()) {
            $this->gst->assignDepositInvoice($order); // one missed when the fee came in
            $part->refresh();
        }
        abort_unless((bool) $part->deposit_invoice_number, 404);

        $part->loadMissing('seller');
        $order->loadMissing('user');

        return view('tax.booking-fee-invoice', [
            'order' => $order,
            'part' => $part,
            'items' => $part->items()->with('product')->orderBy('id')->get(),
            'taxInvoice' => $this->gst->isTaxInvoice($part),
            'back' => $back,
        ]);
    }

    protected function commission(SellerPayout $payout, string $back)
    {
        if ($payout->isPaid() && ! $payout->invoice_number) {
            $this->gst->payoutPaid($payout); // a payout made before invoices were numbered
        }
        $payout->loadMissing(['seller', 'batch']);

        return view('tax.commission-invoice', ['payout' => $payout, 'invoice' => $this->gst->commissionInvoice($payout), 'back' => $back]);
    }
}
