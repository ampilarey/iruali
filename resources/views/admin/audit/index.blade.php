@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => 'Audit log'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <form method="GET" action="{{ route('admin.audit') }}" class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow text-sm">
            <label class="block">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Action</span>
                <select name="action" class="mt-1 block rounded-lg border-gray-300">
                    <option value="">All actions</option>
                    @foreach($actions as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['action'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">User</span>
                <select name="user" class="mt-1 block rounded-lg border-gray-300">
                    <option value="">Anyone</option>
                    @foreach($actors as $actor)
                        <option value="{{ $actor->id }}" @selected((int) ($filters['user'] ?? 0) === $actor->id)>{{ $actor->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">From</span>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 block rounded-lg border-gray-300">
            </label>
            <label class="block">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">To</span>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 block rounded-lg border-gray-300">
            </label>
            <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 font-medium text-white hover:bg-primary-700">Filter</button>
            <a href="{{ route('admin.audit') }}" class="text-gray-600 hover:underline">Clear</a>
        </form>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr><th class="px-4 py-3 text-start">When</th><th class="px-4 py-3 text-start">Who</th><th class="px-4 py-3 text-start">Action</th><th class="px-4 py-3 text-start">Subject</th><th class="px-4 py-3 text-start">Changes</th><th class="px-4 py-3 text-start">IP</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($logs as $log)
                            <tr class="hover:bg-gray-50 align-top">
                                <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                                <td class="px-4 py-3">{{ $log->user?->name ?? 'System' }}</td>
                                <td class="px-4 py-3"><span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800">{{ $actions[$log->action] ?? $log->action }}</span></td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($url = $log->subjectUrl())
                                        <a href="{{ $url }}" class="font-medium text-primary-700 hover:underline">{{ $log->subjectLabel() }}</a>
                                    @else
                                        {{ $log->subjectLabel() }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-600">
                                    @foreach($log->changes ?? [] as $key => $value)
                                        <div><span class="text-gray-400">{{ $key }}:</span> {{ is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value, JSON_UNESCAPED_UNICODE) }}</div>
                                    @endforeach
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500">{{ $log->ip }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Nothing recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($logs->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $logs->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
