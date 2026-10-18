@extends('layouts.admin')

@section('title', 'SMS campaigns')

@php $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500'; @endphp

@section('content')
<div class="max-w-5xl mx-auto py-6 px-4 space-y-4">
    <div>
        <h1 class="text-xl font-bold lg:text-2xl">SMS campaigns</h1>
        <p class="text-sm text-gray-500">One text to a group of customers who chose offers by SMS. A stop link is added to every message. The monthly limit and quiet hours under <a href="{{ route('admin.sms') }}" class="underline">SMS</a> apply.</p>
    </div>
    @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif

    <form method="POST" action="{{ route('admin.sms.campaigns.store') }}">
        @csrf
        <x-admin.fold title="New campaign" body="space-y-3 text-sm" :open="$errors->any() || $campaigns->isEmpty()" id="new-campaign">
            <div>
                <label for="c-name" class="block text-xs font-medium text-gray-700">Name (for you)</label>
                <input id="c-name" name="name" required maxlength="120" value="{{ old('name') }}" class="{{ $field }}" placeholder="Eid sale, Addu">
                @error('name')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="c-message" class="block text-xs font-medium text-gray-700">Message ({name} is the customer's first name)</label>
                <textarea id="c-message" name="message" rows="3" required maxlength="600" class="{{ $field }}" placeholder="iruali: hi {name}, 20% off fishing gear this weekend. iruali.mv/deals">{{ old('message') }}</textarea>
                @error('message')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="c-segment" class="block text-xs font-medium text-gray-700">Send to</label>
                    <select id="c-segment" name="segment" class="{{ $field }}">
                        @foreach(\App\Support\SmsSegments::SEGMENTS as $key => [$label])
                            <option value="{{ $key }}" @selected(old('segment') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="c-value" class="block text-xs font-medium text-gray-700">Which (for a category, brand or island)</label>
                    <select id="c-value" name="segment_value" class="{{ $field }}">
                        <option value="">—</option>
                        <optgroup label="Categories">@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->getTranslation('name', 'en') }}</option>@endforeach</optgroup>
                        <optgroup label="Brands">@foreach($brands as $brand)<option value="{{ $brand->id }}">{{ $brand->name }}</option>@endforeach</optgroup>
                        <optgroup label="Islands">@foreach($islands as $island)<option value="{{ $island->id }}">{{ $island->getTranslation('name', 'en') }}{{ $island->atoll ? ', '.$island->atoll : '' }}</option>@endforeach</optgroup>
                    </select>
                    @error('segment_value')<p class="mt-1 text-xs text-red-700">Choose which category, brand or island.</p>@enderror
                </div>
            </div>
            <button class="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">Save draft</button>
        </x-admin.fold>
    </form>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500"><th class="p-3 text-start">Campaign</th><th class="p-3 text-start">To</th><th class="p-3 text-start">Status</th><th class="p-3 text-end">Sent</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($campaigns as $campaign)
                    <tr>
                        <td class="p-3"><a href="{{ route('admin.sms.campaigns.show', $campaign) }}" class="text-primary-700 hover:underline">{{ $campaign->name }}</a></td>
                        <td class="p-3">{{ \App\Support\SmsSegments::SEGMENTS[$campaign->segment][0] ?? $campaign->segment }}</td>
                        <td class="p-3">{{ ucfirst($campaign->status) }}@if($campaign->status === 'scheduled') · {{ $campaign->scheduled_at?->format('j M H:i') }}@endif</td>
                        <td class="p-3 text-end">{{ $campaign->status === 'sent' ? number_format($campaign->sent_count).' / '.number_format($campaign->recipients_count) : '–' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-3 text-gray-500">No campaigns yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $campaigns->links() }}
</div>
@endsection
