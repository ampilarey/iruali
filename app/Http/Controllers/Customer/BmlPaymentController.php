<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\BmlConnect;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Throwable;

/**
 * Card payments through BML Connect (redirect method).
 *
 * pay     → create a BML transaction and send the customer to BML's payment page
 * return  → BML sends the customer back; we ask BML's API for the real result
 * webhook → BML tells us about state changes (signed); we ask the API again
 */
class BmlPaymentController extends Controller
{
    public function pay(Request $request, Order $order, PaymentService $payments)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        if (! $payments->canPayOnline($order)) {
            return redirect()->route('orders.show', $order);
        }

        try {
            return redirect()->away($payments->startBmlPayment($order));
        } catch (Throwable $e) {
            report($e);
            NotificationService::error(__('We could not reach the payment page. Please try again in a moment.'));

            return redirect()->route('orders.show', $order);
        }
    }

    public function return(Request $request, Order $order, PaymentService $payments)
    {
        $transaction = $order->paymentTransactions()
            ->when($request->query('transactionId'), fn ($q, $id) => $q->where('transaction_id', $id))
            ->latest('id')
            ->first();

        if ($transaction?->transaction_id) {
            try {
                $transaction = $payments->syncBml($transaction);
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($transaction?->isConfirmed()) {
            NotificationService::success(__('Payment received. Thank you!'));
        } elseif ($transaction?->hasFailed()) {
            NotificationService::error(__('The payment was not completed. You can try again from this page.'));
        } else {
            NotificationService::info(__('We are waiting for the bank to confirm your payment. This page will show it once it does.'));
        }

        return redirect()->route('orders.show', $order);
    }

    public function webhook(Request $request, BmlConnect $bml, PaymentService $payments)
    {
        if (! $bml->verifyWebhookSignature(
            $request->header('X-Signature-Nonce'),
            $request->header('X-Signature-Timestamp'),
            $request->header('X-Signature')
        )) {
            abort(403);
        }

        $id = $request->input('transactionId') ?? $request->input('id');
        $transaction = $id ? PaymentTransaction::where('transaction_id', $id)->first() : null;

        if ($transaction) {
            try {
                $payments->syncBml($transaction);
            } catch (Throwable $e) {
                report($e);

                return response()->json(['ok' => false], 503); // let BML retry
            }
        }

        return response()->json(['ok' => true]);
    }
}
