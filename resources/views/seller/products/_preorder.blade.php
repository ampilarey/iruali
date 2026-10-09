{{-- Seller product form: pre-orders while the product is out of stock (App\Services\PreorderService).
     The date, the limit and the note only count while pre-orders are on. Needs $product and $field. --}}
@php
    $preorderOn = (bool) old('preorder_enabled', $product->preorder_enabled);
    $preorderWaiting = $product->exists ? app(\App\Services\PreorderService::class)->waitingUnits((int) $product->id) : 0;
@endphp
<fieldset class="rounded-lg border border-gray-200 p-4" data-preorder-fieldset>
    <legend class="px-1 text-sm font-medium text-gray-700">{{ __('Pre-order') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></legend>
    <input type="hidden" name="preorder_form" value="1">
    <input type="hidden" name="preorder_enabled" value="0">
    <label class="flex items-start gap-2 text-sm text-gray-700">
        <input type="checkbox" name="preorder_enabled" value="1" @checked($preorderOn) class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" data-preorder-toggle>
        <span>
            <span class="font-medium text-gray-900">{{ __('Take pre-orders when it is out of stock') }}</span>
            <span class="block text-xs text-gray-500">{{ __('Customers pay now and you send their units when your next delivery arrives, oldest orders first. With options (size, colour...), every option that runs out can be pre-ordered.') }}</span>
        </span>
    </label>

    <div class="mt-3 grid gap-4 sm:grid-cols-3 {{ $preorderOn ? '' : 'opacity-60' }}" data-preorder-fields>
        <div>
            <label for="preorder_ship_date" class="block text-sm font-medium text-gray-700">{{ __('Expected ship date') }} *</label>
            <input id="preorder_ship_date" name="preorder_ship_date" type="date" min="{{ today()->addDay()->toDateString() }}" dir="ltr" class="{{ $field }}" value="{{ old('preorder_ship_date', $product->preorder_ship_date?->toDateString()) }}" aria-describedby="preorder_ship_date-hint">
            <p id="preorder_ship_date-hint" class="mt-1 text-xs text-gray-500">{{ __('When you expect to send them. Moving it later emails the waiting customers.') }}</p>
            @error('preorder_ship_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="preorder_limit" class="block text-sm font-medium text-gray-700">{{ __('Most units to take') }} *</label>
            <input id="preorder_limit" name="preorder_limit" type="number" min="1" max="{{ \App\Services\PreorderService::MAX_LIMIT }}" step="1" dir="ltr" class="{{ $field }}" value="{{ old('preorder_limit', $product->preorder_limit) }}" aria-describedby="preorder_limit-hint">
            <p id="preorder_limit-hint" class="mt-1 text-xs text-gray-500">{{ __('Units waiting for stock at any one time, counted across all the options.') }}</p>
            @error('preorder_limit')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="preorder_note" class="block text-sm font-medium text-gray-700">{{ __('Note for shoppers') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
            <input id="preorder_note" name="preorder_note" maxlength="255" dir="auto" class="{{ $field }}" value="{{ old('preorder_note', $product->preorder_note) }}" placeholder="{{ __('e.g. Arrives on the next ship from Colombo') }}">
            @error('preorder_note')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    @if($preorderWaiting > 0)
        <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900" data-preorder-waiting>
            {{ trans_choice(':count pre-ordered unit is waiting for stock.|:count pre-ordered units are waiting for stock.', $preorderWaiting, ['count' => $preorderWaiting]) }}
            {{ __('When your delivery arrives, record it under Pre-orders so the waiting customers get their units first.') }}
            <a href="{{ route('seller.preorders') }}" class="font-semibold underline hover:no-underline">{{ __('Pre-orders') }}</a>
        </p>
    @endif
</fieldset>

@push('scripts')
<script>
    (function () {
        var box = document.querySelector('[data-preorder-fieldset]');
        if (!box) return;
        var toggle = box.querySelector('[data-preorder-toggle]'), fields = box.querySelector('[data-preorder-fields]');
        toggle.addEventListener('change', function () { fields.classList.toggle('opacity-60', !toggle.checked); });
    })();
</script>
@endpush
