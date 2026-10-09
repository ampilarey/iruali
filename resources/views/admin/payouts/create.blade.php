@extends('layouts.app')

@php use App\Support\Money; $shop = $seller->business_name ?: $seller->name; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => 'Pay out '.$shop, 'back' => route('admin.payouts')])

    <form method="POST" action="{{ route('admin.payouts.store', $seller) }}" class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6" data-payout>
        @csrf
        <div class="rounded-lg bg-white p-5 shadow text-sm">
            <h2 class="font-semibold text-gray-900">Pay to</h2>
            @if($seller->bankAccount)
                <p class="mt-1">{{ $seller->bankAccount->account_name }} · {{ $seller->bankAccount->bankName() }} · <span class="font-mono" dir="ltr">{{ $seller->bankAccount->account_number }}</span>
                    <span class="ms-2 rounded-full px-2 py-0.5 text-xs font-medium {{ $seller->bankAccount->isVerified() ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $seller->bankAccount->isVerified() ? __('Verified') : __('Not verified') }}</span>
                </p>
            @else
                <p class="mt-1 text-amber-700">{{ $blocked }} {{ __('Ask the shop to add it under Seller Centre → Settings → Bank account.') }}</p>
            @endif
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Orders ready for payout</h2>
                <p class="text-sm text-gray-500">Untick any order you want to hold back (for example, a return in progress).</p>
            </div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr><th class="px-4 py-2"></th><th class="px-4 py-2 text-left">Order</th><th class="px-4 py-2 text-left">Delivered</th><th class="px-4 py-2 text-right">Items</th><th class="px-4 py-2 text-right">Commission</th><th class="px-4 py-2 text-right">Shop earns</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($parts as $part)
                        <tr>
                            <td class="px-4 py-2"><input type="checkbox" name="parts[]" value="{{ $part->id }}" data-amount="{{ $part->seller_earnings }}" checked class="rounded" aria-label="Include order {{ $part->order?->order_number }}"></td>
                            <td class="px-4 py-2"><a href="{{ route('admin.orders.show', $part->order) }}" class="font-medium text-primary-700 hover:underline">#{{ $part->order?->order_number }}</a>@if($part->returnRequests->where('status', 'requested')->isNotEmpty())<span class="ms-1 rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800">Return requested</span>@endif</td>
                            <td class="px-4 py-2 text-gray-600">{{ $part->delivered_at?->format('d M Y') }}</td>
                            <td class="px-4 py-2 text-right">{{ Money::format($part->subtotal) }}@if((float) $part->shop_discount > 0)<span class="block text-xs text-gray-500">less shop's discounts {{ Money::format($part->shop_discount) }}</span>@endif</td>
                            <td class="px-4 py-2 text-right text-gray-600">&minus;{{ Money::format($part->commission_amount) }} ({{ rtrim(rtrim(number_format((float) $part->commission_rate, 2), '0'), '.') }}%)</td>
                            <td class="px-4 py-2 text-right font-semibold">{{ Money::format($part->seller_earnings) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">Nothing is ready to pay.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @if($adjustments->isNotEmpty())
                <div class="border-t border-gray-100 px-5 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">Adjustments settled in this payout</h3>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach($adjustments as $adjustment)
                            <li class="flex justify-between gap-3"><span class="text-gray-700">{{ $adjustment->reason }} <span class="text-xs text-gray-500">({{ $adjustment->created_at->format('d M Y') }})</span></span><span class="font-semibold {{ $adjustment->amount < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $adjustment->amount < 0 ? '−' : '+' }}{{ Money::format(abs($adjustment->amount)) }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="flex justify-end border-t border-gray-100 px-5 py-3 text-base font-semibold" data-adjustments="{{ $adjustments->sum('amount') }}">Total: <span class="ms-2" data-payout-total>{{ Money::format($parts->sum('seller_earnings') + $adjustments->sum('amount')) }}</span></div>
        </div>

        @if($parts->isNotEmpty() && ! $blocked)
            <div class="rounded-lg bg-white p-5 shadow grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="reference" class="block text-sm font-medium text-gray-700">Bank transfer reference *</label>
                    <input id="reference" name="reference" required maxlength="100" value="{{ old('reference') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="note" class="block text-sm font-medium text-gray-700">Note</label>
                    <input id="note" name="note" maxlength="1000" value="{{ old('note') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <p class="sm:col-span-2 text-xs text-gray-500">Make the bank transfer first, then record it here. The selected orders are marked as paid to the shop.</p>
                <div class="sm:col-span-2 flex justify-end">
                    <button class="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700" onclick="return confirm('Record this payout? The selected orders will be marked as paid to the shop.')">Record payout</button>
                </div>
            </div>
        @endif
    </form>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.querySelector('[data-payout]'), total = form && form.querySelector('[data-payout-total]');
        if (!total) return;
        form.querySelectorAll('input[name="parts[]"]').forEach(function (c) {
            c.addEventListener('change', function () {
                var t = parseFloat(form.querySelector('[data-adjustments]').dataset.adjustments) || 0;
                form.querySelectorAll('input[name="parts[]"]:checked').forEach(function (x) { t += parseFloat(x.dataset.amount); });
                total.textContent = 'MVR ' + t.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            });
        });
    })();
</script>
@endpush
