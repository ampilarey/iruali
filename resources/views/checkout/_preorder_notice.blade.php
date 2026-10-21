{{-- Checkout: the cart has pre-order lines (PreorderService::checkoutLines()). They are paid for now and sent
     when their stock arrives, together with the rest of that shop's items. With a booking fee only the fee is
     paid now and the balance when the stock arrives (BookingFeeService); a pre-order the shop confirms first is
     paid in full once it does (PreorderConfirmService). Needs $lines; $bookingFee and $confirmFirst (or null). --}}
@if(! empty($lines))
    <div class="mb-6 rounded-xl border border-primary-200 bg-primary-50 p-4 text-sm text-gray-800" role="status" data-checkout-preorders>
        <p class="font-semibold text-dark flex items-center gap-2"><x-icon name="clock" class="w-5 h-5 shrink-0 text-primary" />{{ trans_choice('Your order has :count pre-order item|Your order has :count pre-order items', count($lines), ['count' => count($lines)]) }}</p>
        @if($confirmFirst ?? null)
            <p class="mt-1 ps-7" data-checkout-confirm-first>{{ trans_choice('Nothing to pay now. The shop first checks it can get your item; we then text and email you a link to pay, and you have :count day to pay. If the shop can\'t get it, the order is cancelled and you pay nothing.|Nothing to pay now. The shop first checks it can get your item; we then text and email you a link to pay, and you have :count days to pay. If the shop can\'t get it, the order is cancelled and you pay nothing.', $confirmFirst['pay_days'], ['count' => $confirmFirst['pay_days']]) }}</p>
        @elseif($bookingFee ?? null)
            <p class="mt-1 ps-7" data-checkout-booking-fee>{{ __('You pay a booking fee of :fee now. When your order arrives we text and email you a link to pay the balance; the shop sends it once the balance is paid.', ['fee' => \App\Support\Money::format($bookingFee['fee'])]) }}</p>
        @else
            <p class="mt-1 ps-7">{{ __('You pay for them now. Each shop sends its part of your order once their stock arrives, so its other items come with them. You can cancel for a full refund until they are sent.') }}</p>
        @endif
    </div>
@endif
