@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => 'SMS'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <div class="rounded-lg bg-white p-5 shadow text-sm space-y-2">
                <h2 class="font-semibold text-gray-900">Gateway</h2>
                @if($live)
                    <p class="text-green-800">Driver <code class="rounded bg-gray-100 px-1">{{ $driver }}</code>: messages are sent through <span class="break-all">{{ config('sms.http.url') }}</span>.</p>
                @else
                    <p class="text-amber-800">Driver <code class="rounded bg-gray-100 px-1">log</code>: nothing is sent. Messages are written to the log and listed here. Set <code>SMS_DRIVER=http</code> with <code>SMS_URL</code>, <code>SMS_METHOD</code>, <code>SMS_AUTH_HEADER</code>, <code>SMS_BODY_TEMPLATE</code>, <code>SMS_SENDER_ID</code> and <code>SMS_SUCCESS_REGEX</code> in <code>.env</code> to connect a Dhiraagu / Ooredoo bulk-SMS account.</p>
                @endif
                <p class="text-xs text-gray-500">Last 30 days: {{ $counts['sent'] ?? 0 }} sent · {{ $counts['failed'] ?? 0 }} failed · {{ $counts['invalid'] ?? 0 }} invalid numbers · {{ $counts['logged'] ?? 0 }} logged only</p>
            </div>

            <form method="POST" action="{{ route('admin.sms.test') }}" class="rounded-lg bg-white p-5 shadow text-sm space-y-3">
                @csrf
                <h2 class="font-semibold text-gray-900">Send a test SMS</h2>
                <div>
                    <label for="to" class="block font-medium text-gray-700">Mobile number</label>
                    <input id="to" name="to" value="{{ old('to') }}" required dir="ltr" placeholder="777 1234" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                    @error('to')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="message" class="block font-medium text-gray-700">Message</label>
                    <textarea id="message" name="message" rows="3" maxlength="320" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">{{ old('message', 'Test message from iruali.') }}</textarea>
                    @error('message')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
                <button class="w-full rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">Send test</button>
            </form>
        </div>

        <div class="lg:col-span-2 overflow-hidden rounded-lg bg-white shadow">
            <div class="border-b border-gray-100 px-5 py-4"><h2 class="text-lg font-semibold text-gray-900">Recent messages</h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr><th class="px-4 py-3 text-left">When</th><th class="px-4 py-3 text-left">To</th><th class="px-4 py-3 text-left">Message</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Gateway reply</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($messages as $m)
                            <tr class="align-top hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $m->created_at?->format('d M, H:i') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap" dir="ltr">{{ $m->to }}</td>
                                <td class="px-4 py-3 max-w-md whitespace-pre-line text-gray-800">{{ $m->message }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $m->status_badge }}">{{ $m->status }}</span>@if($m->cost !== null)<span class="block text-xs text-gray-500">MVR {{ $m->cost }}</span>@endif</td>
                                <td class="px-4 py-3 max-w-xs truncate text-xs text-gray-500" title="{{ $m->provider_response }}">{{ \Illuminate\Support\Str::limit($m->provider_response, 80) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No messages yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $messages->links() }}</div>
        </div>
    </div>
</div>
@endsection
