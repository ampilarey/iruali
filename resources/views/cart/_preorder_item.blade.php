{{-- Cart line: "Pre-order · ships around 3 Nov" (with the shop's note) when the line would be ordered as a
     pre-order (PreorderService::cartLine()), else the usual stock line. Needs $item. --}}
@php $preorderLine = app(\App\Services\PreorderService::class)->cartLine($item); @endphp
@if($preorderLine)
    <p class="mt-1 inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700" data-preorder-line>
        <x-icon name="clock" class="w-3.5 h-3.5" />{{ __('Pre-order · ships around :date', ['date' => $preorderLine['date']->translatedFormat('j M')]) }}
    </p>
    @if($preorderLine['note'])
        <p class="mt-0.5 text-xs text-gray-600" dir="auto">{{ $preorderLine['note'] }}</p>
    @endif
@else
    <x-stock :quantity="$item->availableStock()" class="mt-1" />
@endif
