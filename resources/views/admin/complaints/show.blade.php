@extends('layouts.admin')

@section('title', 'Complaint '.$complaint->reference)

@section('content')
<div class="max-w-4xl mx-auto py-6 px-4 space-y-4">
    <div>
        <a href="{{ route('admin.complaints') }}" class="text-sm text-primary-700 hover:underline">← Complaints</a>
        <h1 class="mt-1 text-xl font-bold lg:text-2xl">Complaint {{ $complaint->reference }}</h1>
        <p class="text-sm text-gray-600">{{ $complaint->categoryLabel() }} · {{ $complaint->statusLabel() }} · sent {{ $complaint->created_at->format('j M Y H:i') }}</p>
    </div>

    @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif

    <div class="rounded-lg bg-white p-4 shadow text-sm space-y-2 sm:p-6">
        <p><span class="text-gray-500">From:</span> {{ $complaint->name }} · <a href="mailto:{{ $complaint->email }}?subject={{ rawurlencode('Your complaint '.$complaint->reference) }}" class="text-primary-700 hover:underline break-all">{{ $complaint->email }}</a>@if($complaint->phone) · {{ $complaint->phone }}@endif</p>
        @if($complaint->user && \App\Support\StaffAccess::can('admin.customers.show'))<p><a href="{{ route('admin.customers.show', $complaint->user) }}" class="text-primary-700 hover:underline">Customer page</a></p>@endif
        @if($complaint->order)<p><span class="text-gray-500">Order:</span> <a href="{{ route('admin.orders.show', $complaint->order) }}" class="font-mono text-primary-700 hover:underline">{{ $complaint->order->order_number }}</a></p>@endif
        <p class="whitespace-pre-line rounded-lg bg-gray-50 p-3">{{ $complaint->details }}</p>
        @if($complaint->photos)
            <div class="flex flex-wrap gap-2">
                @foreach($complaint->photos as $i => $photo)
                    <a href="{{ route('admin.complaints.photo', [$complaint, $i]) }}" target="_blank" rel="noopener"><img src="{{ route('admin.complaints.photo', [$complaint, $i]) }}" alt="Photo {{ $i + 1 }}" class="h-24 w-24 rounded-lg object-cover"></a>
                @endforeach
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.complaints.update', $complaint) }}" class="rounded-lg bg-white p-4 shadow text-sm space-y-3 sm:p-6">
        @csrf @method('PUT')
        <h2 class="font-semibold">Status and notes</h2>
        <div>
            <label for="status" class="block text-xs font-medium text-gray-700">Status</label>
            <select id="status" name="status" class="mt-1 rounded-lg border border-gray-300 px-3 py-2">
                @foreach(\App\Models\Complaint::STATUSES as $key => $label)<option value="{{ $key }}" @selected($complaint->status === $key)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="note" class="block text-xs font-medium text-gray-700">Add a note (staff only)</label>
            <textarea id="note" name="note" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">{{ old('note') }}</textarea>
        </div>
        <button class="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">Save</button>
        <ul class="space-y-2">
            @foreach(array_reverse((array) $complaint->notes) as $note)
                <li class="rounded-lg bg-gray-50 p-3"><p class="whitespace-pre-line">{{ $note['body'] ?? '' }}</p><p class="mt-1 text-xs text-gray-500">{{ $note['by'] ?? 'Staff' }} · {{ $note['at'] ?? '' }}</p></li>
            @endforeach
        </ul>
    </form>
</div>
@endsection
