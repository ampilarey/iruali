@extends('layouts.app')

@php use App\Support\Money; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => __('Payout batches'), 'back' => route('admin.payouts')])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600">{{ __('A batch pays several shops in one BML bulk transfer: draft it, download the bank file, upload it in Internet Banking, then mark the batch paid with the bank\'s reference.') }}</p>
            <a href="{{ route('admin.payout-batches.create') }}" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{{ __('New batch') }}</a>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr><th class="px-4 py-3 text-start">{{ __('Reference') }}</th><th class="px-4 py-3 text-start">{{ __('Status') }}</th><th class="px-4 py-3 text-end">{{ __('Shops') }}</th><th class="px-4 py-3 text-end">{{ __('Total') }}</th><th class="px-4 py-3 text-start">{{ __('Created') }}</th><th class="px-4 py-3 text-start">{{ __('Paid') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($batches as $batch)
                            <tr>
                                <td class="px-4 py-3"><a href="{{ route('admin.payout-batches.show', $batch) }}" class="font-medium text-primary-700 hover:underline">{{ $batch->reference }}</a></td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $batch->status_badge }}">{{ $batch->statusLabel() }}</span></td>
                                <td class="px-4 py-3 text-end">{{ $batch->count }}</td>
                                <td class="px-4 py-3 text-end font-semibold">{{ Money::format($batch->total) }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $batch->created_at->format('d M Y') }}@if($batch->creator) · {{ $batch->creator->name }}@endif</td>
                                <td class="px-4 py-3 text-gray-600">@if($batch->paid_at){{ $batch->paid_at->format('d M Y') }} · {{ $batch->bank_reference }}@else—@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">{{ __('No batches yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($batches->hasPages())<div class="px-4 py-3">{{ $batches->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
