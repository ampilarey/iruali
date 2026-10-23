{{-- Seller product form: pre-orders while the product is out of stock (App\Services\PreorderService).
     The date (or days after the order), the limit, the note and what the customer pays at checkout (everything, a
     booking fee: App\Services\BookingFeeService, or nothing until the shop confirms: App\Services\PreorderConfirmService)
     only count while pre-orders are on. Only for a shop an admin allowed pre-orders. Needs $product and $field. --}}
@php
    $preorderOn = (bool) old('preorder_enabled', $product->preorder_enabled);
    $preorderWaiting = $product->exists ? app(\App\Services\PreorderService::class)->waitingUnits((int) $product->id) : 0;
    $preorderShips = old('preorder_ships', (int) $product->preorder_lead_days > 0 ? 'days' : 'date');
    $depositType = old('preorder_deposit_type', $product->preorder_confirm_first ? \App\Services\PreorderService::CONFIRM_FIRST : ($product->preorder_deposit_type ?? 'none'));
    $isFee = in_array($depositType, \App\Services\BookingFeeService::TYPES, true);
    $isConfirm = $depositType === \App\Services\PreorderService::CONFIRM_FIRST;
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

    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 {{ $preorderOn ? '' : 'opacity-60' }}" data-preorder-fields>
        <fieldset class="sm:col-span-2 lg:col-span-4">
            <legend class="block text-sm font-medium text-gray-700">{{ __('When does it ship?') }}</legend>
            <div class="mt-1 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-700">
                <label class="flex items-center gap-2"><input type="radio" name="preorder_ships" value="date" @checked($preorderShips !== 'days') class="text-primary-600 focus:ring-primary-500" data-preorder-ships> {{ __('On a date (one shipment for everyone)') }}</label>
                <label class="flex items-center gap-2"><input type="radio" name="preorder_ships" value="days" @checked($preorderShips === 'days') class="text-primary-600 focus:ring-primary-500" data-preorder-ships> {{ __('Some days after each order (you order it for each customer)') }}</label>
            </div>
        </fieldset>
        <div data-preorder-ships-date @class(['hidden' => $preorderShips === 'days'])>
            <label for="preorder_ship_date" class="block text-sm font-medium text-gray-700">{{ __('Expected ship date') }} *</label>
            <input id="preorder_ship_date" name="preorder_ship_date" type="date" min="{{ today()->addDay()->toDateString() }}" dir="ltr" class="{{ $field }}" value="{{ old('preorder_ship_date', $product->preorder_ship_date?->toDateString()) }}" aria-describedby="preorder_ship_date-hint">
            <p id="preorder_ship_date-hint" class="mt-1 text-xs text-gray-500">{{ __('When you expect to send them. Moving it later emails the waiting customers.') }}</p>
            @error('preorder_ship_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div data-preorder-ships-days @class(['hidden' => $preorderShips !== 'days'])>
            <label for="preorder_lead_days" class="block text-sm font-medium text-gray-700">{{ __('Ships about how many days after the order?') }} *</label>
            <input id="preorder_lead_days" name="preorder_lead_days" type="number" min="1" max="{{ \App\Services\PreorderService::MAX_LEAD_DAYS }}" step="1" dir="ltr" class="{{ $field }}" value="{{ old('preorder_lead_days', $product->preorder_lead_days) }}" aria-describedby="preorder_lead_days-hint">
            <p id="preorder_lead_days-hint" class="mt-1 text-xs text-gray-500">{{ __('Your usual time to get it and send it, e.g. 14. Each order gets its own date.') }}</p>
            @error('preorder_lead_days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="preorder_limit" class="block text-sm font-medium text-gray-700">{{ __('Most units to take') }} *</label>
            <input id="preorder_limit" name="preorder_limit" type="number" min="1" max="{{ \App\Services\PreorderService::MAX_LIMIT }}" step="1" dir="ltr" class="{{ $field }}" value="{{ old('preorder_limit', $product->preorder_limit) }}" aria-describedby="preorder_limit-hint">
            <p id="preorder_limit-hint" class="mt-1 text-xs text-gray-500">{{ __('Units waiting for stock at any one time, counted across all the options.') }}</p>
            @error('preorder_limit')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="preorder_closes_on" class="block text-sm font-medium text-gray-700">{{ __('Last day to pre-order') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
            <input id="preorder_closes_on" name="preorder_closes_on" type="date" min="{{ today()->toDateString() }}" dir="ltr" class="{{ $field }}" value="{{ old('preorder_closes_on', $product->preorder_closes_on?->toDateString()) }}" aria-describedby="preorder_closes_on-hint">
            <p id="preorder_closes_on-hint" class="mt-1 text-xs text-gray-500">{{ __('Stop taking pre-orders after this day, for example when you place your order with your supplier. Empty: until the ship date.') }}</p>
            @error('preorder_closes_on')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="preorder_note" class="block text-sm font-medium text-gray-700">{{ __('Note for shoppers') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
            <input id="preorder_note" name="preorder_note" maxlength="255" dir="auto" class="{{ $field }}" value="{{ old('preorder_note', $product->preorder_note) }}" placeholder="{{ __('e.g. Arrives on the next ship from Colombo') }}">
            @error('preorder_note')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2 lg:col-span-4">
            <label for="source_url" class="block text-sm font-medium text-gray-700">{{ __('Where you buy it (link)') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
            <input id="source_url" name="source_url" type="url" maxlength="500" dir="ltr" class="{{ $field }}" value="{{ old('source_url', $product->source_url) }}" placeholder="https://" aria-describedby="source_url-hint">
            <p id="source_url-hint" class="mt-1 text-xs text-gray-500">{{ __('The product\'s page on the shop you order it from. Only iruali sees it, to check what is bought; shoppers don\'t.') }}</p>
            @error('source_url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2" data-preorder-late-days>
            <label for="preorder_late_days" class="block text-sm font-medium text-gray-700">{{ __('Late after how many days?') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
            <input id="preorder_late_days" name="preorder_late_days" type="number" min="1" max="{{ \App\Services\PreorderService::MAX_DAYS }}" step="1" dir="ltr" class="{{ $field }}" value="{{ old('preorder_late_days', $product->preorder_late_days) }}" placeholder="{{ \App\Services\PreorderService::LATE_AFTER_DAYS }}" aria-describedby="preorder_late_days-hint">
            <p id="preorder_late_days-hint" class="mt-1 text-xs text-gray-500">{{ __('Days past the expected date before an order counts as late: iruali sees it as late, and with a booking fee the customer can then cancel for everything back. Empty: :days.', ['days' => \App\Services\PreorderService::LATE_AFTER_DAYS]) }}</p>
            @error('preorder_late_days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- What the customer pays at checkout: everything; a booking fee (BookingFeeService), the rest when you record the
             stock as arrived; or nothing until you confirm you can get it, then everything (PreorderConfirmService) --}}
        <div class="sm:col-span-2" data-preorder-deposit>
            <label for="preorder_deposit_type" class="block text-sm font-medium text-gray-700">{{ __('What the customer pays at checkout') }}</label>
            <select id="preorder_deposit_type" name="preorder_deposit_type" class="{{ $field }}" aria-describedby="preorder_deposit-hint" data-preorder-deposit-type>
                <option value="none" @selected(! $isFee && ! $isConfirm)>{{ __('The full price') }}</option>
                <option value="percent" @selected($depositType === 'percent')>{{ __('A booking fee: a percentage of the price') }}</option>
                <option value="fixed" @selected($depositType === 'fixed')>{{ __('A booking fee: an amount per item') }}</option>
                <option value="{{ \App\Services\PreorderService::CONFIRM_FIRST }}" @selected($isConfirm)>{{ __('Nothing yet: the full price once I confirm I can get it') }}</option>
            </select>
            @error('preorder_deposit_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div @class(['sm:col-span-2', 'hidden' => ! $isFee]) data-preorder-deposit-value data-preorder-deposit-only>
            <label for="preorder_deposit_value" class="block text-sm font-medium text-gray-700">{{ __('Booking fee') }} * <span class="font-normal text-gray-500">(<span data-unit-percent @class(['hidden' => $depositType !== 'percent'])>%</span><span data-unit-amount @class(['hidden' => $depositType === 'percent'])>{{ \App\Support\Money::prefix() }}{{ __('per item') }}</span>)</span></label>
            <input id="preorder_deposit_value" name="preorder_deposit_value" type="number" min="1" step="0.01" dir="ltr" class="{{ $field }}" value="{{ old('preorder_deposit_value', $product->preorder_deposit_value !== null ? rtrim(rtrim((string) $product->preorder_deposit_value, '0'), '.') : null) }}" aria-describedby="preorder_deposit-hint">
            @error('preorder_deposit_value')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        {{-- Days to pay: the rest once it arrives (booking fee), or everything once you confirm --}}
        <div @class(['sm:col-span-2', 'hidden' => ! $isFee && ! $isConfirm]) data-preorder-balance-days>
            <label for="preorder_balance_days" class="block text-sm font-medium text-gray-700"><span data-days-fee @class(['hidden' => $isConfirm])>{{ __('Days to pay the rest once it arrives') }}</span><span data-days-confirm @class(['hidden' => ! $isConfirm])>{{ __('Days to pay once you confirm') }}</span> <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
            <input id="preorder_balance_days" name="preorder_balance_days" type="number" min="1" max="{{ \App\Services\PreorderService::MAX_DAYS }}" step="1" dir="ltr" class="{{ $field }}" value="{{ old('preorder_balance_days', $product->preorder_balance_days) }}" placeholder="{{ $isConfirm ? \App\Services\PreorderConfirmService::PAY_DAYS : \App\Services\BookingFeeService::BALANCE_DAYS }}" data-placeholder-fee="{{ \App\Services\BookingFeeService::BALANCE_DAYS }}" data-placeholder-confirm="{{ \App\Services\PreorderConfirmService::PAY_DAYS }}" aria-describedby="preorder_balance_days-hint">
            <p id="preorder_balance_days-hint" class="mt-1 text-xs text-gray-500"><span data-days-fee @class(['hidden' => $isConfirm])>{{ __('When you record it as arrived, the customer gets a payment link and this many days to pay the rest. Empty: :days.', ['days' => \App\Services\BookingFeeService::BALANCE_DAYS]) }}</span><span data-days-confirm @class(['hidden' => ! $isConfirm])>{{ __('When you confirm you can get it, the customer gets a payment link and this many days to pay; unpaid after that, the order is cancelled. Empty: :days.', ['days' => \App\Services\PreorderConfirmService::PAY_DAYS]) }}</span></p>
            @error('preorder_balance_days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <p id="preorder_deposit-hint" class="sm:col-span-2 lg:col-span-4 text-xs text-gray-500">{{ __('With a booking fee the customer pays only the fee at checkout and the rest when you record the stock as arrived, before you send it. If they cancel, you keep the fee (less commission); if you cancel, can\'t get the item, move the date later or it is later than the days you allow, they get everything back. Booking-fee items are ordered on their own. If your shop is GST-registered, the fee gets its own tax invoice.') }}
            {{ __('With "Nothing yet", the customer orders without paying; you check with your supplier, then confirm it under Pre-orders (they get a link to pay in full) or say you can\'t get it (the order is cancelled). Order the item once it shows as paid. These items are ordered on their own too.') }}</p>
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
        // On a date, or some days after each order
        box.querySelectorAll('[data-preorder-ships]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                box.querySelector('[data-preorder-ships-date]').classList.toggle('hidden', radio.value === 'days');
                box.querySelector('[data-preorder-ships-days]').classList.toggle('hidden', radio.value !== 'days');
            });
        });
        // The booking fee (a percentage or an amount per item), or nothing until the shop confirms; both have days to pay
        var type = box.querySelector('[data-preorder-deposit-type]');
        type.addEventListener('change', function () {
            var fee = type.value === 'percent' || type.value === 'fixed', confirm = type.value === 'confirm';
            box.querySelectorAll('[data-preorder-deposit-only]').forEach(function (el) { el.classList.toggle('hidden', !fee); });
            box.querySelector('[data-unit-percent]').classList.toggle('hidden', type.value !== 'percent');
            box.querySelector('[data-unit-amount]').classList.toggle('hidden', type.value === 'percent');
            box.querySelector('[data-preorder-balance-days]').classList.toggle('hidden', !fee && !confirm);
            box.querySelectorAll('[data-days-fee]').forEach(function (el) { el.classList.toggle('hidden', confirm); });
            box.querySelectorAll('[data-days-confirm]').forEach(function (el) { el.classList.toggle('hidden', !confirm); });
            var days = box.querySelector('#preorder_balance_days');
            days.placeholder = confirm ? days.dataset.placeholderConfirm : days.dataset.placeholderFee;
        });
    })();
</script>
@endpush
