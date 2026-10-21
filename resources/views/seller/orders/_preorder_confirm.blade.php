{{-- Seller order page: a pre-order paid once the shop confirms (App\Services\PreorderConfirmService). Confirm you can
     get the item (the customer is sent a link to pay) or cancel it; then wait for the payment. Needs $order. --}}
@if($order->confirm_first && $order->status !== 'cancelled' && $order->payment_status !== 'paid')
    @php $money = fn ($amount) => \App\Support\Money::format($amount); @endphp
    <div class="mt-2 space-y-2 rounded-md bg-primary-50 px-3 py-2 text-xs text-gray-800" data-seller-confirm-first="{{ $order->awaitingConfirmation() ? 'waiting' : 'confirmed' }}">
        @if($order->awaitingConfirmation())
            <p>{{ __('The customer pays :amount once you confirm you can get the item. Check with your supplier, then:', ['amount' => $money($order->total_amount)]) }}</p>
            @if(\App\Support\CurrentShop::can('seller.preorders.confirm'))
                @include('preorders._confirm_actions', ['confirmUrl' => route('seller.preorders.confirm', $order), 'declineUrl' => route('seller.preorders.decline', $order)])
            @endif
        @else
            <p>{{ __('Confirmed :date. Waiting for the customer to pay :amount by :due; order the item once it shows as paid.', ['date' => $order->availability_confirmed_at?->translatedFormat('j M'), 'amount' => $money($order->cardAmount()), 'due' => $order->pay_due_on?->translatedFormat('j M')]) }}</p>
        @endif
    </div>
@endif
