{{-- Order page (account or guest): a pre-order paid once the shop confirms it can get the item
     (App\Services\PreorderConfirmService). Waiting for the shop, then for the payment by its due date (the pay
     button and the transfer details follow), or cancelled because the shop couldn't get it. Needs $order. --}}
@if($order->confirm_first)
    @php $money = fn ($amount) => \App\Support\Money::format($amount); @endphp
    @if($order->availability_declined_at)
        <p class="mt-4 rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-700" data-confirm-first-box="declined">{{ __('The shop couldn\'t get your item, so the order was cancelled. You were not charged.') }}</p>
    @elseif($order->status !== 'cancelled' && $order->payment_status !== 'paid')
        <div class="mt-4 rounded-xl border border-primary-200 bg-primary-50 p-4 text-sm space-y-1" data-confirm-first-box="{{ $order->awaitingConfirmation() ? 'waiting' : 'confirmed' }}">
            @if($order->awaitingConfirmation())
                <h3 class="font-semibold text-gray-900">{{ __('Waiting for the shop to confirm') }}</h3>
                <p class="text-gray-700">{{ __('The shop is checking it can get your item. Once it confirms, we text and email you a link to pay :amount. Nothing to pay yet.', ['amount' => $money($order->total_amount)]) }}</p>
            @else
                <h3 class="font-semibold text-gray-900">{{ __('The shop can get your item') }}</h3>
                <p class="text-gray-700">{{ __('Please pay :amount by :date. If it is not paid by then, the order is cancelled.', ['amount' => $money($order->cardAmount()), 'date' => $order->pay_due_on?->translatedFormat('j M Y')]) }}</p>
            @endif
        </div>
    @endif
@endif
