@php
    use App\Services\PaymentService;
    use App\Support\Company;
    use App\Support\Money;
    $paidTxn = $order->paymentTransactions->firstWhere('state', 'CONFIRMED');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Receipt') }} {{ $order->order_number }} · {{ Company::tradingName() }}</title>
    <style>
        body { font-family: Figtree, ui-sans-serif, system-ui, sans-serif; color: #0F2A3A; margin: 0; background: #F5F8F7; }
        .sheet { max-width: 760px; margin: 24px auto; background: #fff; border: 1px solid #D5E1DF; border-radius: 12px; padding: 32px; }
        h1 { font-size: 22px; margin: 0; } h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .05em; color: #5F7680; margin: 24px 0 8px; }
        .muted { color: #5F7680; font-size: 13px; } .row { display: flex; justify-content: space-between; gap: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; } th, td { text-align: left; padding: 8px 0; border-bottom: 1px solid #EAF0EF; } td.num, th.num { text-align: right; }
        .totals td { border: 0; padding: 4px 0; } .grand td { font-weight: 700; font-size: 16px; border-top: 2px solid #0F2A3A; padding-top: 8px; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #EAF0EF; }
        .paid { background: #D3EDDB; color: #186331; } .note { margin-top: 24px; padding: 12px 14px; background: #F5F8F7; border-radius: 8px; font-size: 13px; }
        .actions { max-width: 760px; margin: 0 auto; display: flex; gap: 8px; justify-content: flex-end; padding: 0 4px; }
        .btn { font: inherit; font-size: 14px; font-weight: 600; padding: 8px 14px; border-radius: 8px; border: 1px solid #0B7A70; background: #0B7A70; color: #fff; cursor: pointer; text-decoration: none; }
        .btn.alt { background: #fff; color: #0B7A70; }
        @media print { body { background: #fff; } .sheet { border: 0; margin: 0; padding: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions" style="margin-top:16px">
        <a href="{{ route('orders.show', $order) }}" class="btn alt">{{ __('Back to order') }}</a>
        <button type="button" class="btn" onclick="window.print()">{{ __('Print / save as PDF') }}</button>
    </div>
    <main class="sheet">
        <div class="row">
            <div>
                <img src="/images/brand/iruali-logo.svg" alt="{{ Company::tradingName() }}" height="36">
                <p class="muted" style="margin:8px 0 0">
                    <strong style="color:#0F2A3A">{{ Company::legalName() ?? Company::tradingName() }}</strong>@if(Company::registrationNo()) · {{ __('Registration no.') }} {{ Company::registrationNo() }}@endif<br>
                    @if(Company::address()){{ Company::address() }}<br>@endif
                    @if(Company::phone()){{ Company::phone() }}@endif @if(Company::phone() && Company::email())·@endif @if(Company::email()){{ Company::email() }}@endif
                </p>
            </div>
            <div style="text-align:right">
                <h1>{{ $order->payment_status === 'paid' ? __('Receipt') : __('Order summary') }}</h1>
                <p class="muted" style="margin:4px 0">{{ __('Order') }} <strong style="color:#0F2A3A">{{ $order->order_number }}</strong></p>
                <p class="muted" style="margin:0">{{ $order->created_at->timezone('Indian/Maldives')->format('j M Y, H:i') }}</p>
                <p style="margin:8px 0 0"><span class="badge {{ $order->payment_status === 'paid' ? 'paid' : '' }}">{{ PaymentService::statusLabel($order->payment_status) }}</span></p>
            </div>
        </div>

        <h2>{{ __('Deliver to') }}</h2>
        <p style="margin:0;font-size:14px">{{ $order->user?->name }}<br>{{ $order->shipping_address }}<br>{{ collect([$order->shipping_city, $order->shipping_state, $order->shipping_zip])->filter()->join(', ') }}, {{ $order->shipping_country }}</p>

        <h2>{{ __('Items') }}</h2>
        <table>
            <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Sold by') }}</th><th class="num">{{ __('Qty') }}</th><th class="num">{{ __('Price') }}</th><th class="num">{{ __('Amount') }}</th></tr></thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->product?->name ?? __('Product') }}</td>
                        <td class="muted">{{ $item->product?->seller ? ($item->product->seller->business_name ?: $item->product->seller->name) : '' }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ Money::format($item->price) }}</td>
                        <td class="num">{{ Money::format($item->price * $item->quantity) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <table class="totals" style="margin-top:8px;max-width:320px;margin-left:auto">
            @if((float) $order->subtotal > 0)<tr><td>{{ __('Subtotal') }}</td><td class="num">{{ Money::format($order->subtotal) }}</td></tr>@endif
            @if((float) $order->voucher_discount > 0)<tr><td>{{ __('Voucher') }} {{ $order->voucher_code }}</td><td class="num">&minus;{{ Money::format($order->voucher_discount) }}</td></tr>@endif
            @if((float) $order->points_redeemed_discount > 0)<tr><td>{{ __('Loyalty points') }}</td><td class="num">&minus;{{ Money::format($order->points_redeemed_discount) }}</td></tr>@endif
            <tr><td>{{ __('Delivery') }}</td><td class="num">{{ Money::format($order->shipping_amount) }}</td></tr>
            <tr class="grand"><td>{{ __('Total') }} (MVR)</td><td class="num">{{ Money::format($order->total_amount) }}</td></tr>
        </table>

        <h2>{{ __('Payment') }}</h2>
        <p style="margin:0;font-size:14px">
            {{ PaymentService::methodLabel($order->payment_method) }} · {{ PaymentService::statusLabel($order->payment_status) }}
            @if($order->paid_at) · {{ $order->paid_at->timezone('Indian/Maldives')->format('j M Y, H:i') }}@endif
            @if($paidTxn)<br><span class="muted">{{ __('Bank of Maldives transaction') }}: {{ $paidTxn->transaction_id }} · {{ __('Reference') }}: {{ $paidTxn->local_id }}</span>@endif
        </p>
        <p class="muted" style="margin:6px 0 0">{{ __('Transaction currency') }}: MVR ({{ __('Maldivian Rufiyaa') }}) · {{ __('Merchant outlet country') }}: {{ __('Maldives') }}</p>

        <div class="note">
            {{ __('Please keep this receipt for your records, together with our Terms & Conditions and Returns, Refunds & Cancellations policy.') }}
            <br><span class="muted">{{ route('policies.terms') }} · {{ route('policies.refunds') }}</span>
        </div>
    </main>
</body>
</html>
