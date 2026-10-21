{{-- Product page buy box: the pre-order offer (PreorderService::productOffer()) when the product, or some of its
     options, is out of stock and the shop takes pre-orders: its date (or days after the order), any booking
     fee, or that it is paid once the shop confirms. With only some options on pre-order the box shows once one
     of them is picked (resources/js/variant-picker.js). Needs $preorder (null when there is none). --}}
@if($preorder)
    <div class="mt-3 rounded-lg border border-primary-200 bg-primary-50 p-3 text-sm {{ $preorder['whole'] ? '' : 'hidden' }}" data-preorder-notice role="status">
        <p class="font-semibold text-dark flex items-center gap-2"><x-icon name="clock" class="w-4 h-4 shrink-0 text-primary" />{{ ($preorder['days'] ?? null)
            ? trans_choice('Pre-order: ships about :count day after you order|Pre-order: ships about :count days after you order', $preorder['days'], ['count' => $preorder['days']])
            : __('Pre-order: ships around :date', ['date' => $preorder['date']->translatedFormat('j M')]) }}</p>
        @if($preorder['note'])
            <p class="mt-1 ps-6 text-gray-700" dir="auto">{{ $preorder['note'] }}</p>
        @endif
        @if($preorder['fee'] ?? null)
            {{-- A booking fee now, the rest when it arrives (BookingFeeService) --}}
            <p class="mt-1 ps-6 font-medium text-dark" data-booking-fee>{{ __('Booking fee now: :fee. You pay the rest within :days days of it arriving, before it is delivered.', ['fee' => $preorder['fee'], 'days' => $preorder['balance_days']]) }}</p>
            <p class="mt-1 ps-6 text-xs text-gray-600">{{ __('If you cancel, the booking fee is not refunded. If the shop cancels, can\'t get it, moves the date later or is more than :days days late, you get everything back. Ordered on its own, not with other items.', ['days' => $preorder['late_days']]) }}</p>
        @elseif($preorder['confirm'] ?? false)
            {{-- Nothing now; the full price once the shop confirms it can get it (PreorderConfirmService) --}}
            <p class="mt-1 ps-6 font-medium text-dark" data-confirm-first>{{ trans_choice('Nothing to pay now: the shop first confirms it can get it, then you have :count day to pay.|Nothing to pay now: the shop first confirms it can get it, then you have :count days to pay.', $preorder['pay_days'], ['count' => $preorder['pay_days']]) }}</p>
            <p class="mt-1 ps-6 text-xs text-gray-600">{{ __('If the shop can\'t get it, the order is cancelled and you pay nothing. Ordered on its own, not with other items.') }}</p>
        @else
            <p class="mt-1 ps-6 text-xs text-gray-600">{{ __('You pay now and the shop sends it when the stock arrives. You can cancel for a full refund until it is sent.') }}</p>
        @endif
        @if($preorder['closes'] ?? null)
            <p class="mt-1 ps-6 text-xs font-semibold text-dark" data-preorder-closes>{{ $preorder['closes']->isToday() ? __('Last day to pre-order: today') : __('Pre-order by :date', ['date' => $preorder['closes']->translatedFormat('j M')]) }}</p>
        @endif
        @if($preorder['left'] <= 5)
            <p class="mt-1 ps-6 text-xs font-semibold text-sun-ink">{{ __('Only :count left to pre-order', ['count' => $preorder['left']]) }}</p>
        @endif
    </div>
@endif
