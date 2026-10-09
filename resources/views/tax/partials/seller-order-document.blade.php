{{-- Seller Centre → order: the shop's invoice for its part of a paid order --}}
@if($order->payment_status === 'paid' && ! $order->isGiftCardOrder())
    <div class="rounded-lg bg-white p-5 shadow text-sm" data-shop-invoice>
        <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">{{ $part->gst_registered ? __('Tax invoice') : __('Receipt') }}</h2>
        @if($part->invoice_number)<p class="mt-2 font-mono text-gray-900" dir="ltr">{{ $part->invoice_number }}</p>@endif
        <p class="mt-1 text-gray-600">
            @if($part->gst_registered)
                {{ __('GST included: :amount at :rate%.', ['amount' => \App\Support\Money::format($part->gst_amount), 'rate' => \App\Support\InvoiceText::rate($part->gst_rate)]) }}
            @else
                {{ __('Your shop was not GST-registered when this order was placed, so this is a receipt without GST.') }}
            @endif
        </p>
        <a href="{{ route('seller.orders.invoice', $order) }}" target="_blank" rel="noopener" class="mt-3 inline-block rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">{{ __('View / print') }}</a>
    </div>
@endif
