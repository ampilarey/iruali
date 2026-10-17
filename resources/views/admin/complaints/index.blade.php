@extends('layouts.admin')

@section('title', 'Complaints')

@section('content')
<div class="max-w-6xl mx-auto py-6 px-4 space-y-4">
    <div>
        <h1 class="text-xl font-bold lg:text-2xl">Complaints</h1>
        <p class="text-sm text-gray-500">From the complaint form in the site footer and on order pages. Open ones untouched for 3 days are sent as a reminder; a summary goes out every Monday.</p>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-lg bg-white p-4 shadow"><p class="text-xs text-gray-500">Open</p><p class="mt-1 text-lg font-bold">{{ (int) ($counts['open'] ?? 0) + (int) ($counts['in_progress'] ?? 0) }}</p></div>
        <div class="rounded-lg bg-white p-4 shadow"><p class="text-xs text-gray-500">Resolved</p><p class="mt-1 text-lg font-bold">{{ (int) ($counts['resolved'] ?? 0) }}</p></div>
        <div class="rounded-lg bg-white p-4 shadow"><p class="text-xs text-gray-500">Order rating, 30 days</p><p class="mt-1 text-lg font-bold">{{ $ratings['count'] ? $ratings['average'].' / 5' : '–' }}</p></div>
        <div class="rounded-lg bg-white p-4 shadow"><p class="text-xs text-gray-500">Ratings, 30 days</p><p class="mt-1 text-lg font-bold">{{ $ratings['count'] }}</p></div>
    </div>

    <nav class="flex flex-wrap gap-2 text-sm" aria-label="Filter by status">
        @foreach(['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed', 'all' => 'All'] as $key => $label)
            <a href="{{ route('admin.complaints', ['status' => $key]) }}" @class(['rounded-full px-3 py-1', 'bg-primary-600 text-white' => $status === $key, 'bg-white text-gray-700 shadow' => $status !== $key])>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500"><th class="p-3 text-start">Reference</th><th class="p-3 text-start">From</th><th class="p-3 text-start">About</th><th class="p-3 text-start">Status</th><th class="p-3 text-end">Sent</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($complaints as $complaint)
                    <tr>
                        <td class="p-3"><a href="{{ route('admin.complaints.show', $complaint) }}" class="font-mono text-primary-700 hover:underline">{{ $complaint->reference }}</a></td>
                        <td class="p-3">{{ $complaint->name }}<br><span class="text-xs text-gray-500 break-all">{{ $complaint->email }}</span></td>
                        <td class="p-3">{{ $complaint->categoryLabel() }}</td>
                        <td class="p-3">{{ $complaint->statusLabel() }}</td>
                        <td class="p-3 text-end whitespace-nowrap">{{ $complaint->created_at->format('j M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-3 text-gray-500">No complaints here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $complaints->links() }}

    <x-admin.fold title="Latest low order ratings" flush>
        <ul class="divide-y divide-gray-100 text-sm">
            @forelse($lowRatings as $rating)
                <li class="p-3">
                    <span class="font-semibold">{{ $rating->rating }}/5</span>
                    @if($rating->order)· <a href="{{ route('admin.orders.show', $rating->order) }}" class="font-mono text-primary-700 hover:underline">{{ $rating->order->order_number }}</a>@endif
                    · {{ $rating->created_at->format('j M Y') }}
                    @if($rating->comment)<p class="mt-1 text-gray-700">{{ $rating->comment }}</p>@endif
                </li>
            @empty
                <li class="p-3 text-gray-500">No low ratings.</li>
            @endforelse
        </ul>
    </x-admin.fold>
</div>
@endsection
