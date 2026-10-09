@php
    use App\Support\Company;
    use App\Support\InvoiceText as T;
    use App\Support\Money;

    $shopName = $part->gst_business_name ?: $part->shopName();
    $lineAmount = fn ($item) => round((float) $item->price * $item->quantity, 2);
    $subtotal = round((float) $items->sum($lineAmount), 2);
    $total = round((float) ($part->gst_taxable ?? $part->subtotal), 2);
    $discount = round(max(0, $subtotal - $total), 2);
    $gst = round((float) $part->gst_amount, 2);
    $rate = T::rate($part->gst_rate);
    $title = $taxInvoice ? 'Tax invoice' : 'Receipt';
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __($title, [], 'en') }} {{ $part->invoice_number }} · {{ $shopName }}</title>
    @include('tax.partials.document-styles')
</head>
<body>
    <div class="actions">
        <a href="{{ $back }}" class="btn alt">{{ T::label('Back to order') }}</a>
        <button type="button" class="btn" onclick="window.print()">{{ T::label('Print / save as PDF') }}</button>
    </div>
    <main class="sheet" data-invoice="{{ $taxInvoice ? 'tax' : 'receipt' }}">
        <div class="row">
            <div>
                <h2 style="margin-top:0">{{ T::label('Sold by') }}</h2>
                <p class="strong">{{ $shopName }}</p>
                @if($part->gst_business_address)<p class="muted">{{ $part->gst_business_address }}</p>@endif
                @if($taxInvoice && $part->gst_tin)<p class="muted" style="margin-top:4px">{{ T::label('GST TIN') }} <strong dir="ltr" style="color:#0F2A3A">{{ $part->gst_tin }}</strong></p>@endif
            </div>
            <div class="end">
                <h1>{{ T::label($title) }}</h1>
                <table class="meta">
                    <tr><th>{{ T::label($taxInvoice ? 'Invoice no.' : 'Receipt no.') }}</th><td dir="ltr">{{ $part->invoice_number }}</td></tr>
                    <tr><th>{{ T::label('Date') }}</th><td>{{ $part->invoiced_at?->format('j M Y') }}</td></tr>
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

        <h2>{{ T::label('Items') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ T::label('Description') }}</th>
                    <th class="num">{{ T::label('Qty') }}</th>
                    <th class="num">{{ T::label($taxInvoice ? 'Unit price incl. GST' : 'Unit price') }}</th>
                    <th class="num">{{ T::label($taxInvoice ? 'Amount incl. GST' : 'Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>{{ $item->product ? $item->product->getTranslation('name', 'en') : __('Product', [], 'en') }}@if($item->variant_name) – {{ $item->variant_name }}@endif @if($item->variant_sku)<span class="muted" dir="ltr">({{ $item->variant_sku }})</span>@endif</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ Money::format($item->price) }}</td>
                        <td class="num">{{ Money::format($lineAmount($item)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            @if($discount > 0)
                <tr><td>{{ T::label('Subtotal') }}</td><td class="num">{{ Money::format($subtotal) }}</td></tr>
                <tr><td>{{ T::label('Shop discount') }}</td><td class="num">&minus;{{ Money::format($discount) }}</td></tr>
            @endif
            <tr class="grand"><td>{{ T::label($taxInvoice ? 'Total incl. GST' : 'Total') }} (MVR)</td><td class="num">{{ Money::format($total) }}</td></tr>
        </table>

        @if($taxInvoice)
            <h2>{{ T::label('GST breakdown') }}</h2>
            <table data-gst-breakdown>
                <thead>
                    <tr><th>{{ T::label('GST rate') }}</th><th class="num">{{ T::label('Value excl. GST') }}</th><th class="num">{{ T::label('GST') }}</th><th class="num">{{ T::label('Total incl. GST') }}</th></tr>
                </thead>
                <tbody>
                    <tr><td>{{ $rate }}%</td><td class="num">{{ Money::format($total - $gst) }}</td><td class="num">{{ Money::format($gst) }}</td><td class="num">{{ Money::format($total) }}</td></tr>
                </tbody>
            </table>
            <p class="muted" style="margin-top:8px">{{ T::label('Prices include GST. GST is :rate% of the price before GST.', ['rate' => $rate]) }}</p>
        @endif

        @if($part->gst_reversed_at)
            <div class="note warn" data-reversed>{{ T::label('Cancelled on :date: this sale was reversed and the payment refunded.', ['date' => $part->gst_reversed_at->format('j M Y')]) }}</div>
        @endif

        <h2>{{ T::label('Payment') }}</h2>
        <p class="muted">{{ T::label('Paid :date. iruali collects payment for the shops on its marketplace.', ['date' => $order->paid_at?->format('j M Y, H:i')]) }}</p>

        <div class="note">
            {{ T::label('Issued through iruali for :shop. Please keep it for your records.', ['shop' => $shopName]) }}
            <br><span class="muted">{{ Company::legalName() ?? Company::tradingName() }}@if(Company::address()) · {{ Company::address() }}@endif</span>
        </div>
    </main>
</body>
</html>
