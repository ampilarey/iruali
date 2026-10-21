{{-- Seller order page: a pre-order with a booking fee (App\Services\BookingFeeService). When to order the item,
     and when the order can be sent. Needs $order. --}}
@if($order->hasBookingFee())
    @php $money = fn ($amount) => \App\Support\Money::format($amount); @endphp
    <p class="mt-2 rounded-md bg-primary-50 px-3 py-2 text-xs text-gray-800" data-seller-booking-fee>
        @if($order->status === 'cancelled')
            {{ (float) $order->deposit_kept_amount > 0
                ? __('Cancelled. The booking fee of :fee was kept for you and added to your earnings, less commission.', ['fee' => $money($order->deposit_kept_amount)])
                : __('Cancelled. The booking fee was refunded to the customer.') }}
        @elseif(! $order->bookingFeePaid())
            {{ __('Pre-order with a booking fee of :fee. Wait until the fee is paid before you order the item.', ['fee' => $money($order->deposit_amount)]) }}
        @elseif($order->payment_status === 'paid')
            {{ __('The booking fee and the balance are paid. You can send it.') }}
        @elseif($order->balanceDue())
            {{ __('Waiting for the customer to pay the balance of :balance (due :date). Send it once the order shows as paid.', ['balance' => $money($order->balanceAmount()), 'date' => $order->balance_due_on?->translatedFormat('j M')]) }}
        @else
            {{ __('Booking fee of :fee paid: order the item now. When it arrives, record it under Pre-orders; we then ask the customer for the balance of :balance.', ['fee' => $money($order->deposit_amount), 'balance' => $money($order->balanceAmount())]) }}
        @endif
    </p>
@endif
