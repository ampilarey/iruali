@props(['ends', 'compact' => false])
<p {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-md bg-coral-soft text-coral font-semibold '.($compact ? 'text-[11px] px-1.5 py-0.5' : 'text-sm px-2.5 py-1')]) }}>
    <x-icon name="clock" class="{{ $compact ? 'w-3 h-3' : 'w-4 h-4' }}" />
    <span>{{ __('Deal ends in') }}</span>
    <span dir="ltr" class="tabular-nums" data-countdown="{{ $ends->toIso8601String() }}" data-ended="{{ __('Deal ended') }}">{{ $ends->diffForHumans(['parts' => 2, 'short' => true, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}</span>
</p>
