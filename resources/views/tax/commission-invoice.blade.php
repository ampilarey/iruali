@php
    use App\Support\Company;
    use App\Support\InvoiceText as T;
    use App\Support\Money;

    $details = $payout->invoice_details ?? [];
    $shopName = $details['name'] ?? ($payout->seller?->shopName() ?? '');
    $platformTin = $details['platform_tin'] ?? $invoice['lines']->pluck('order.gst_platform_tin')->filter()->last();
    $title = $invoice['tax_invoice'] ? 'Tax invoice' : 'Invoice';
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __($title, [], 'en') }} {{ $payout->invoice_number }} · {{ Company::tradingName() }}</title>
    @include('tax.partials.document-styles')
</head>
<body>
    <div class="actions">
        <a href="{{ $back }}" class="btn alt">{{ T::label('Back') }}</a>
        <button type="button" class="btn" onclick="window.print()">{{ T::label('Print / save as PDF') }}</button>
    </div>
    <main class="sheet" data-commission-invoice="{{ $invoice['tax_invoice'] ? 'tax' : 'plain' }}">
        <div class="row">
            <div>
                <img src="/images/brand/iruali-logo.svg" alt="{{ Company::tradingName() }}" height="32">
                <p class="strong" style="margin-top:8px">{{ Company::legalName() ?? Company::tradingName() }}</p>
                @if(Company::address())<p class="muted">{{ Company::address() }}</p>@endif
                @if(Company::registrationNo())<p class="muted">{{ T::label('Registration no.') }} {{ Company::registrationNo() }}</p>@endif
                @if($platformTin)<p class="muted" style="margin-top:4px">{{ T::label('GST TIN') }} <strong dir="ltr" style="color:#0F2A3A">{{ $platformTin }}</strong></p>@endif
            </div>
            <div class="end">
                <h1>{{ T::label($title) }}</h1>
                <p class="muted">{{ T::label('Marketplace commission') }}</p>
                <table class="meta">
                    <tr><th>{{ T::label('Invoice no.') }}</th><td dir="ltr">{{ $payout->invoice_number ?? '—' }}</td></tr>
                    <tr><th>{{ T::label('Date') }}</th><td>{{ ($payout->invoiced_at ?? $payout->paid_at)?->format('j M Y') ?? '—' }}</td></tr>
                    <tr><th>{{ T::label('Payout') }}</th><td dir="ltr">#{{ $payout->id }}@if($payout->reference) · {{ $payout->reference }}@endif</td></tr>
                </table>
            </div>
        </div>

        @unless($payout->invoice_number)
            <div class="note warn">{{ T::label('Not issued yet: the invoice number is given when the payout is paid.') }}</div>
        @endunless

        <h2>{{ T::label('Invoice to') }}</h2>
        <p class="strong">{{ $shopName }}</p>
        @if(! empty($details['address']))<p class="muted">{{ $details['address'] }}</p>@endif
        @if(! empty($details['tin']))<p class="muted">{{ T::label('GST TIN') }} <strong dir="ltr" style="color:#0F2A3A">{{ $details['tin'] }}</strong></p>@endif

        <h2>{{ T::label('Commission') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ T::label('Order') }}</th>
                    <th>{{ T::label('Order date') }}</th>
                    <th class="num">{{ T::label('Sales') }}</th>
                    <th class="num">{{ T::label('Rate') }}</th>
                    <th class="num">{{ T::label($invoice['tax_invoice'] ? 'Commission incl. GST' : 'Commission') }}</th>
                    @if($invoice['tax_invoice'])<th class="num">{{ T::label('GST') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($invoice['lines'] as $line)
                    <tr>
                        <td dir="ltr">{{ $line->order?->order_number }}</td>
                        <td>{{ $line->order?->created_at?->format('j M Y') }}</td>
                        <td class="num">{{ Money::format($line->subtotal) }}</td>
                        <td class="num">{{ T::rate($line->commission_rate) }}%</td>
                        <td class="num">{{ Money::format($line->commission_amount) }}</td>
                        @if($invoice['tax_invoice'])<td class="num">{{ Money::format($line->commission_gst) }}</td>@endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">{{ T::label('No orders in this payout.') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tr class="grand"><td>{{ T::label($invoice['tax_invoice'] ? 'Total commission incl. GST' : 'Total commission') }} (MVR)</td><td class="num">{{ Money::format($invoice['commission']) }}</td></tr>
            @if($invoice['tax_invoice'])
                <tr><td>{{ T::label('Value excl. GST') }}</td><td class="num">{{ Money::format($invoice['commission'] - $invoice['gst']) }}</td></tr>
                <tr><td>{{ T::label('GST') }}</td><td class="num">{{ Money::format($invoice['gst']) }}</td></tr>
            @endif
        </table>
        @if($invoice['tax_invoice'])
            <p class="muted" style="margin-top:8px">{{ T::label('Commission includes GST on orders placed while iruali was GST-registered.') }}</p>
        @endif

        <h2>{{ T::label('Settlement') }}</h2>
        <table class="totals" style="margin-inline-start:0">
            <tr><td>{{ T::label('Your sales in this payout') }}</td><td class="num">{{ Money::format($invoice['sales']) }}</td></tr>
            <tr><td>{{ T::label('Commission kept by iruali') }}</td><td class="num">&minus;{{ Money::format($invoice['commission']) }}</td></tr>
            @if($invoice['adjustments'] != 0)<tr><td>{{ T::label('Returns and other adjustments') }}</td><td class="num">{{ $invoice['adjustments'] < 0 ? '−' : '+' }}{{ Money::format(abs($invoice['adjustments'])) }}</td></tr>@endif
            <tr class="grand"><td>{{ T::label('Paid to you') }}</td><td class="num">{{ Money::format($payout->amount) }}</td></tr>
        </table>

        <div class="note">{{ T::label('The commission was kept from your payout, so there is nothing to pay.') }}</div>
    </main>
</body>
</html>
