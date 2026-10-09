{{-- Checkout: the cart has pre-order lines (PreorderService::checkoutLines()). They are paid for now and sent
     when their stock arrives, together with the rest of that shop's items. Needs $lines. --}}
@if(! empty($lines))
    <div class="mb-6 rounded-xl border border-primary-200 bg-primary-50 p-4 text-sm text-gray-800" role="status" data-checkout-preorders>
        <p class="font-semibold text-dark flex items-center gap-2"><x-icon name="clock" class="w-5 h-5 shrink-0 text-primary" />{{ trans_choice('Your order has :count pre-order item|Your order has :count pre-order items', count($lines), ['count' => count($lines)]) }}</p>
        <p class="mt-1 ps-7">{{ __('You pay for them now. Each shop sends its part of your order once their stock arrives, so its other items come with them. You can cancel for a full refund until they are sent.') }}</p>
    </div>
@endif
