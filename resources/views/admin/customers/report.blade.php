@extends('layouts.admin')

@section('title', 'Customer report')

@section('content')
<div class="max-w-6xl mx-auto py-6 px-4 space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h1 class="text-xl font-bold lg:text-2xl">Customer report</h1>
            <p class="text-sm text-gray-500">From paid orders that were not cancelled.</p>
        </div>
        <a href="{{ route('admin.customer-report.export') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-gray-50">Download CSV</a>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach([
            ['Customers who bought', number_format($totals['buyers']), null],
            ['Came back', $totals['repeat_rate'].'%', number_format($totals['repeat']).' ordered more than once'],
            ['Lifetime value', 'MVR '.number_format($totals['lifetime_value'], 2), 'spent per customer, on average'],
            ['Orders per customer', $totals['orders_per_customer'], null],
        ] as [$label, $value, $hint])
            <div class="rounded-lg bg-white p-4 shadow">
                <p class="text-xs text-gray-500">{{ $label }}</p>
                <p class="mt-1 text-lg font-bold text-gray-900">{{ $value }}</p>
                @if($hint)<p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>@endif
            </div>
        @endforeach
    </div>

    <x-admin.fold title="Top customers" :open="true" flush>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500"><th class="p-3 text-start">Customer</th><th class="p-3 text-end">Orders</th><th class="p-3 text-end">Spent</th><th class="p-3 text-end">Last order</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($top as $row)
                        <tr>
                            <td class="p-3"><a href="{{ route('admin.customers.show', $row['id']) }}" class="text-primary-700 hover:underline">{{ $row['name'] }}</a><br><span class="text-xs text-gray-500 break-all">{{ $row['email'] }}</span></td>
                            <td class="p-3 text-end">{{ $row['orders'] }}</td>
                            <td class="p-3 text-end whitespace-nowrap">MVR {{ number_format($row['spent'], 2) }}</td>
                            <td class="p-3 text-end whitespace-nowrap">{{ $row['last']->format('j M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-3 text-gray-500">No paid orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.fold>

    <x-admin.fold title="New and returning customers by month" flush>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500"><th class="p-3 text-start">Month</th><th class="p-3 text-end">New</th><th class="p-3 text-end">Returning</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($months as $row)
                        <tr><td class="p-3 whitespace-nowrap">{{ $row['month'] }}</td><td class="p-3 text-end">{{ $row['new'] }}</td><td class="p-3 text-end">{{ $row['returning'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.fold>

    <x-admin.fold title="Cohorts" flush>
        <p class="px-4 pt-3 text-xs text-gray-500 sm:px-6">Customers grouped by the month of their first order, and the share of them who ordered again 1 to {{ \App\Http\Controllers\Admin\CustomerReportController::COHORT_MONTHS }} months later.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500"><th class="p-3 text-start">First order</th><th class="p-3 text-end">Customers</th>@for($m = 1; $m <= \App\Http\Controllers\Admin\CustomerReportController::COHORT_MONTHS; $m++)<th class="p-3 text-end">+{{ $m }}</th>@endfor</tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($cohorts as $row)
                        <tr>
                            <td class="p-3 whitespace-nowrap">{{ $row['month'] }}</td>
                            <td class="p-3 text-end">{{ $row['size'] }}</td>
                            @foreach($row['after'] as $share)<td class="p-3 text-end">{{ $share === null ? '–' : $share.'%' }}</td>@endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.fold>
</div>
@endsection
