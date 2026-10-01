<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Services\GiftCardService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/**
 * /gift-cards: choose an amount and a recipient, pay by card, and the card is emailed once paid.
 */
class GiftCardController extends Controller
{
    public function index(PaymentService $payments)
    {
        return view('gift-cards.index', [
            'amounts' => GiftCard::AMOUNTS,
            'min' => GiftCard::MIN_CUSTOM,
            'max' => GiftCard::MAX_CUSTOM,
            'paymentsOpen' => $payments->methods() !== [],
        ]);
    }

    public function store(Request $request, GiftCardService $giftCards, PaymentService $payments)
    {
        $data = $request->validate([
            'amount' => ['required_without:custom_amount', 'nullable', Rule::in(array_merge(GiftCard::AMOUNTS, ['custom']))],
            'custom_amount' => ['required_if:amount,custom', 'nullable', 'numeric', 'min:'.GiftCard::MIN_CUSTOM, 'max:'.GiftCard::MAX_CUSTOM],
            'recipient_email' => 'required|email:rfc|max:255',
            'recipient_name' => 'nullable|string|max:120',
            'message' => 'nullable|string|max:500',
        ], [
            'custom_amount.min' => __('Gift cards are from MVR :min to MVR :max.', ['min' => GiftCard::MIN_CUSTOM, 'max' => GiftCard::MAX_CUSTOM]),
            'custom_amount.max' => __('Gift cards are from MVR :min to MVR :max.', ['min' => GiftCard::MIN_CUSTOM, 'max' => GiftCard::MAX_CUSTOM]),
        ]);

        if ($payments->methods() === []) {
            NotificationService::error(__('Card payment is not available right now, so gift cards can\'t be bought. Please try again later.'));

            return back()->withInput();
        }

        $amount = ($data['amount'] ?? 'custom') === 'custom' ? (float) $data['custom_amount'] : (float) $data['amount'];

        try {
            ['order' => $order] = $giftCards->purchase($request->user(), $amount, $data['recipient_email'], $data['recipient_name'] ?? null, $data['message'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['custom_amount' => $e->getMessage()])->withInput();
        }

        try {
            return redirect()->away($payments->startBmlPayment($order));
        } catch (Throwable $e) {
            report($e);
            NotificationService::error(__('Your order is saved, but we could not reach the payment page. Please use "Pay now" to try again.'));

            return redirect()->route('orders.show', $order);
        }
    }
}
