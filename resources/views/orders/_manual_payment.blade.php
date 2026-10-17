{{-- Order page (account or guest): paying by bank transfer or cash on delivery (ManualPaymentService). Bank transfer:
     the account, the amount and the reference, and the slip upload; cash on delivery: the amount to have ready.
     Needs $order and $slipAction (where the slip form posts). --}}
@php
    $manual = \App\Services\ManualPaymentService::class;
    $amountDue = \App\Support\Money::format($order->cardAmount());
@endphp
@if($order->payment_method === $manual::TRANSFER && $manual::awaiting($order))
    @php $bank = $manual::transferDetails(); @endphp
    <div class="mt-6 rounded-xl border border-primary-200 bg-primary-50 p-4 space-y-3 text-sm" data-transfer-box>
        <h3 class="font-semibold text-gray-900">{{ __('Pay by bank transfer') }}</h3>
        <p class="text-gray-700">{{ __('Transfer :amount to this account, with your order number as the reference:', ['amount' => $amountDue]) }}</p>
        <dl class="rounded-lg bg-white p-3 space-y-1">
            <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Bank') }}</dt><dd class="font-medium text-dark">{{ $bank['bank'] }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Account name') }}</dt><dd class="font-medium text-dark">{{ $bank['name'] }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Account number') }}</dt><dd class="font-semibold text-dark select-all" dir="ltr">{{ $bank['number'] }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Amount') }}</dt><dd class="font-semibold text-dark" dir="ltr">{{ $amountDue }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Reference') }}</dt><dd class="font-semibold text-dark select-all" dir="ltr">{{ $order->order_number }}</dd></div>
        </dl>
        @if($bank['note'] !== '')
            <p class="text-gray-700" dir="auto">{{ $bank['note'] }}</p>
        @endif
        @if($order->payment_slip_uploaded_at)
            <p class="rounded-lg bg-green-50 px-3 py-2 text-green-800" data-slip-received>{{ __('We have your transfer slip (sent :date). We are checking the payment and will email you once it is confirmed.', ['date' => $order->payment_slip_uploaded_at->translatedFormat('j M, H:i')]) }}</p>
        @else
            <p class="text-gray-700">{{ __('Then upload a photo or PDF of the transfer slip here within :hours hours, or the order is cancelled.', ['hours' => $manual::payWithinHours()]) }}</p>
        @endif
        <form method="POST" action="{{ $slipAction }}" enctype="multipart/form-data" class="space-y-2">
            @csrf
            <label for="slip" class="block font-medium text-gray-900">{{ $order->payment_slip_uploaded_at ? __('Upload a different slip') : __('Transfer slip') }}</label>
            <input id="slip" name="slip" type="file" required accept="image/jpeg,image/png,image/webp,application/pdf" class="block w-full text-sm">
            @error('slip')<p class="text-danger text-xs">{{ $message }}</p>@enderror
            <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-hover">{{ __('Send transfer slip') }}</button>
        </form>
        <p class="text-xs text-gray-500">{{ __('The shops send your order once the payment is confirmed.') }}</p>
    </div>
@elseif($order->payment_method === $manual::COD && $manual::awaiting($order))
    <div class="mt-6 rounded-xl border border-primary-200 bg-primary-50 p-4 text-sm" data-cod-box>
        <h3 class="font-semibold text-gray-900">{{ __('Cash on delivery') }}</h3>
        <p class="mt-1 text-gray-700">{{ __('Please have :amount ready in cash when your order arrives (or when you collect it).', ['amount' => $amountDue]) }}</p>
    </div>
@elseif($order->payment_method === $manual::TRANSFER && $order->payment_status === 'paid')
    <p class="mt-6 rounded-xl bg-green-50 text-green-800 text-sm font-medium p-4 flex items-center gap-2"><x-icon name="check" class="w-4 h-4" />{{ __('Paid by bank transfer, confirmed :date.', ['date' => $order->paid_at?->format('j M Y, H:i')]) }}</p>
@elseif($order->payment_method === $manual::COD && $order->payment_status === 'paid')
    <p class="mt-6 rounded-xl bg-green-50 text-green-800 text-sm font-medium p-4 flex items-center gap-2"><x-icon name="check" class="w-4 h-4" />{{ __('Paid in cash on :date.', ['date' => $order->paid_at?->format('j M Y, H:i')]) }}</p>
@endif
