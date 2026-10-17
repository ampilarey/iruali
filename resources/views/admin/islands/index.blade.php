@extends('layouts.admin')

@php $field = 'rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500'; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3 py-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-primary-600">{{ __('Settings') }}</p>
                    <h1 class="text-2xl font-bold text-gray-900">{{ __('Islands') }}</h1>
                </div>
                <a href="{{ route('admin.delivery') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Delivery rates') }}</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <div class="rounded-lg bg-white p-5 shadow text-sm text-gray-600 space-y-1">
            <p>{{ __(':active of :all islands are switched on. Customers and shops choose from the switched-on islands; addresses and orders already on a switched-off island are not changed.', ['active' => $totals['active'], 'all' => $totals['all']]) }}</p>
            <p>{{ __('"Shops based here" are approved shops with this home island. "Shops delivering here" counts every approved shop that delivers to all islands plus the island shops that reach this one.') }}</p>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow">
            <div>
                <label for="island-q" class="block text-xs font-medium text-gray-600">{{ __('Search') }}</label>
                <input id="island-q" name="q" value="{{ $q }}" class="{{ $field }}" placeholder="{{ __('Island or atoll') }}">
            </div>
            <div>
                <label for="island-atoll" class="block text-xs font-medium text-gray-600">{{ __('Atoll') }}</label>
                <select id="island-atoll" name="atoll" class="{{ $field }}">
                    <option value="">{{ __('All atolls') }}</option>
                    @foreach($atolls as $a)<option value="{{ $a }}" @selected($atoll === $a)>{{ $a }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="island-status" class="block text-xs font-medium text-gray-600">{{ __('Status') }}</label>
                <select id="island-status" name="status" class="{{ $field }}">
                    <option value="">{{ __('All') }}</option>
                    <option value="active" @selected($status === 'active')>{{ __('Switched on') }}</option>
                    <option value="inactive" @selected($status === 'inactive')>{{ __('Switched off') }}</option>
                </select>
            </div>
            <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Filter') }}</button>
        </form>

        <div class="overflow-x-auto rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-start text-xs font-medium uppercase text-gray-500">{{ __('Island') }}</th>
                        <th class="px-4 py-2 text-start text-xs font-medium uppercase text-gray-500">{{ __('Atoll') }}</th>
                        <th class="px-4 py-2 text-start text-xs font-medium uppercase text-gray-500">{{ __('Shops based here') }}</th>
                        <th class="px-4 py-2 text-start text-xs font-medium uppercase text-gray-500">{{ __('Shops delivering here') }}</th>
                        <th class="px-4 py-2 text-start text-xs font-medium uppercase text-gray-500">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($islands as $island)
                        <tr data-island-row="{{ $island->id }}">
                            <td class="px-4 py-2">
                                <span class="font-medium text-gray-900">{{ $island->englishName() }}</span>
                                <span class="ms-1 text-gray-500" lang="dv" dir="rtl">{{ $island->getTranslation('name', 'dv', false) }}</span>
                                @unless($island->hasPosition())<span class="block text-xs text-gray-400">{{ __('No map position: not offered by "Use my location"') }}</span>@endunless
                            </td>
                            <td class="px-4 py-2 text-gray-700">{{ $island->atoll }} @if($island->atoll_dv)<span class="text-gray-500" lang="dv">({{ $island->atoll_dv }})</span>@endif</td>
                            <td class="px-4 py-2 text-gray-700">{{ $island->shops_count }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ $island->delivering_count }}</td>
                            <td class="px-4 py-2">
                                <form method="POST" action="{{ route('admin.islands.toggle', $island) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="active" value="{{ $island->is_active ? 0 : 1 }}">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $island->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $island->is_active ? __('On') : __('Off') }}</span>
                                    <button class="text-xs font-medium text-primary-600 hover:underline">{{ $island->is_active ? __('Switch off') : __('Switch on') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('No islands match.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
