@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => 'Errors'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <p class="text-sm text-gray-600">Every exception the site reports is counted here by place in the code. Payment errors also email {{ \App\Support\ErrorTracker::alertsTo() ?: 'nobody (set ALERTS_EMAIL)' }} at once; a digest goes out at 07:00 when there is something new.</p>

        <nav class="flex flex-wrap gap-2 text-sm">
            @foreach(['unresolved' => 'Unresolved', 'all' => 'All'] as $key => $label)
                <a href="{{ route('admin.errors', ['status' => $key]) }}" class="rounded-full px-3 py-1.5 font-medium {{ $status === $key ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 shadow hover:bg-gray-50' }}">{{ $label }} ({{ $counts[$key] }})</a>
            @endforeach
        </nav>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr><th class="px-4 py-3 text-start">Last seen</th><th class="px-4 py-3 text-start">Exception</th><th class="px-4 py-3 text-start">Where</th><th class="px-4 py-3 text-end">Count</th><th class="px-4 py-3 text-start">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($events as $event)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-gray-600"><a href="{{ route('admin.errors.show', $event) }}" class="font-medium text-primary-700 hover:underline">{{ $event->last_seen_at?->format('d M Y H:i') }}</a></td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ $event->shortClass() }}</div>
                                    <div class="max-w-md truncate text-gray-500" title="{{ $event->message }}">{{ $event->message }}</div>
                                    @if($event->url)<div class="max-w-md truncate text-xs text-gray-400">{{ $event->url }}</div>@endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $event->shortFile() }}:{{ $event->line }}</td>
                                <td class="px-4 py-3 text-end font-semibold">{{ $event->count }}</td>
                                <td class="px-4 py-3">
                                    @if($event->isResolved())
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Resolved</span>
                                    @else
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Open</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No errors recorded. Good.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($events->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $events->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
