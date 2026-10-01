@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => 'Inbox'])

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <p class="text-sm text-gray-600">
            @if($total)
                <strong>{{ $total }}</strong> thing(s) waiting for someone. Each row opens the page that deals with it.
            @else
                Nothing is waiting. Come back later.
            @endif
        </p>

        <ul class="divide-y divide-gray-100 overflow-hidden rounded-lg bg-white shadow">
            @foreach($items as $item)
                @php $tone = ['danger' => 'bg-red-100 text-red-800', 'warn' => 'bg-amber-100 text-amber-800', 'info' => 'bg-blue-100 text-blue-800'][$item['severity']]; @endphp
                <li data-inbox="{{ $item['key'] }}" data-count="{{ $item['count'] }}">
                    <a href="{{ $item['url'] }}" class="flex items-center justify-between gap-4 px-4 py-3 hover:bg-gray-50 {{ $item['count'] ? '' : 'text-gray-400' }}">
                        <span class="font-medium">{{ $item['label'] }}</span>
                        <span class="rounded-full px-2.5 py-0.5 text-sm font-semibold {{ $item['count'] ? $tone : 'bg-gray-100 text-gray-500' }}">{{ $item['count'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
