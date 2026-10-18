@extends('layouts.admin')

@section('title', 'Webhooks')

@section('content')
<div class="max-w-4xl mx-auto py-6 px-4 space-y-4">
    <div>
        <h1 class="text-xl font-bold lg:text-2xl">Webhooks</h1>
        <p class="text-sm text-gray-500">Tell another system (accounting, a warehouse, Zapier) about orders. iruali POSTs JSON to the address, signed in the <code>X-Iruali-Signature</code> header (sha256 HMAC of the body with the secret). No customer contact details are sent.</p>
    </div>
    @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('admin.webhooks.store') }}" class="rounded-lg bg-white p-4 shadow text-sm space-y-3">
        @csrf
        <label class="block text-xs font-medium text-gray-700">Address (https)<input name="url" type="url" required value="{{ old('url') }}" placeholder="https://example.com/iruali-orders" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2"></label>
        @error('url')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
        <div class="flex flex-wrap gap-4">@foreach(\App\Support\Webhooks::EVENTS as $event => $label)<label class="flex items-center gap-1"><input type="checkbox" name="events[]" value="{{ $event }}" checked> {{ $label }}</label>@endforeach</div>
        <button class="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">Add webhook</button>
    </form>

    @foreach($endpoints as $endpoint)
        <div class="rounded-lg bg-white p-4 shadow text-sm space-y-2" data-webhook>
            <p class="font-medium break-all">{{ $endpoint->url }} @unless($endpoint->is_active)<span class="text-xs text-gray-500">(paused)</span>@endunless</p>
            <p class="text-xs text-gray-500">{{ implode(', ', (array) $endpoint->events) }} · secret <code class="select-all">{{ $endpoint->getAttribute('secret') }}</code></p>
            <p class="text-xs">Last: @if($endpoint->last_sent_at){{ $endpoint->last_sent_at->format('j M H:i') }} · {{ $endpoint->last_error ?? 'HTTP '.$endpoint->last_status }}@else never sent @endif</p>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.webhooks.test', $endpoint) }}">@csrf<button class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50">Send a test</button></form>
                <form method="POST" action="{{ route('admin.webhooks.toggle', $endpoint) }}">@csrf<button class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50">{{ $endpoint->is_active ? 'Pause' : 'Resume' }}</button></form>
                <form method="POST" action="{{ route('admin.webhooks.destroy', $endpoint) }}" onsubmit="return confirm('Remove this webhook?')">@csrf @method('DELETE')<button class="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50">Remove</button></form>
            </div>
        </div>
    @endforeach
</div>
@endsection
