{{-- Seller product form: "Coming soon" (App\Services\ComingSoonService). Listed but not on sale until the launch day,
     or until it is switched off; shoppers can ask to be emailed. Applies at once, without waiting for a review.
     Needs $product and $field. --}}
@php $comingSoonOn = (bool) old('coming_soon', $product->coming_soon); @endphp
<fieldset class="rounded-lg border border-gray-200 p-4" data-coming-soon-fieldset>
    <legend class="px-1 text-sm font-medium text-gray-700">{{ __('Coming soon') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></legend>
    <input type="hidden" name="coming_soon_form" value="1">
    <input type="hidden" name="coming_soon" value="0">
    <label class="flex items-start gap-2 text-sm text-gray-700">
        <input type="checkbox" name="coming_soon" value="1" @checked($comingSoonOn) class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" data-coming-soon-toggle>
        <span>
            <span class="font-medium text-gray-900">{{ __('Show it as "Coming soon"') }}</span>
            <span class="block text-xs text-gray-500">{{ __('Customers can see it and ask to be emailed, but can\'t order it yet. Untick this to put it on sale now; everyone who asked gets an email. To take payment before it arrives, use Pre-order below instead.') }}</span>
        </span>
    </label>
    <div class="mt-3 max-w-xs {{ $comingSoonOn ? '' : 'opacity-60' }}" data-coming-soon-fields>
        <label for="available_on" class="block text-sm font-medium text-gray-700">{{ __('On sale from') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
        <input id="available_on" name="available_on" type="date" min="{{ today()->addDay()->toDateString() }}" dir="ltr" class="{{ $field }}" value="{{ old('available_on', $product->available_on?->toDateString()) }}" aria-describedby="available_on-hint">
        <p id="available_on-hint" class="mt-1 text-xs text-gray-500">{{ __('It goes on sale by itself that morning. Empty: it stays "Coming soon" until you untick it.') }}</p>
        @error('available_on')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</fieldset>

@push('scripts')
<script>
    (function () {
        var box = document.querySelector('[data-coming-soon-fieldset]');
        if (!box) return;
        var toggle = box.querySelector('[data-coming-soon-toggle]'), fields = box.querySelector('[data-coming-soon-fields]');
        toggle.addEventListener('change', function () { fields.classList.toggle('opacity-60', !toggle.checked); });
    })();
</script>
@endpush
