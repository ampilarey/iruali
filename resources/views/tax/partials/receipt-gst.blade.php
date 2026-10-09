{{-- iruali's receipt: the GST in iruali's own charges, when iruali was GST-registered when the order was placed. Nothing shows otherwise. --}}
@if($order->gst_platform_registered)
    @php
        $ownSales = $order->sellerOrders->whereNull('seller_id')->where('gst_registered', true);
        $receiptRate = \App\Support\InvoiceText::rate($order->gst_rate);
    @endphp
    <h2>{{ __('GST') }}</h2>
    <table class="totals" style="max-width:420px;margin-inline-start:auto" data-receipt-gst>
        <tr><td>{{ __('GST included in delivery (:rate%)', ['rate' => $receiptRate]) }}</td><td class="num">{{ \App\Support\Money::format($order->delivery_gst) }}</td></tr>
        @if($ownSales->isNotEmpty())
            <tr><td>{{ __('GST included in items sold by iruali') }}</td><td class="num">{{ \App\Support\Money::format($ownSales->sum('gst_amount')) }}</td></tr>
        @endif
    </table>
    <p class="muted" style="margin:6px 0 0">{{ __('Prices include GST.') }} {{ __('iruali GST TIN') }}: <span dir="ltr">{{ $order->gst_platform_tin }}</span>. {{ __('Each GST-registered shop\'s tax invoice is on your order page.') }}</p>
@endif
