{{--
    An order's shop-funded discounts (multi-buy savings and each shop code used), for order summaries.
    $as: 'rows' (customer pages: the items' total first, then the discounts, before the subtotal),
    'table' (receipt rows) or 'dl' (admin).
--}}
@php
    $shopDiscountTotal = round((float) $order->shop_discount, 2);
    $shopDiscountRows = [];
    if ($shopDiscountTotal > 0) {
        $multibuyTotal = round((float) $order->items->sum('multibuy_discount'), 2);
        if ($multibuyTotal > 0) {
            $shopDiscountRows[] = [__('Multi-buy savings'), $multibuyTotal];
        }
        foreach (\App\Models\ShopDiscountRedemption::where('order_id', $order->id)->with('seller')->orderBy('id')->get() as $use) {
            $shopDiscountRows[] = [__('Shop code :code (:shop)', ['code' => $use->code, 'shop' => $use->seller?->shopName() ?? '']), (float) $use->amount];
        }
    }
    $as = $as ?? 'rows';
@endphp
@if($shopDiscountRows)
    @if($as === 'table')
        @foreach($shopDiscountRows as [$label, $amount])
            <tr><td>{{ $label }}</td><td class="num">&minus;{{ \App\Support\Money::format($amount) }}</td></tr>
        @endforeach
    @elseif($as === 'dl')
        @foreach($shopDiscountRows as [$label, $amount])
            <div class="flex justify-between text-gray-600"><dt>{{ $label }}</dt><dd>−{{ \App\Support\Money::format($amount) }}</dd></div>
        @endforeach
    @else
        <div class="flex justify-between">
            <span class="text-gray-600">{{ __('Items') }}</span>
            <span class="text-gray-900 force-ltr" dir="ltr">{{ \App\Support\Money::format($order->items->sum(fn ($i) => $i->price * $i->quantity)) }}</span>
        </div>
        @foreach($shopDiscountRows as [$label, $amount])
            <div class="flex justify-between gap-2">
                <span class="text-green-700">{{ $label }}</span>
                <span class="text-green-700 force-ltr" dir="ltr">-{{ \App\Support\Money::format($amount) }}</span>
            </div>
        @endforeach
    @endif
@endif
