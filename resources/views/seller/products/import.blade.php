@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @component('seller.partials.header', ['title' => __('Import products')])
        @slot('action')
            <a href="{{ route('seller.products.export') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Export CSV') }}</a>
        @endslot
    @endcomponent

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @if($result)
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ __('Import done: :created created, :updated updated, :errors skipped.', ['created' => $result['create'], 'updated' => $result['update'], 'errors' => $result['error']]) }}
                <a href="{{ route('seller.products.index') }}" class="ms-2 font-semibold underline">{{ __('View products') }}</a>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc ps-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-base font-semibold text-gray-900">{{ __('Upload a CSV') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('Use the same columns as the export. Rows are matched to your products and variants by SKU: known SKUs are updated (price, stock, names, descriptions, active), new SKUs create products that wait for approval. Nothing changes until you confirm the preview.') }}</p>
            <ul class="mt-2 list-disc ps-5 text-xs text-gray-500 space-y-0.5">
                <li>{{ __('Variant rows need variant_attributes (e.g. "Size=M;Colour=Blue") and product_sku, the SKU of the product they belong to.') }}</li>
                <li>{{ __('Up to :max rows per file. Image URLs are only kept when they are already your uploads; nothing is downloaded.', ['max' => \App\Services\ProductCsvService::MAX_ROWS]) }}</li>
                <li><a href="{{ route('seller.products.import.sample') }}" class="font-medium text-primary-600 hover:underline">{{ __('Download the sample CSV') }}</a></li>
            </ul>
            <form method="POST" action="{{ route('seller.products.import.preview') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="file" accept=".csv,text/csv" required class="block text-sm text-gray-700">
                <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Preview import') }}</button>
            </form>
        </div>

        @if($preview)
            @php $counts = $preview['counts']; @endphp
            <div class="rounded-lg bg-white shadow">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">{{ __('Preview') }}</h2>
                        <p class="text-sm text-gray-600">
                            <span class="font-medium text-green-700">{{ trans_choice(':count row creates|:count rows create', $counts['create'], ['count' => $counts['create']]) }}</span> ·
                            <span class="font-medium text-blue-700">{{ trans_choice(':count row updates|:count rows update', $counts['update'], ['count' => $counts['update']]) }}</span> ·
                            <span class="font-medium text-red-700">{{ trans_choice(':count row has errors|:count rows have errors', $counts['error'], ['count' => $counts['error']]) }}</span>
                        </p>
                    </div>
                    @if($counts['create'] + $counts['update'] > 0)
                        <form method="POST" action="{{ route('seller.products.import.apply') }}">
                            @csrf
                            <input type="hidden" name="token" value="{{ $token }}">
                            <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Apply :count rows', ['count' => $counts['create'] + $counts['update']]) }}</button>
                            @if($counts['error'] > 0)<p class="mt-1 text-xs text-gray-500">{{ __('Rows with errors are skipped.') }}</p>@endif
                        </form>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-start">{{ __('Line') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('SKU') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Product') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Action') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Details') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($preview['rows'] as $row)
                                <tr class="{{ $row['action'] === 'error' ? 'bg-red-50' : '' }}">
                                    <td class="px-4 py-2 text-gray-500" dir="ltr">{{ $row['line'] }}</td>
                                    <td class="px-4 py-2 text-gray-900" dir="ltr">{{ $row['sku'] }}</td>
                                    <td class="px-4 py-2 text-gray-900">{{ $row['name'] }}</td>
                                    <td class="px-4 py-2">
                                        @if($row['action'] === 'create')<span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">{{ __('Create') }}</span>
                                        @elseif($row['action'] === 'update')<span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">{{ __('Update') }}</span>
                                        @else<span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">{{ __('Error') }}</span>@endif
                                    </td>
                                    <td class="px-4 py-2 text-xs {{ $row['action'] === 'error' ? 'text-red-700' : 'text-gray-600' }}">{{ implode('; ', $row['action'] === 'error' ? $row['errors'] : $row['changes']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
