@extends('layouts.admin')

@section('title', $campaign->name)

@section('content')
<div class="max-w-3xl mx-auto py-6 px-4 space-y-4" data-sms-campaign>
    <div>
        <a href="{{ route('admin.sms.campaigns') }}" class="text-sm text-primary-700 hover:underline">← SMS campaigns</a>
        <h1 class="mt-1 text-xl font-bold lg:text-2xl">{{ $campaign->name }}</h1>
        <p class="text-sm text-gray-600">{{ \App\Support\SmsSegments::SEGMENTS[$campaign->segment][0] ?? $campaign->segment }} · {{ ucfirst($campaign->status) }}</p>
    </div>
    @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>@endif

    <div class="rounded-lg bg-white p-4 shadow text-sm space-y-2 sm:p-6">
        <p class="text-xs font-medium uppercase text-gray-500">How it reads (for you)</p>
        <p class="whitespace-pre-line rounded-lg bg-gray-50 p-3 font-mono text-xs">{{ $sample }}</p>
        <p class="text-xs text-gray-500">{{ $parts }} SMS part(s) per person ({{ mb_strlen($sample) }} characters; Thaana uses 70 per part).</p>
        @if($campaign->status === 'sent')
            <p>Sent {{ $campaign->sent_at?->format('j M Y H:i') }} to {{ number_format($campaign->sent_count) }} people@if($campaign->failed_count), {{ number_format($campaign->failed_count) }} not sent@endif.</p>
        @else
            <p data-campaign-recipients><strong>{{ number_format($recipients) }}</strong> people in this group allow offers by SMS (about {{ number_format($recipients * $parts) }} SMS).</p>
        @endif
        @if($budgetProblem)<p class="rounded-lg bg-amber-50 px-3 py-2 text-amber-900">{{ $budgetProblem }}</p>@endif
    </div>

    @if($campaign->isEditable())
        <form method="POST" action="{{ route('admin.sms.campaigns.test', $campaign) }}">
            @csrf
            <button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-gray-50">Send me a test</button>
        </form>
        <form method="POST" action="{{ route('admin.sms.campaigns.send', $campaign) }}" class="rounded-lg bg-white p-4 shadow text-sm space-y-3 sm:p-6">
            @csrf
            <h2 class="font-semibold">Send</h2>
            <label class="flex items-center gap-2"><input type="radio" name="when" value="now" checked> Now</label>
            <label class="flex flex-wrap items-center gap-2"><input type="radio" name="when" value="later"> At <input type="datetime-local" name="scheduled_at" class="rounded-lg border border-gray-300 px-2 py-1"> (Maldives time)</label>
            @error('scheduled_at')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
            <label class="flex items-start gap-2"><input type="checkbox" name="confirm" value="1" required class="mt-1"> <span>Yes, send this to {{ number_format($recipients) }} people</span></label>
            <button class="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">{{ $campaign->status === 'scheduled' ? 'Change time' : 'Send' }}</button>
        </form>
        @if($campaign->status === 'scheduled')
            <form method="POST" action="{{ route('admin.sms.campaigns.cancel', $campaign) }}">@csrf<button class="text-sm text-red-700 underline">Cancel the scheduled send</button></form>
        @endif
    @endif
</div>
@endsection
