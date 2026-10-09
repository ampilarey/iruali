{{-- One cart line's shop-funded savings: its multi-buy saving, its share of a shop code, and how many more units reach the next multi-buy tier. --}}
@if($deal)
    @if($deal['multibuy'] > 0)
        <p class="text-xs font-semibold text-coral">{{ __('Multi-buy :percent% off', ['percent' => \App\Models\ShopDiscountCode::percentText((float) $deal['multibuy_percent'])]) }}: <span dir="ltr">&minus;{{ \App\Support\Money::format($deal['multibuy']) }}</span></p>
    @endif
    @if($deal['code'] > 0)
        <p class="text-xs font-semibold text-success">{{ __('Shop code') }}: <span dir="ltr">&minus;{{ \App\Support\Money::format($deal['code']) }}</span></p>
    @endif
    @if($deal['next_tier'])
        <p class="text-xs text-gray-600">
            {{ $deal['group']
                ? __('Add :count more from “:group” to save :percent%', ['count' => $deal['next_tier']['more'], 'group' => $deal['group'], 'percent' => \App\Models\ShopDiscountCode::percentText($deal['next_tier']['percent'])])
                : __('Add :count more to save :percent%', ['count' => $deal['next_tier']['more'], 'percent' => \App\Models\ShopDiscountCode::percentText($deal['next_tier']['percent'])]) }}
        </p>
    @endif
@endif
