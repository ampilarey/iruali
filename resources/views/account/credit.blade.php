@extends('layouts.app')

@section('title', __('Pay later'))

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8 space-y-5" data-credit-statement>
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h1 class="font-display text-2xl font-bold text-dark">{{ __('Pay later') }}</h1>
            <p class="text-sm text-gray-600">{{ __('Each order is invoiced to your business and due :days days after you order.', ['days' => $account->terms_days]) }}</p>
        </div>
        <button type="button" onclick="window.print()" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50 print:hidden">{{ __('Print statement') }}</button>
    </div>

    @unless($account->isActive())
        <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ __('Pay later is paused on your account. Please contact us.') }}</p>
    @endunless

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-gray-200 bg-white p-4"><p class="text-xs text-gray-500">{{ __('Credit limit') }}</p><p class="mt-1 text-lg font-bold" dir="ltr">{{ \App\Support\Money::format((float) $account->credit_limit) }}</p></div>
        <div class="rounded-lg border border-gray-200 bg-white p-4"><p class="text-xs text-gray-500">{{ __('Owed') }}</p><p class="mt-1 text-lg font-bold" dir="ltr">{{ \App\Support\Money::format($outstanding) }}</p></div>
        <div class="rounded-lg border border-gray-200 bg-white p-4"><p class="text-xs text-gray-500">{{ __('Left to use') }}</p><p class="mt-1 text-lg font-bold" dir="ltr">{{ \App\Support\Money::format($available) }}</p></div>
    </div>

    <section class="rounded-lg border border-gray-200 bg-white p-4">
        <h2 class="font-semibold text-dark">{{ __('Open invoices') }}</h2>
        <table class="mt-2 w-full text-sm">
            <thead><tr class="text-xs text-gray-500"><th class="py-1 text-start">{{ __('Order') }}</th><th class="py-1 text-start">{{ __('Due') }}</th><th class="py-1 text-end">{{ __('Amount') }}</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($open as $invoice)
                    <tr @class(['text-red-700' => $invoice->credit_due_on?->isPast()])>
                        <td class="py-1"><a href="{{ route('orders.show', $invoice) }}" class="font-mono hover:underline" dir="ltr">{{ $invoice->order_number }}</a></td>
                        <td class="py-1">{{ $invoice->credit_due_on?->translatedFormat('j M Y') }}@if($invoice->credit_due_on?->isPast()) · {{ __('Overdue') }}@endif</td>
                        <td class="py-1 text-end" dir="ltr">{{ \App\Support\Money::format((float) $invoice->total_amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-2 text-gray-500">{{ __('Nothing to pay.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        @php $bank = \App\Services\ManualPaymentService::transferDetails(); @endphp
        @if($open->isNotEmpty() && $bank['number'] !== '')
            <p class="mt-3 text-xs text-gray-600">{{ __('Pay by bank transfer to :bank, :name, account :number, with the order number as the reference.', ['bank' => $bank['bank'], 'name' => $bank['name'], 'number' => $bank['number']]) }}</p>
        @endif
    </section>

    @if($paid->isNotEmpty())
        <section class="rounded-lg border border-gray-200 bg-white p-4">
            <h2 class="font-semibold text-dark">{{ __('Paid recently') }}</h2>
            <ul class="mt-2 divide-y divide-gray-100 text-sm">
                @foreach($paid as $invoice)
                    <li class="flex justify-between py-1"><span class="font-mono" dir="ltr">{{ $invoice->order_number }}</span><span>{{ $invoice->paid_at?->translatedFormat('j M Y') }} · <span dir="ltr">{{ \App\Support\Money::format((float) $invoice->total_amount) }}</span></span></li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
@endsection
