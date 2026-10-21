@php
    use App\Support\Company;
    use App\Support\InvoiceText as T;
    use App\Support\Money;

    // The booking fee of a pre-order, invoiced when it is paid (App\Services\GstService::assignDepositInvoice()).
    // The rest of the sale is invoiced when the balance is paid (tax/invoice). Needs $order, $part, $items, $taxInvoice, $back.
    $shopName = $part->gst_business_name ?: $part->shopName();
    $fee = round((float) $part->deposit_amount, 2);
    $gst = round((float) $part->deposit_gst, 2);
    $rate = T::rate($part->gst_rate);
    $title = $taxInvoice ? 'Tax invoice' : 'Receipt';
    $saleTotal = round((float) ($part->gst_taxable ?? $part->subtotal), 2);
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __($title, [], 'en') }} {{ $part->deposit_invoice_number }} · {{ $shopName }}</title>
    @include('tax.partials.document-styles')
</head>
<body>
    <div class="actions">
        <a href="{{ $back }}" class="btn alt">{{ T::label('Back to order') }}</a>
        <button type="button" class="btn" onclick="window.print()">{{ T::label('Print / save as PDF') }}</button>
    </div>
    <main class="sheet" data-invoice="{{ $taxInvoice ? 'tax' : 'receipt' }}" data-booking-fee-invoice>
        <div class="row">
            <div>
                <h2 style="margin-top:0">{{ T::label('Sold by') }}</h2>
                <p class="strong">{{ $shopName }}</p>
                @if($part->gst_business_address)<p class="muted">{{ $part->gst_business_address }}</p>@endif
                @if($taxInvoice && $part->gst_tin)<p class="muted" style="margin-top:4px">{{ T::label('GST TIN') }} <strong dir="ltr" style="color:#0F2A3A">{{ $part->gst_tin }}</strong></p>@endif
            </div>
            <div class="end">
                <h1>{{ T::label($title) }}</h1>
                <p class="muted">{{ T::label('Booking fee (advance payment)') }}</p>
                <table class="meta">
                    <tr><th>{{ T::label($taxInvoice ? 'Invoice no.' : 'Receipt no.') }}</th><td dir="ltr">{{ $part->deposit_invoice_number }}</td></tr>
                    <tr><th>{{ T::label('Date') }}</th><td>{{ $part->deposit_invoiced_at?->format('j M Y') }}</td></tr>
                    <tr><th>{{ T::label('Order') }}</th><td dir="ltr">{{ $order->order_number }}</td></tr>
                </table>
            </div>
        </div>

        <h2>{{ T::label('Bill to') }}</h2>
        @if($order->buyer_business_name)
            <p class="strong" data-buyer-business>{{ $order->buyer_business_name }}</p>
            @if($order->buyer_tin)<p class="muted">{{ T::label('TIN') }} <strong dir="ltr" style="color:#0F2A3A">{{ $order->buyer_tin }}</strong></p>@endif
            @if($order->buyer_business_address)<p class="muted">{{ $order->buyer_business_address }}</p>@endif
            <p class="muted" style="margin-top:4px">{{ T::label('Ordered by :name', ['name' => $order->customerName()]) }}</p>
        @else
            <p>{{ $order->customerName() }}</p>
            <p class="muted">{{ collect([$order->shipping_address, $order->shipping_city, $order->shipping_state])->filter()->join(', ') }}</p>
        @endif

        <h2>{{ T::label('Pre-order') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ T::label('Description') }}</th>
                    <th class="num">{{ T::label('Qty') }}</th>
                    <th class="num">{{ T::label($taxInvoice ? 'Amount incl. GST' : 'Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>{{ $item->product ? $item->product->getTranslation('name', 'en') : __('Product', [], 'en') }}@if($item->variant_name) – {{ $item->variant_name }}@endif</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ Money::format(round((float) $item->price * $item->quantity, 2)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr><td>{{ T::label('Sale total') }}</td><td class="num">{{ Money::format($saleTotal) }}</td></tr>
            <tr class="grand" data-booking-fee-total><td>{{ T::label($taxInvoice ? 'Booking fee paid, incl. GST' : 'Booking fee paid') }} (MVR)</td><td class="num">{{ Money::format($fee) }}</td></tr>
        </table>

        @if($taxInvoice)
            <h2>{{ T::label('GST breakdown') }}</h2>
            <table data-gst-breakdown>
                <thead>
                    <tr><th>{{ T::label('GST rate') }}</th><th class="num">{{ T::label('Value excl. GST') }}</th><th class="num">{{ T::label('GST') }}</th><th class="num">{{ T::label('Total incl. GST') }}</th></tr>
                </thead>
                <tbody>
                    <tr><td>{{ $rate }}%</td><td class="num">{{ Money::format($fee - $gst) }}</td><td class="num">{{ Money::format($gst) }}</td><td class="num">{{ Money::format($fee) }}</td></tr>
                </tbody>
            </table>
            <p class="muted" style="margin-top:8px">{{ T::label('Prices include GST. GST is :rate% of the price before GST.', ['rate' => $rate]) }}</p>
        @endif

        @if($part->deposit_reversed_at)
            <div class="note warn" data-reversed>{{ T::label('Cancelled on :date: the booking fee was refunded.', ['date' => $part->deposit_reversed_at->format('j M Y')]) }}</div>
        @elseif($part->deposit_kept_at)
            <div class="note" data-kept>{{ T::label('The order was cancelled on :date. As agreed at checkout, the booking fee was not refunded.', ['date' => $part->deposit_kept_at->format('j M Y')]) }}</div>
        @endif

        <h2>{{ T::label('Payment') }}</h2>
        <p class="muted">{{ T::label('Booking fee paid :date. The rest of the sale is invoiced when the balance is paid.', ['date' => $order->deposit_paid_at?->format('j M Y, H:i')]) }}</p>

        <div class="note">
            {{ T::label('Issued through iruali for :shop. Please keep it for your records.', ['shop' => $shopName]) }}
            <br><span class="muted">{{ Company::legalName() ?? Company::tradingName() }}@if(Company::address()) · {{ Company::address() }}@endif</span>
        </div>
    </main>
</body>
</html>
