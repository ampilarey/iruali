{{-- Product form: "Multi-buy offer" (up to 3 quantity tiers, for this product or a mix-and-match group). Saved by MultiBuyService. --}}
@php
    $multibuyOffer = $product->exists ? $product->multibuyOffer : null;
    $multibuyGroups = auth()->user() ? app(\App\Services\MultiBuyService::class)->groupsFor(auth()->user()) : collect();
    $multibuyTiers = array_values((array) old('multibuy.tiers', $multibuyOffer?->tierList() ?? []));
    $multibuyGroup = (string) old('multibuy.group', $multibuyOffer?->isShared() ? $multibuyOffer->id : '');
    $multibuyNumber = fn ($value) => $value === null || $value === '' ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    $multibuyField = 'rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
@endphp
<fieldset class="sm:col-span-3 rounded-lg border border-gray-200 p-4" data-multibuy>
    <legend class="px-1 text-sm font-semibold text-gray-900">{{ __('Multi-buy offer') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></legend>
    <p class="text-xs text-gray-500">{{ __('A discount when a customer buys more, e.g. 2 or more: 5% off, 3 or more: 10% off. Your shop pays for it, like a discount code: commission and your earnings are worked out on the discounted price.') }}</p>

    <div class="mt-3 space-y-2">
        @for($i = 0; $i < \App\Models\MultiBuyOffer::MAX_TIERS; $i++)
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <label for="multibuy-qty-{{ $i }}" class="w-24 text-gray-700">{{ __('Tier :n: buy', ['n' => $i + 1]) }}</label>
                <input id="multibuy-qty-{{ $i }}" name="multibuy[tiers][{{ $i }}][min_qty]" type="number" min="2" max="999" step="1" dir="ltr" class="{{ $multibuyField }} w-20" value="{{ $multibuyTiers[$i]['min_qty'] ?? '' }}" data-multibuy-qty>
                <label for="multibuy-percent-{{ $i }}" class="text-gray-700">{{ __('or more, get') }}</label>
                <input id="multibuy-percent-{{ $i }}" name="multibuy[tiers][{{ $i }}][percent]" type="number" min="1" max="90" step="0.01" dir="ltr" class="{{ $multibuyField }} w-20" value="{{ $multibuyNumber($multibuyTiers[$i]['percent'] ?? '') }}" data-multibuy-percent>
                <span class="text-gray-700">{{ __('% off each') }}</span>
            </div>
        @endfor
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <div>
            <label for="multibuy-group" class="block text-sm font-medium text-gray-700">{{ __('Mix and match') }}</label>
            <select id="multibuy-group" name="multibuy[group]" class="mt-1 block w-full {{ $multibuyField }}" data-multibuy-group>
                <option value="">{{ __('This product only') }}</option>
                @foreach($multibuyGroups as $group)
                    <option value="{{ $group->id }}" data-tiers="{{ json_encode($group->tierList()) }}" @selected($multibuyGroup === (string) $group->id)>{{ $group->name }} ({{ trans_choice(':count product|:count products', $group->products_count, ['count' => $group->products_count]) }})</option>
                @endforeach
                <option value="new" @selected($multibuyGroup === 'new')>{{ __('New group…') }}</option>
            </select>
        </div>
        <div data-multibuy-new class="{{ $multibuyGroup === 'new' ? '' : 'hidden' }}">
            <label for="multibuy-group-name" class="block text-sm font-medium text-gray-700">{{ __('Name of the new group') }}</label>
            <input id="multibuy-group-name" name="multibuy[group_name]" maxlength="80" class="mt-1 block w-full {{ $multibuyField }}" value="{{ old('multibuy.group_name') }}" placeholder="{{ __('e.g. T-shirts') }}">
        </div>
    </div>
    <p class="mt-2 text-xs text-gray-500">{{ __('Products in the same group count together: one T-shirt and one polo shirt make 2. The tiers above are the group\'s tiers, for every product in it; leave them empty to keep the group\'s own.') }}</p>
</fieldset>

@push('scripts')
<script>
    (function () {
        var box = document.querySelector('[data-multibuy]');
        if (!box) return;
        var group = box.querySelector('[data-multibuy-group]'), named = box.querySelector('[data-multibuy-new]');
        var qty = box.querySelectorAll('[data-multibuy-qty]'), pct = box.querySelectorAll('[data-multibuy-percent]');
        group.addEventListener('change', function () {
            named.classList.toggle('hidden', group.value !== 'new');
            var option = group.options[group.selectedIndex];
            if (!option || !option.dataset.tiers) return;
            // Show the group's tiers, so saving keeps them unless they are changed here
            var tiers = JSON.parse(option.dataset.tiers);
            qty.forEach(function (input, i) { input.value = tiers[i] ? tiers[i].min_qty : ''; });
            pct.forEach(function (input, i) { input.value = tiers[i] ? tiers[i].percent : ''; });
        });
    })();
</script>
@endpush
