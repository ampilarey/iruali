{{-- The cart's "Shop code" box: one shop's own code per shop, for its items; it can be used with one iruali voucher. --}}
@php $shopNotices = app(\App\Services\ShopDiscountService::class)->pullNotices(); @endphp
<div class="space-y-2" id="shop-codes">
    @foreach($shopNotices as $notice)
        <p class="rounded-lg bg-sun-soft px-3 py-2 text-xs text-sun-ink">{{ __('A shop code was removed: :reason', ['reason' => $notice]) }}</p>
    @endforeach
    @if(! empty($shopDeals['voucher_error']))
        <p class="rounded-lg bg-sun-soft px-3 py-2 text-xs text-sun-ink">{{ __('Your voucher was removed: :reason', ['reason' => $shopDeals['voucher_error']]) }}</p>
    @endif

    @foreach($shopDeals['codes'] ?? [] as $sellerId => $applied)
        <form action="{{ route('cart.shopCode.remove') }}" method="POST" class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
            @csrf
            <input type="hidden" name="seller_id" value="{{ $sellerId }}">
            <span class="flex min-w-0 items-center gap-1.5"><x-icon name="tag" class="w-4 h-4 shrink-0 text-success" /><span class="font-mono font-semibold" dir="ltr">{{ $applied['code']->code }}</span><span class="truncate text-gray-600">· {{ $applied['shop'] }}</span></span>
            <button type="submit" class="shrink-0 text-danger hover:underline">{{ __('Remove') }}</button>
        </form>
    @endforeach

    <form action="{{ route('cart.shopCode.apply') }}" method="POST">
        @csrf
        <label for="shop_code" class="text-sm font-medium text-gray-700">{{ __('Shop code') }}</label>
        <div class="mt-1 flex gap-2">
            <input id="shop_code" type="text" name="shop_code" maxlength="30" autocomplete="off" dir="ltr" value="{{ old('shop_code') }}" class="flex-1 min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm uppercase focus:border-primary focus:ring-primary" required>
            <button type="submit" class="px-4 rounded-lg border border-gray-300 font-semibold text-sm hover:bg-gray-50">{{ __('Apply') }}</button>
        </div>
        <p class="mt-1 text-xs text-gray-500">{{ __('A shop\'s own code, for its items. One code per shop; you can use it with an iruali voucher.') }}</p>
        @error('shop_code')<p class="text-danger text-sm mt-1">{{ $message }}</p>@enderror
    </form>
</div>
