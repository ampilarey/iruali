@extends('layouts.admin')

@section('content')
<div class="min-h-screen bg-gray-100 pb-6 lg:pb-12">
    @include('admin.payouts._header', ['title' => 'Inbox'])

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <p class="text-sm text-gray-600">
            @if($total)
                <strong>{{ $total }}</strong> thing(s) waiting for someone. Each row opens the page that deals with it.
            @else
                Nothing is waiting. Come back later.
            @endif
        </p>

        @php
            $waiting = collect($items)->filter(fn ($i) => $i['count'] > 0);
            $clear = collect($items)->reject(fn ($i) => $i['count'] > 0);
        @endphp

        @if($waiting->isNotEmpty())
            <ul class="divide-y divide-gray-100 overflow-hidden rounded-lg bg-white shadow" data-inbox-waiting>
                @foreach($waiting as $item)
                    @php $tone = ['danger' => 'bg-red-100 text-red-800', 'warn' => 'bg-amber-100 text-amber-800', 'info' => 'bg-blue-100 text-blue-800'][$item['severity']]; @endphp
                    <li data-inbox="{{ $item['key'] }}" data-count="{{ $item['count'] }}">
                        <a href="{{ $item['url'] }}" class="flex min-h-12 items-center justify-between gap-4 px-4 py-3 hover:bg-gray-50">
                            <span class="font-medium text-gray-900">{{ $item['label'] }}</span>
                            <span class="flex shrink-0 items-center gap-2">
                                <span class="rounded-full px-2.5 py-0.5 text-sm font-semibold {{ $tone }}">{{ $item['count'] }}</span>
                                <svg class="h-4 w-4 text-gray-400 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        @if($clear->isNotEmpty())
            <details class="group overflow-hidden rounded-lg bg-white shadow" data-inbox-clear>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 text-sm font-medium text-gray-600 [&::-webkit-details-marker]:hidden">
                    <span>{{ __('All clear') }} ({{ $clear->count() }})</span>
                    <svg class="h-4 w-4 text-gray-400 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <ul class="divide-y divide-gray-100 border-t border-gray-100">
                    @foreach($clear as $item)
                        <li data-inbox="{{ $item['key'] }}" data-count="0">
                            <a href="{{ $item['url'] }}" class="flex items-center justify-between gap-4 px-4 py-3 text-gray-400 hover:bg-gray-50">
                                <span class="font-medium">{{ $item['label'] }}</span>
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-semibold text-gray-500">0</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
</div>
@endsection
