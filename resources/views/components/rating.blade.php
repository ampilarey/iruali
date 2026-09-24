@props(['value' => 0, 'count' => null, 'size' => 'sm'])
<span {{ $attributes->merge(['class' => 'flex items-center gap-1']) }} title="{{ __(':rating out of 5', ['rating' => $value]) }}">
    <span class="flex" dir="ltr" aria-hidden="true">
        @for($i = 1; $i <= 5; $i++)
            <x-icon name="star" class="{{ $size === 'lg' ? 'w-5 h-5' : 'w-3.5 h-3.5' }} {{ $i <= round($value) ? 'text-sun' : 'text-gray-300' }}" />
        @endfor
    </span>
    <span class="sr-only">{{ __(':rating out of 5', ['rating' => $value]) }}</span>
    @if($count !== null)<span class="{{ $size === 'lg' ? 'text-sm' : 'text-xs' }} text-gray-500">({{ $count }})</span>@endif
</span>
