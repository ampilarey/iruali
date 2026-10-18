@extends('layouts.admin')

@section('title', 'Exports')

@section('content')
<div class="max-w-3xl mx-auto py-6 px-4 space-y-4">
    <div>
        <h1 class="text-xl font-bold lg:text-2xl">Exports</h1>
        <p class="text-sm text-gray-500">CSV files for spreadsheets and your accountant. Pick the dates, then the file. Customers are under <a href="{{ route('admin.customer-report') }}" class="underline">Customer report</a>, iruali's profit and loss and Xero files under <a href="{{ route('admin.finance') }}" class="underline">Finance</a>.</p>
    </div>
    <form method="GET" class="rounded-lg bg-white p-4 shadow space-y-4 text-sm" data-exports>
        <div class="grid grid-cols-2 gap-3 max-w-md">
            <label class="block text-xs font-medium text-gray-700">From<input type="date" name="from" value="{{ request('from', now()->startOfMonth()->toDateString()) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label>
            <label class="block text-xs font-medium text-gray-700">To<input type="date" name="to" value="{{ request('to', now()->toDateString()) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label>
        </div>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach($kinds as $kind => $label)
                <button type="submit" formaction="{{ route('admin.exports.download', $kind) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-start font-medium hover:bg-gray-50">{{ $label }}</button>
            @endforeach
        </div>
    </form>
</div>
@endsection
