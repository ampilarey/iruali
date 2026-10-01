@extends('layouts.app')

@section('title', 'Campaigns')

@section('content')
<div class="max-w-6xl mx-auto py-8 px-4">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold">Campaigns</h1>
            <p class="text-sm text-gray-500">Sales and events: a banner on the home page, a landing page, and discounted products from shops.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.dashboard') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Back to Dashboard</a>
            <a href="{{ route('admin.campaigns.create') }}" class="bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-700">+ New campaign</a>
        </div>
    </div>
    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-100 text-start">
                    <th class="p-3 text-start">Campaign</th>
                    <th class="p-3 text-start">Type</th>
                    <th class="p-3 text-start">Runs</th>
                    <th class="p-3 text-start">Placement</th>
                    <th class="p-3 text-end">Min. discount</th>
                    <th class="p-3 text-end">Products</th>
                    <th class="p-3 text-start">Status</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($campaigns as $campaign)
                    <tr>
                        <td class="p-3"><a href="{{ route('admin.campaigns.edit', $campaign) }}" class="font-medium text-primary-600 hover:underline">{{ $campaign->name }}</a><br><span class="text-xs text-gray-500">/campaigns/{{ $campaign->slug }}</span></td>
                        <td class="p-3">{{ ucfirst($campaign->type) }}</td>
                        <td class="p-3 whitespace-nowrap">{{ $campaign->starts_at->format('d M Y H:i') }} – {{ $campaign->ends_at->format('d M Y H:i') }}</td>
                        <td class="p-3">{{ str_replace('_', ' ', $campaign->placement) }}</td>
                        <td class="p-3 text-end">{{ $campaign->discount_percent !== null ? rtrim(rtrim(number_format((float) $campaign->discount_percent, 2, '.', ''), '0'), '.').'%' : '—' }}</td>
                        <td class="p-3 text-end">{{ $campaign->participations_count }}@if($campaign->pending_count > 0) <span class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-semibold text-yellow-800">{{ $campaign->pending_count }} pending</span>@endif</td>
                        <td class="p-3">
                            @if(! $campaign->is_active)<span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">Off</span>
                            @elseif($campaign->isLive())<span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">Live</span>
                            @elseif($campaign->isUpcoming())<span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800">Scheduled</span>
                            @else<span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">Ended</span>@endif
                        </td>
                        <td class="p-3 text-end whitespace-nowrap">
                            <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="text-primary-600 hover:underline">Edit</a>
                            <form action="{{ route('admin.campaigns.destroy', $campaign) }}" method="POST" class="inline ms-2" onsubmit="return confirm('Delete this campaign?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-6 text-center text-gray-500">No campaigns yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $campaigns->links() }}</div>
    </div>
</div>
@endsection
