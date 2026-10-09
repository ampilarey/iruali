{{-- Printable packing slip for one shop's part of an order (App\Support\PackingSlip). A gift goes to
     the receiver and carries the gift message; with "hide prices" it prints without any prices.
     Needs $order, $part, $items and $hidePrices. --}}
@php
    use App\Support\Money;
    $shop = $part->shopName();
    $backUrl = request()->routeIs('admin.*') ? route('admin.orders.show', $order) : route('seller.orders.show', $order);
    $recipient = $order->isGift() && $order->gift_receiver_name ? $order->gift_receiver_name : $order->customerName();
    $phone = $order->isGift() && $order->gift_receiver_phone ? $order->gift_receiver_phone : $order->shipping_phone;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'dv' && ! request()->routeIs('admin.*', 'seller.*') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Packing slip') }} {{ $order->order_number }} · {{ $shop }}</title>
    <style>
        body { font-family: Figtree, "Noto Sans Thaana", "MV Boli", ui-sans-serif, system-ui, sans-serif; color: #0F2A3A; margin: 0; background: #F5F8F7; }
        .sheet { max-width: 760px; margin: 24px auto; background: #fff; border: 1px solid #D5E1DF; border-radius: 12px; padding: 32px; }
        h1 { font-size: 22px; margin: 0; } h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .05em; color: #5F7680; margin: 24px 0 8px; }
        .muted { color: #5F7680; font-size: 13px; } .row { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; } th, td { text-align: start; padding: 8px 0; border-bottom: 1px solid #EAF0EF; } td.num, th.num { text-align: end; }
        .total td { border: 0; font-weight: 700; padding-top: 10px; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #EAF0EF; }
        .gift { margin-top: 24px; padding: 16px 18px; border: 2px dashed #E7A3B8; border-radius: 12px; background: #FFF6F9; }
        .gift p { margin: 0; } .gift .message { margin-top: 8px; font-size: 16px; white-space: pre-line; }
        .tick { display: inline-block; width: 14px; height: 14px; border: 1px solid #5F7680; border-radius: 3px; vertical-align: middle; }
        .actions { max-width: 760px; margin: 0 auto; display: flex; gap: 8px; justify-content: flex-end; padding: 0 4px; }
        .btn { font: inherit; font-size: 14px; font-weight: 600; padding: 8px 14px; border-radius: 8px; border: 1px solid #0B7A70; background: #0B7A70; color: #fff; cursor: pointer; text-decoration: none; }
        .btn.alt { background: #fff; color: #0B7A70; }
        @media print { body { background: #fff; } .sheet { border: 0; margin: 0; padding: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions" style="margin-top:16px">
        <a href="{{ $backUrl }}" class="btn alt">{{ __('Back to order') }}</a>
        <button type="button" class="btn" onclick="window.print()">{{ __('Print') }}</button>
    </div>
    <main class="sheet">
        <div class="row">
            <div>
                <img src="/images/brand/iruali-logo.svg" alt="iruali" height="32">
                <p class="muted" style="margin:8px 0 0">{{ __('From :shop', ['shop' => $shop]) }}</p>
            </div>
            <div style="text-align:end">
                <h1>{{ __('Packing slip') }}</h1>
                <p class="muted" style="margin:4px 0">{{ __('Order') }} <strong style="color:#0F2A3A">{{ $order->order_number }}</strong></p>
                <p class="muted" style="margin:0">{{ $order->created_at->timezone('Indian/Maldives')->translatedFormat('j M Y') }}</p>
                @if($order->isGift())<p style="margin:8px 0 0"><span class="badge">{{ __('Gift') }}</span></p>@endif
            </div>
        </div>

        @if($part->isPickup())
            <h2>{{ __('Collected by the customer') }}</h2>
            <p style="margin:0;font-size:14px">{{ $order->customerName() }}@if($order->shipping_phone)<br>{{ __('Phone') }}: <span dir="ltr">{{ $order->shipping_phone }}</span>@endif</p>
            <p class="muted" style="margin:6px 0 0">{{ __('Hand over only when the customer gives the right 6-digit pickup code, and confirm it on the order page.') }}</p>
        @else
            <h2>{{ __('Deliver to') }}</h2>
            <p style="margin:0;font-size:14px">
                <strong>{{ $recipient }}</strong><br>
                {{ $order->shipping_address }}<br>
                {{ collect([$order->shipping_city, $order->shipping_state, $order->shipping_zip])->filter()->join(', ') }}@if($order->shipping_country), {{ $order->shipping_country }}@endif
                @if($phone)<br>{{ __('Phone') }}: <span dir="ltr">{{ $phone }}</span>@endif
            </p>
            @if($order->deliverySlotLabel())
                <p style="margin:6px 0 0;font-size:14px"><strong>{{ __('Delivery time') }}:</strong> <span dir="ltr">{{ $order->deliverySlotLabel() }}</span></p>
            @endif
        @endif

        <h2>{{ __('Items') }}</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:24px"><span class="tick" aria-hidden="true"></span></th>
                    <th>{{ __('Product') }}</th>
                    <th>{{ __('SKU') }}</th>
                    <th class="num">{{ __('Qty') }}</th>
                    @unless($hidePrices)
                        <th class="num">{{ __('Price') }}</th>
                        <th class="num">{{ __('Amount') }}</th>
                    @endunless
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td><span class="tick" aria-hidden="true"></span></td>
                        <td>{{ $item->displayName() }}</td>
                        <td class="muted" dir="ltr">{{ $item->variant_sku ?: $item->product?->sku }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                        @unless($hidePrices)
                            <td class="num">{{ Money::format($item->price) }}</td>
                            <td class="num">{{ Money::format($item->price * $item->quantity) }}</td>
                        @endunless
                    </tr>
                @endforeach
                @unless($hidePrices)
                    <tr class="total"><td></td><td colspan="4">{{ __('Total') }}</td><td class="num">{{ Money::format($items->sum(fn ($item) => $item->price * $item->quantity)) }}</td></tr>
                @endunless
            </tbody>
        </table>

        @if($order->isGift())
            <div class="gift">
                <p><strong>{{ __('A gift for you') }}@if($order->gift_receiver_name), {{ $order->gift_receiver_name }}@endif</strong></p>
                @if($order->gift_message)
                    <p class="message">{{ $order->gift_message }}</p>
                @endif
                <p class="muted" style="margin-top:8px">{{ __('Sent to you through iruali.') }}</p>
            </div>
        @endif
    </main>
</body>
</html>
