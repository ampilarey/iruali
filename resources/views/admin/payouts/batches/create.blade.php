@extends('layouts.app')

@php use App\Support\Money; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => __('New payout batch'), 'back' => route('admin.payout-batches.index')])

    <form method="POST" action="{{ route('admin.payout-batches.store') }}" class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6" data-batch>
        @csrf
        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('Shops with earnings ready to pay') }}</h2>
                    <p class="text-sm text-gray-500">{{ __('Each ticked shop gets one payout for everything it is owed, including open return deductions. Shops without a bank account cannot be included.') }}</p>
                </div>
                <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50" data-select-all>{{ __('Select all with bank details') }}</button>
            </div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr><th class="px-4 py-2"></th><th class="px-4 py-2 text-start">{{ __('Shop') }}</th><th class="px-4 py-2 text-start">{{ __('Bank account') }}</th><th class="px-4 py-2 text-end">{{ __('Ready') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($candidates as $row)
                        @php $seller = $row['seller']; @endphp
                        <tr class="{{ $row['blocked'] ? 'bg-amber-50/50' : '' }}">
                            <td class="px-4 py-2">
                                @if($row['blocked'])
                                    <input type="checkbox" disabled class="rounded opacity-40" aria-label="{{ $row['blocked'] }}">
                                @else
                                    <input type="checkbox" name="sellers[]" value="{{ $seller->id }}" data-amount="{{ $row['available'] }}" class="rounded" aria-label="{{ __('Include :shop', ['shop' => $seller->shopName()]) }}">
                                @endif
                            </td>
                            <td class="px-4 py-2"><p class="font-medium text-gray-900">{{ $seller->shopName() }}</p><p class="text-xs text-gray-500">{{ $seller->email }}</p></td>
                            <td class="px-4 py-2 text-xs text-gray-600">
                                @if($seller->bankAccount)
                                    {{ $seller->bankAccount->account_name }} · {{ $seller->bankAccount->bankName() }} · <span class="font-mono" dir="ltr">{{ $seller->bankAccount->account_number }}</span>
                                    @unless($seller->bankAccount->isVerified())<span class="ms-1 rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-800">{{ __('Not verified') }}</span>@endunless
                                @else
                                    <span class="text-amber-700">{{ $row['blocked'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-end font-semibold text-green-700">{{ Money::format($row['available']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-gray-500">{{ __('Nothing is ready to pay.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <div class="flex justify-end border-t border-gray-100 px-5 py-3 text-base font-semibold">{{ __('Total') }}: <span class="ms-2" data-batch-total>{{ Money::format(0) }}</span></div>
        </div>

        @if($candidates->isNotEmpty())
            <div class="rounded-lg bg-white p-5 shadow space-y-4">
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">{{ __('Notes') }}</label>
                    <input id="notes" name="notes" maxlength="1000" value="{{ old('notes') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <p class="text-xs text-gray-500">{{ __('Drafting a batch reserves the earnings: they leave the shops\' "ready" balance and wait for the transfer. A draft can be cancelled.') }}</p>
                <div class="flex justify-end">
                    <button class="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">{{ __('Create batch') }}</button>
                </div>
            </div>
        @endif
    </form>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.querySelector('[data-batch]'); if (!form) return;
        var total = form.querySelector('[data-batch-total]'), boxes = form.querySelectorAll('input[name="sellers[]"]');
        function sum() {
            var t = 0; form.querySelectorAll('input[name="sellers[]"]:checked').forEach(function (x) { t += parseFloat(x.dataset.amount); });
            total.textContent = 'MVR ' + t.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        boxes.forEach(function (c) { c.addEventListener('change', sum); });
        var all = form.querySelector('[data-select-all]');
        if (all) all.addEventListener('click', function () { boxes.forEach(function (c) { c.checked = true; }); sum(); });
    })();
</script>
@endpush
