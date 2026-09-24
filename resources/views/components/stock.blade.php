@props(['quantity'])
@if($quantity <= 0)
    <p {{ $attributes->merge(['class' => 'text-xs font-semibold text-danger']) }}>{{ __('Out of stock') }}</p>
@elseif($quantity <= 5)
    <p {{ $attributes->merge(['class' => 'text-xs font-semibold text-sun-ink']) }}>{{ __('Only :count left', ['count' => $quantity]) }}</p>
@else
    <p {{ $attributes->merge(['class' => 'text-xs font-semibold text-success flex items-center gap-1']) }}><x-icon name="check" class="w-3.5 h-3.5" />{{ __('In stock') }}</p>
@endif
