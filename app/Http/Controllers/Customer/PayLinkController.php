<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ManualPaymentService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Support\Audit;
use App\Support\PayLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * The payment link page (App\Support\PayLink): reached only through a signed link, no sign-in. The
 * customer pays by card or bank transfer; choosing the other method than the one picked at checkout
 * switches the order to it (card instead of a transfer, or paying now instead of cash on delivery).
 */
class PayLinkController extends Controller
{
    public function show(Request $request, Order $order)
    {
        $order->loadMissing('items');
        // Back from the card page, a visitor who is not signed in returns here (BmlPaymentController::return)
        $request->session()->put('paylink.'.$order->getKey(), $request->fullUrl());

        return view('pay.show', [
            'order' => $order,
            'payable' => PayLink::payable($order),
            'card' => PayLink::cardAvailable(),
            'transfer' => PayLink::transferAvailable(),
            'cardAction' => $this->signed($request, 'pay.card', $order),
            'slipAction' => $this->signed($request, 'pay.slip', $order),
        ]);
    }

    public function card(Request $request, Order $order, PaymentService $payments): RedirectResponse
    {
        if (! PayLink::payable($order) || ! PayLink::cardAvailable()) {
            return back();
        }
        $this->switchTo($order, 'bml');

        try {
            return redirect()->away($payments->startBmlPayment($order->fresh()));
        } catch (Throwable $e) {
            report($e);
            NotificationService::error(__('We could not reach the payment page. Please try again in a moment.'));

            return back();
        }
    }

    public function slip(Request $request, Order $order, ManualPaymentService $manual): RedirectResponse
    {
        if (! PayLink::payable($order) || ! PayLink::transferAvailable()) {
            return back();
        }
        $request->validate(['slip' => 'required|file|max:8192|mimes:jpg,jpeg,png,webp,pdf'], [
            'slip.required' => __('Please choose the photo or PDF of your transfer slip.'),
            'slip.mimes' => __('The slip must be a photo (JPG, PNG, WebP) or a PDF.'),
            'slip.max' => __('The slip must be smaller than 8 MB.'),
        ]);
        $this->switchTo($order, ManualPaymentService::TRANSFER);

        if (! $manual->storeSlip($order->fresh(), $request->file('slip'))) {
            NotificationService::error(__('We could not take that file. Please upload a photo or PDF of the slip.'));

            return back();
        }
        NotificationService::success(__('Thank you. We will check your transfer and confirm your order soon.'));

        return back();
    }

    protected function switchTo(Order $order, string $method): void
    {
        if ($order->payment_method === $method) {
            return;
        }
        Audit::record('payment.method_switched', $order, ['from' => $order->payment_method, 'to' => $method, 'by' => 'payment link', 'order_number' => $order->order_number]);
        $order->forceFill(['payment_method' => $method])->save();
    }

    /**
     * The page's forms post to links signed until the same moment as the page's own link.
     */
    protected function signed(Request $request, string $route, Order $order): string
    {
        $expires = (int) $request->query('expires', 0);

        return URL::temporarySignedRoute($route, $expires > 0 ? Carbon::createFromTimestamp($expires) : now()->addHours(PayLink::HOURS), ['order' => $order->getKey()]);
    }
}
