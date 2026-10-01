@extends('layouts.app')

@php use App\Support\Money; use App\Support\BankFileFormat; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => __('Batch :reference', ['reference' => $batch->reference]), 'back' => route('admin.payout-batches.index')])

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="rounded-lg bg-white p-5 shadow grid gap-3 sm:grid-cols-4 text-sm">
            <div><p class="text-gray-500">{{ __('Status') }}</p><p><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $batch->status_badge }}">{{ $batch->statusLabel() }}</span></p></div>
            <div><p class="text-gray-500">{{ __('Total') }}</p><p class="text-2xl font-bold text-gray-900">{{ Money::format($batch->total) }}</p><p class="text-xs text-gray-500">{{ trans_choice(':count shop|:count shops', $batch->count, ['count' => $batch->count]) }}</p></div>
            <div><p class="text-gray-500">{{ __('Created') }}</p><p>{{ $batch->created_at->format('d M Y, H:i') }}@if($batch->creator)<br><span class="text-xs text-gray-500">{{ $batch->creator->name }}</span>@endif</p></div>
            <div>
                <p class="text-gray-500">{{ __('Bank file') }}</p>
                @if($batch->exported_at)<p>{{ __('Downloaded') }} {{ $batch->exported_at->format('d M Y, H:i') }}</p>@else<p class="text-gray-500">{{ __('Not downloaded yet') }}</p>@endif
            </div>
            @if($batch->paid_at)
                <div class="sm:col-span-2"><p class="text-gray-500">{{ __('Paid') }}</p><p>{{ $batch->paid_at->format('d M Y') }} · {{ __('Bank reference') }} <span class="font-mono">{{ $batch->bank_reference }}</span></p></div>
            @endif
            @if($batch->notes)<div class="sm:col-span-2"><p class="text-gray-500">{{ __('Notes') }}</p><p>{{ $batch->notes }}</p></div>@endif
        </div>

        @if($batch->isOpen())
            <div class="rounded-lg bg-white p-5 shadow space-y-4">
                <ol class="list-decimal ps-5 text-sm text-gray-700 space-y-1">
                    <li>{{ __('Download the bank file and upload it in BML Internet Banking → Bulk transfer. Check the total matches before you confirm.') }}</li>
                    <li>{{ __('Once the bank has processed it, enter the bank\'s reference and the date below. Every shop in the batch is then marked paid and emailed.') }}</li>
                </ol>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('admin.payout-batches.file', $batch) }}" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{{ __('Download bank file') }}</a>
                    <span class="text-xs text-gray-500">{{ BankFileFormat::filename($batch) }} · {{ __('BML bulk transfer CSV') }}</span>
                    @if($batch->isDraft())
                        <form method="POST" action="{{ route('admin.payout-batches.cancel', $batch) }}" class="ms-auto" onsubmit="return confirm('{{ __('Cancel this batch? The earnings become available again.') }}')">
                            @csrf
                            <button class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('Cancel batch') }}</button>
                        </form>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.payout-batches.paid', $batch) }}" class="grid gap-4 border-t border-gray-100 pt-4 sm:grid-cols-3" onsubmit="return confirm('{{ __('Mark every payout in this batch as paid? The shops will be emailed.') }}')">
                    @csrf
                    <div>
                        <label for="bank_reference" class="block text-sm font-medium text-gray-700">{{ __('Bank reference') }} *</label>
                        <input id="bank_reference" name="bank_reference" required maxlength="100" value="{{ old('bank_reference') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="paid_on" class="block text-sm font-medium text-gray-700">{{ __('Transfer date') }} *</label>
                        <input id="paid_on" name="paid_on" type="date" required max="{{ now()->toDateString() }}" value="{{ old('paid_on', now()->toDateString()) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div class="flex items-end">
                        <button class="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">{{ __('Mark paid') }}</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="border-b border-gray-100 px-5 py-4"><h2 class="text-lg font-semibold text-gray-900">{{ __('Lines') }}</h2></div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr><th class="px-4 py-2 text-start">{{ __('Shop') }}</th><th class="px-4 py-2 text-start">{{ __('Account name') }}</th><th class="px-4 py-2 text-start">{{ __('Account number') }}</th><th class="px-4 py-2 text-end">{{ __('Amount') }}</th><th class="px-4 py-2 text-start">{{ __('Remark') }}</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($lines as $payout)
                        <tr>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $payout->seller?->shopName() }}</td>
                            <td class="px-4 py-2">{{ $payout->seller?->bankAccount?->account_name }}</td>
                            <td class="px-4 py-2 font-mono" dir="ltr">{{ $payout->seller?->bankAccount?->account_number }}<span class="block font-sans text-xs text-gray-500">{{ $payout->seller?->bankAccount?->bankName() }}</span></td>
                            <td class="px-4 py-2 text-end font-semibold">{{ Money::format($payout->amount) }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ BankFileFormat::remark($batch, $payout->id) }}</td>
                            <td class="px-4 py-2 text-end"><a href="{{ route('admin.payouts.show', $payout) }}" class="text-xs font-medium text-primary-700 hover:underline">{{ __('Statement') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">{{ __('This batch has no lines.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
