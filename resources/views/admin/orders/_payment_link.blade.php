{{-- Admin order page → Payment: send the customer a link to pay this unpaid order (App\Support\PayLink) --}}
@if(\App\Support\PayLink::payable($order) && \App\Support\StaffAccess::can('admin.orders.payment-link'))
    @php
        $linkPhone = \App\Services\Sms\PhoneNumber::normalize($order->shipping_phone ?: $order->user?->phone);
        $linkEmail = $order->customerEmail();
        $smsOn = app(\App\Services\Sms\SmsManager::class)->isLive();
    @endphp
    <div class="mt-3 rounded-md border border-gray-200 p-3 text-sm space-y-2" data-payment-link>
        <p class="font-semibold text-gray-900">Payment link</p>
        <p class="text-xs text-gray-500">A page where the customer pays by card or bank transfer without signing in. Works for {{ \App\Support\PayLink::HOURS }} hours, and the order is not cancelled for being unpaid meanwhile.@if($order->payment_link_sent_at) Last sent {{ $order->payment_link_sent_at->diffForHumans() }}.@endif</p>
        <form method="POST" action="{{ route('admin.orders.payment-link', $order) }}" class="space-y-2">
            @csrf
            <label class="flex items-center gap-2 {{ $linkPhone ? '' : 'text-gray-400' }}"><input type="checkbox" name="by[]" value="sms" @checked($linkPhone && $smsOn) @disabled(! $linkPhone) class="rounded text-primary-600 focus:ring-primary-500"> SMS {{ $linkPhone ? 'to '.$linkPhone : '(no mobile number)' }}@if($linkPhone && ! $smsOn)<span class="text-xs text-amber-700">(SMS not connected: logged only)</span>@endif</label>
            <label class="flex items-center gap-2 {{ $linkEmail ? '' : 'text-gray-400' }}"><input type="checkbox" name="by[]" value="mail" @checked((bool) $linkEmail) @disabled(! $linkEmail) class="rounded text-primary-600 focus:ring-primary-500"> Email {{ $linkEmail ? 'to '.$linkEmail : '(no email)' }}</label>
            @error('by')<p class="text-xs text-red-700">Tick SMS or email.</p>@enderror
            <button class="rounded-lg border border-primary-300 bg-white px-3 py-1.5 font-semibold text-primary-700 hover:bg-primary-50">Send payment link</button>
        </form>
        <details>
            <summary class="cursor-pointer text-xs font-medium text-primary-700">Copy the link instead (e.g. for WhatsApp)</summary>
            <input readonly value="{{ session('payment_link') ?: \App\Support\PayLink::url($order) }}" onclick="this.select()" dir="ltr" class="mt-1 w-full rounded border border-gray-300 px-2 py-1 font-mono text-xs" aria-label="Payment link">
        </details>
    </div>
@endif
