@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => $event->shortClass(), 'back' => route('admin.errors')])

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <div class="rounded-lg bg-white p-6 shadow">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-mono text-sm text-gray-900">{{ $event->exception_class }}</p>
                    <p class="mt-1 text-gray-700">{{ $event->message }}</p>
                </div>
                @if($event->isResolved())
                    <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800">Resolved {{ $event->resolved_at->format('d M Y H:i') }}</span>
                @elseif(auth()->user()->hasRole('admin'))
                    <form method="POST" action="{{ route('admin.errors.resolve', $event) }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Mark resolved</button>
                    </form>
                @endif
            </div>

            <dl class="mt-6 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">File</dt><dd class="font-mono text-xs text-gray-900">{{ $event->shortFile() }}:{{ $event->line }}</dd></div>
                <div><dt class="text-gray-500">Count</dt><dd class="font-semibold text-gray-900">{{ $event->count }}</dd></div>
                <div><dt class="text-gray-500">First seen</dt><dd class="text-gray-900">{{ $event->first_seen_at?->format('d M Y H:i') }}</dd></div>
                <div><dt class="text-gray-500">Last seen</dt><dd class="text-gray-900">{{ $event->last_seen_at?->format('d M Y H:i') }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-gray-500">Last URL</dt><dd class="break-all text-gray-900">{{ $event->url ?: '(console / queue)' }}</dd></div>
                <div><dt class="text-gray-500">Last user</dt><dd class="text-gray-900">{{ $event->user ? $event->user->name.' ('.$event->user->email.')' : '—' }}</dd></div>
                <div><dt class="text-gray-500">Fingerprint</dt><dd class="font-mono text-xs text-gray-500">{{ $event->fingerprint }}</dd></div>
            </dl>
            <p class="mt-6 text-xs text-gray-500">The full stack trace is in storage/logs (search for the message). Resolved errors reopen on their own if they happen again.</p>
        </div>
    </div>
</div>
@endsection
