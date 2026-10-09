@extends('layouts.app')

@php
    use App\Support\Money;
    $kinds = ['return' => __('Return'), 'dispute' => __('Dispute'), 'cancellation' => __('Cancelled after payment')];
@endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => __('Monthly GST report'), 'back' => route('admin.tax')])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <form method="GET" action="{{ route('admin.tax.report') }}" class="flex flex-wrap items-end gap-2">
                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700">{{ __('Month') }}</label>
                    <input id="month" name="month" type="month" value="{{ $report['month'] }}" class="mt-1 rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Show') }}</button>
            </form>
            <a href="{{ route('admin.tax.report', ['month' => $report['month'], 'export' => 'csv']) }}" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{{ __('Download CSV') }}</a>
        </div>

        <p class="text-sm text-gray-600">{{ __(':from to :to. Paid orders only: a sale counts in the month it was paid, a refund in the month a return or dispute was approved or the paid order was cancelled. GST comes from what was recorded on each order when it was placed. Gift-card orders are left out.', ['from' => $report['from']->format('j M Y'), 'to' => $report['to']->format('j M Y')]) }}</p>

        <div class="overflow-hidden rounded-lg bg-white shadow" data-report-shops>
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('GST-registered shops') }}</h2>
                <p class="text-xs text-gray-500">{{ __('Shops that were GST-registered when their orders were placed. Amounts include GST.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-start">{{ __('Shop') }}</th>
                            <th class="px-4 py-2 text-start">{{ __('TIN') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('Sales') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('GST collected') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('Refunds') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('GST on refunds') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('Net sales') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('Net GST') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($report['shops'] as $shop)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $shop['name'] }}<span class="block text-xs font-normal text-gray-500">{{ trans_choice(':count invoice|:count invoices', $shop['invoices'], ['count' => $shop['invoices']]) }}</span></td>
                                <td class="px-4 py-2 font-mono text-xs" dir="ltr">{{ $shop['tin'] }}</td>
                                <td class="px-4 py-2 text-end">{{ Money::format($shop['sales']) }}</td>
                                <td class="px-4 py-2 text-end">{{ Money::format($shop['gst']) }}</td>
                                <td class="px-4 py-2 text-end text-red-700">{{ $shop['refunds'] > 0 ? '−'.Money::format($shop['refunds']) : Money::format(0) }}</td>
                                <td class="px-4 py-2 text-end text-red-700">{{ $shop['refund_gst'] > 0 ? '−'.Money::format($shop['refund_gst']) : Money::format(0) }}</td>
                                <td class="px-4 py-2 text-end font-semibold">{{ Money::format($shop['net_sales']) }}</td>
                                <td class="px-4 py-2 text-end font-semibold">{{ Money::format($shop['net_gst']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">{{ __('No sales by GST-registered shops in this month.') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if($report['shops']->isNotEmpty())
                        <tfoot class="bg-gray-50 font-semibold text-gray-900">
                            <tr>
                                <td class="px-4 py-2" colspan="2">{{ __('Total') }}</td>
                                <td class="px-4 py-2 text-end">{{ Money::format($report['shop_totals']['sales']) }}</td>
                                <td class="px-4 py-2 text-end">{{ Money::format($report['shop_totals']['gst']) }}</td>
                                <td class="px-4 py-2 text-end">{{ Money::format($report['shop_totals']['refunds']) }}</td>
                                <td class="px-4 py-2 text-end">{{ Money::format($report['shop_totals']['refund_gst']) }}</td>
                                <td class="px-4 py-2 text-end">{{ Money::format($report['shop_totals']['net_sales']) }}</td>
                                <td class="px-4 py-2 text-end">{{ Money::format($report['shop_totals']['net_gst']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow" data-report-iruali>
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('iruali') }}</h2>
                <p class="text-xs text-gray-500">
                    @if($report['registered_orders'] > 0)
                        {{ __('GST is shown for orders placed while iruali was GST-registered (:count this month). Amounts include GST.', ['count' => $report['registered_orders']]) }}
                    @else
                        {{ __('iruali was not GST-registered for any order paid this month, so no GST is shown.') }}
                    @endif
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-start"></th>
                            <th class="px-4 py-2 text-end">{{ __('Amount') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('GST') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('Refunded') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('GST on refunds') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('Net') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('Net GST') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach(['commission' => __('Commission'), 'delivery' => __('Delivery fees')] as $key => $label)
                            @php $line = $report['iruali'][$key]; @endphp
                            <tr data-line="{{ $key }}">
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $label }}</td>
                                <td class="px-4 py-2 text-end">{{ Money::format($line['amount']) }}</td>
                                <td class="px-4 py-2 text-end">{{ Money::format($line['gst']) }}</td>
                                <td class="px-4 py-2 text-end text-red-700">{{ $line['refunded'] > 0 ? '−'.Money::format($line['refunded']) : Money::format(0) }}</td>
                                <td class="px-4 py-2 text-end text-red-700">{{ $line['refunded_gst'] > 0 ? '−'.Money::format($line['refunded_gst']) : Money::format(0) }}</td>
                                <td class="px-4 py-2 text-end font-semibold">{{ Money::format($line['net']) }}</td>
                                <td class="px-4 py-2 text-end font-semibold">{{ Money::format($line['net_gst']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 font-semibold text-gray-900">
                        <tr>
                            <td class="px-4 py-2">{{ __('Total') }}</td>
                            <td class="px-4 py-2 text-end">{{ Money::format($report['iruali_totals']['amount']) }}</td>
                            <td class="px-4 py-2 text-end">{{ Money::format($report['iruali_totals']['gst']) }}</td>
                            <td class="px-4 py-2 text-end">{{ Money::format($report['iruali_totals']['refunded']) }}</td>
                            <td class="px-4 py-2 text-end">{{ Money::format($report['iruali_totals']['refunded_gst']) }}</td>
                            <td class="px-4 py-2 text-end">{{ Money::format($report['iruali_totals']['net']) }}</td>
                            <td class="px-4 py-2 text-end">{{ Money::format($report['iruali_totals']['net_gst']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @unless($registeredNow)
                <p class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500">{{ __('iruali is not GST-registered now. Switch it on under Admin → Tax once registered.') }}</p>
            @endunless
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow" data-report-refunds>
            <div class="border-b border-gray-100 px-5 py-4"><h2 class="text-lg font-semibold text-gray-900">{{ __('Refunds in this month') }}</h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-start">{{ __('Date') }}</th>
                            <th class="px-4 py-2 text-start">{{ __('Order') }}</th>
                            <th class="px-4 py-2 text-start">{{ __('Shop') }}</th>
                            <th class="px-4 py-2 text-start">{{ __('Type') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('Goods') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('Shop GST') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('Delivery') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('Commission given back') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($report['refunds'] as $refund)
                            <tr>
                                <td class="px-4 py-2 text-gray-600">{{ $refund['date']?->format('j M Y') }}</td>
                                <td class="px-4 py-2"><a href="{{ route('admin.orders.show', $refund['order']) }}" class="font-medium text-primary-700 hover:underline" dir="ltr">{{ $refund['order']->order_number }}</a></td>
                                <td class="px-4 py-2">{{ $refund['part']->gst_business_name ?: $refund['part']->shopName() }}</td>
                                <td class="px-4 py-2">{{ $kinds[$refund['kind']] ?? $refund['kind'] }}</td>
                                <td class="px-4 py-2 text-end">{{ Money::format($refund['goods']) }}</td>
                                <td class="px-4 py-2 text-end">{{ Money::format($refund['goods_gst']) }}</td>
                                <td class="px-4 py-2 text-end">{{ Money::format($refund['delivery']) }}</td>
                                <td class="px-4 py-2 text-end">{{ Money::format($refund['commission']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">{{ __('No refunds in this month.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-xs text-gray-500">{{ __('This report helps you prepare your GST return; it is not a filing. Check the figures and what to file with your accountant or MIRA.') }}</p>
    </div>
</div>
@endsection
