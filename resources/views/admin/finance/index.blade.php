@extends('layouts.admin')

@section('title', 'Finance')

@php
    $money = fn ($v) => \App\Support\Money::format((float) $v);
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm';
    $prev = $pnl['from']->copy()->subMonth()->format('Y-m');
    $next = $pnl['from']->copy()->addMonth()->format('Y-m');
@endphp

@section('content')
<div class="max-w-5xl mx-auto py-6 px-4 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-xl font-bold lg:text-2xl">Finance · {{ $pnl['from']->format('F Y') }}</h1>
            <p class="text-sm text-gray-500">iruali's own income and costs. Shops' sales are not iruali's income; their GST is under Tax.</p>
        </div>
        <div class="flex gap-2 text-sm">
            <a href="{{ route('admin.finance', ['month' => $prev]) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 hover:bg-gray-50">← {{ \Illuminate\Support\Carbon::parse($prev.'-01')->format('M') }}</a>
            <a href="{{ route('admin.finance', ['month' => $next]) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 hover:bg-gray-50">{{ \Illuminate\Support\Carbon::parse($next.'-01')->format('M') }} →</a>
        </div>
    </div>
    @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3" data-pnl>
        <div class="rounded-lg bg-white p-4 shadow"><p class="text-xs text-gray-500">Income</p><p class="mt-1 text-lg font-bold">{{ $money($pnl['income_total']) }}</p></div>
        <div class="rounded-lg bg-white p-4 shadow"><p class="text-xs text-gray-500">Costs</p><p class="mt-1 text-lg font-bold">{{ $money($pnl['cost_total']) }}</p></div>
        <div class="rounded-lg bg-white p-4 shadow"><p class="text-xs text-gray-500">{{ $pnl['profit'] >= 0 ? 'Profit' : 'Loss' }}</p><p @class(['mt-1 text-lg font-bold', 'text-green-700' => $pnl['profit'] >= 0, 'text-red-700' => $pnl['profit'] < 0])>{{ $money($pnl['profit']) }}</p></div>
    </div>

    <x-admin.fold title="Profit and loss" :open="true" flush>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                <tr><td class="p-3">Commission (net of refunds, without GST)</td><td class="p-3 text-end">{{ $money($pnl['income']['commission']) }}</td></tr>
                <tr><td class="p-3">Delivery and small-order fees (net, without GST)</td><td class="p-3 text-end">{{ $money($pnl['income']['delivery']) }}</td></tr>
                <tr class="font-semibold"><td class="p-3">Income</td><td class="p-3 text-end">{{ $money($pnl['income_total']) }}</td></tr>
                @if($pnl['card_fees'] > 0)<tr><td class="p-3">Card fees (estimate: {{ $cardFeePercent }}% of {{ $money($pnl['card_total']) }} paid by card)</td><td class="p-3 text-end">−{{ $money($pnl['card_fees']) }}</td></tr>@endif
                @foreach($pnl['costs'] as $category => $amount)
                    <tr><td class="p-3">{{ \App\Models\Expense::CATEGORIES[$category] ?? $category }} (without GST)</td><td class="p-3 text-end">−{{ $money($amount) }}</td></tr>
                @endforeach
                <tr class="font-semibold"><td class="p-3">{{ $pnl['profit'] >= 0 ? 'Profit' : 'Loss' }}</td><td class="p-3 text-end">{{ $money($pnl['profit']) }}</td></tr>
            </tbody>
        </table>
        <p class="flex flex-wrap gap-3 px-3 pb-3 text-sm">
            <a href="{{ route('admin.finance.export', ['month' => $pnl['month']]) }}" class="text-primary-700 underline">Download CSV</a>
            <a href="{{ route('admin.finance.xero.invoices', ['month' => $pnl['month']]) }}" class="text-primary-700 underline">Xero: commission invoices</a>
            <a href="{{ route('admin.finance.xero.bills', ['month' => $pnl['month']]) }}" class="text-primary-700 underline">Xero: expenses as bills</a>
        </p>
    </x-admin.fold>

    <form method="POST" action="{{ route('admin.finance.expenses.store') }}" enctype="multipart/form-data">
        @csrf
        <x-admin.fold title="Add an expense" body="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3" :open="$errors->any()" id="add-expense">
            <label class="block text-xs font-medium text-gray-700">Date<input type="date" name="spent_on" required value="{{ old('spent_on', now()->toDateString()) }}" class="{{ $field }}"></label>
            <label class="block text-xs font-medium text-gray-700">Category<select name="category" class="{{ $field }}">@foreach(\App\Models\Expense::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach</select></label>
            <label class="block text-xs font-medium text-gray-700">Supplier<input name="supplier" maxlength="120" value="{{ old('supplier') }}" class="{{ $field }}"></label>
            <label class="block text-xs font-medium text-gray-700 sm:col-span-3">Description<input name="description" required maxlength="200" value="{{ old('description') }}" class="{{ $field }}">@error('description')<span class="text-red-700">{{ $message }}</span>@enderror</label>
            <label class="block text-xs font-medium text-gray-700">Amount paid, GST included (MVR)<input type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount') }}" class="{{ $field }}">@error('amount')<span class="text-red-700">{{ $message }}</span>@enderror</label>
            <label class="block text-xs font-medium text-gray-700">GST in it (MVR)<input type="number" step="0.01" min="0" name="gst_amount" value="{{ old('gst_amount') }}" class="{{ $field }}">@error('gst_amount')<span class="text-red-700">{{ $message }}</span>@enderror</label>
            <label class="block text-xs font-medium text-gray-700">Receipt (PDF or photo)<input type="file" name="receipt" accept=".pdf,image/*" class="mt-1 block w-full text-sm"></label>
            <label class="flex items-center gap-2 text-sm sm:col-span-3"><input type="checkbox" name="recurring" value="1"> Every month (copied on the 1st)</label>
            <div class="sm:col-span-3"><button class="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">Add</button></div>
        </x-admin.fold>
    </form>

    <x-admin.fold title="Expenses this month" flush>
        <x-slot:badge><span class="text-xs text-gray-500">{{ $pnl['expenses']->count() }}</span></x-slot:badge>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    @forelse($pnl['expenses'] as $expense)
                        <tr data-expense>
                            <td class="p-3 whitespace-nowrap">{{ $expense->spent_on->format('j M') }}</td>
                            <td class="p-3">{{ $expense->description }}<br><span class="text-xs text-gray-500">{{ $expense->categoryLabel() }}{{ $expense->supplier ? ' · '.$expense->supplier : '' }}{{ $expense->recurring ? ' · monthly' : '' }}</span></td>
                            <td class="p-3 text-end whitespace-nowrap">{{ $money($expense->amount) }}</td>
                            <td class="p-3 text-end whitespace-nowrap">
                                @if($expense->receipt_path)<a href="{{ route('admin.finance.expenses.receipt', $expense) }}" class="text-primary-700 underline" target="_blank">Receipt</a>@endif
                                <form method="POST" action="{{ route('admin.finance.expenses.destroy', $expense) }}" class="inline" onsubmit="return confirm('Remove this expense?')">@csrf @method('DELETE')<button class="ms-2 text-red-700 underline">Remove</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="p-3 text-gray-500">No expenses entered this month.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.fold>

    @if(auth()->user()->hasRole('admin'))
        <form method="POST" action="{{ route('admin.finance.settings') }}">
            @csrf @method('PUT')
            <x-admin.fold title="Card fee and Xero codes" body="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                <label class="block text-xs font-medium text-gray-700">BML card fee (%)<input type="number" step="0.01" min="0" max="10" name="card_fee_percent" value="{{ $cardFeePercent }}" class="{{ $field }}"></label>
                <label class="block text-xs font-medium text-gray-700">Xero sales account<input name="xero_sales_account" value="{{ \App\Models\Setting::get('xero_sales_account', '200') }}" class="{{ $field }}"></label>
                <label class="block text-xs font-medium text-gray-700">Xero expense account<input name="xero_expense_account" value="{{ \App\Models\Setting::get('xero_expense_account', '400') }}" class="{{ $field }}"></label>
                <label class="block text-xs font-medium text-gray-700">Xero tax rate, sales<input name="xero_tax_sales" value="{{ \App\Models\Setting::get('xero_tax_sales', 'GST on Income') }}" class="{{ $field }}"></label>
                <label class="block text-xs font-medium text-gray-700">Xero tax rate, purchases<input name="xero_tax_purchases" value="{{ \App\Models\Setting::get('xero_tax_purchases', 'GST on Expenses') }}" class="{{ $field }}"></label>
                <p class="text-xs text-gray-500 sm:col-span-3">The card fee estimate is left out once you enter card fees as an expense. Import the Xero files with "Amounts are tax inclusive".</p>
                <div class="sm:col-span-3"><button class="rounded-lg border border-gray-300 px-4 py-2 font-semibold hover:bg-gray-50">Save</button></div>
            </x-admin.fold>
        </form>
    @endif
</div>
@endsection
