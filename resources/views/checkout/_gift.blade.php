{{-- Checkout: send the order as a gift. The parcel goes to the address above, for the receiver named
     here; the gift message goes on the packing slip, which can leave the prices out. The customer's
     own receipt is unchanged. Opens without JavaScript (peer-checked). Needs $field from checkout/index. --}}
@php $giftOpen = (bool) old('is_gift'); @endphp
<section class="bg-white rounded-2xl border border-gray-200 p-6 grid grid-cols-[auto_1fr] gap-x-3 items-start" data-gift>
    <input type="checkbox" id="is_gift" name="is_gift" value="1" @checked($giftOpen) class="peer mt-1.5 h-4 w-4 rounded text-primary-600 focus:ring-primary-500">
    <label for="is_gift" class="cursor-pointer">
        <span class="flex items-center gap-2 text-xl font-semibold text-gray-900"><x-icon name="gift" class="w-5 h-5 text-primary" />{{ __('This is a gift') }}</span>
        <span class="block text-sm text-gray-600">{{ __('We deliver it to the address above for the person you name. You still get the receipt and the order updates.') }}</span>
    </label>
    <div class="col-span-2 mt-4 space-y-4 hidden peer-checked:block" data-gift-fields>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="gift_receiver_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Receiver\'s name') }}</label>
                <input type="text" id="gift_receiver_name" name="gift_receiver_name" maxlength="120" autocomplete="off" class="{{ $field }}" value="{{ old('gift_receiver_name') }}" @error('gift_receiver_name') aria-invalid="true" @enderror>
                @error('gift_receiver_name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="gift_receiver_phone" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Receiver\'s phone') }}</label>
                <input type="tel" id="gift_receiver_phone" name="gift_receiver_phone" maxlength="20" inputmode="tel" autocomplete="off" dir="ltr" class="{{ $field }}" value="{{ old('gift_receiver_phone') }}" placeholder="7771234" @error('gift_receiver_phone') aria-invalid="true" @enderror>
                @error('gift_receiver_phone')
                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                @else
                    <p class="mt-1 text-xs text-gray-500">{{ __('The courier calls this number to deliver the gift.') }}</p>
                @enderror
            </div>
        </div>
        <div>
            <label for="gift_message" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Gift message') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
            <textarea id="gift_message" name="gift_message" rows="3" maxlength="200" class="{{ $field }}" placeholder="{{ __('e.g. Happy birthday! Love, Aisha') }}" @error('gift_message') aria-invalid="true" @enderror>{{ old('gift_message') }}</textarea>
            @error('gift_message')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @else
                <p class="mt-1 text-xs text-gray-500">{{ __('Up to 200 characters. Printed on the packing slip.') }}</p>
            @enderror
        </div>
        <label class="flex items-start gap-3 text-sm text-gray-700">
            <input type="hidden" name="gift_hide_prices" value="0">
            <input type="checkbox" name="gift_hide_prices" value="1" @checked(old('gift_hide_prices', '1') === '1') class="mt-0.5 h-4 w-4 rounded text-primary-600 focus:ring-primary-500">
            <span>{{ __('Hide prices on the packing slip') }}</span>
        </label>
    </div>
</section>
<script>
    (function () {
        var box = document.getElementById('is_gift');
        var fields = document.querySelector('[data-gift-fields]');
        if (!box || !fields) return;
        var sync = function () { fields.classList.toggle('hidden', !box.checked); };
        box.addEventListener('change', sync);
        sync();
    })();
</script>
