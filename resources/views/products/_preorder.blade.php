{{-- Product page buy box: the pre-order offer (PreorderService::productOffer()) when the product, or some of its
     options, is out of stock and the shop takes pre-orders. With only some options on pre-order the box shows
     once one of them is picked (resources/js/variant-picker.js). Needs $preorder (null when there is none). --}}
@if($preorder)
    <div class="mt-3 rounded-lg border border-primary-200 bg-primary-50 p-3 text-sm {{ $preorder['whole'] ? '' : 'hidden' }}" data-preorder-notice role="status">
        <p class="font-semibold text-dark flex items-center gap-2"><x-icon name="clock" class="w-4 h-4 shrink-0 text-primary" />{{ __('Pre-order: ships around :date', ['date' => $preorder['date']->translatedFormat('j M')]) }}</p>
        @if($preorder['note'])
            <p class="mt-1 ps-6 text-gray-700" dir="auto">{{ $preorder['note'] }}</p>
        @endif
        <p class="mt-1 ps-6 text-xs text-gray-600">{{ __('You pay now and the shop sends it when the stock arrives. You can cancel for a full refund until it is sent.') }}</p>
        @if($preorder['left'] <= 5)
            <p class="mt-1 ps-6 text-xs font-semibold text-sun-ink">{{ __('Only :count left to pre-order', ['count' => $preorder['left']]) }}</p>
        @endif
    </div>
@endif
