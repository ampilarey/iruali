@extends('layouts.app')

@php
    $previewLang = request()->query('lang') === 'dv' ? 'dv' : 'en';
    $badge = ['draft' => 'bg-gray-100 text-gray-700', 'sending' => 'bg-amber-100 text-amber-800', 'sent' => 'bg-green-100 text-green-800'][$issue->status] ?? 'bg-gray-100 text-gray-700';
    $sections = array_filter([
        $issue->newArrivalDays() ? 'New arrivals from the last '.$issue->newArrivalDays().' days' : null,
        $issue->wantsDeals() ? 'Current deals' : null,
        $issue->campaignId() ? 'Campaign: '.($campaign?->name ?? 'removed') : null,
        $issue->wantsBrands() ? 'Featured brands' : null,
    ]);
@endphp

@section('title', 'Newsletter: '.$issue->subject_en)

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.moderation._header', ['title' => 'Newsletter'])

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <section class="rounded-lg bg-white p-6 shadow">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Newsletter #{{ $issue->id }}</p>
                    <h2 class="text-xl font-semibold text-gray-900 break-words">{{ $issue->subject_en }}</h2>
                    @if($issue->subject_dv)<p class="text-gray-700" dir="rtl">{{ $issue->subject_dv }}</p>@endif
                    <p class="mt-1 text-sm text-gray-500">Sections: {{ $sections ? implode(', ', $sections) : 'none (text only)' }}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $badge }}" data-issue-status>{{ ucfirst($issue->status) }}</span>
            </div>

            @if($issue->isDraft())
                <div class="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700" data-audience>
                    It would go to <strong>{{ number_format($audience['total']) }}</strong> {{ \Illuminate\Support\Str::plural('address', $audience['total']) }}:
                    {{ number_format($audience['customers']) }} {{ \Illuminate\Support\Str::plural('customer', $audience['customers']) }} who accept marketing emails and
                    {{ number_format($audience['subscribers']) }} other confirmed {{ \Illuminate\Support\Str::plural('subscriber', $audience['subscribers']) }}. Each address gets it once, in its own language, with a one-click unsubscribe link.
                    @unless($issue->hasDhivehi())<span class="block mt-1 text-amber-800">Add the Dhivehi subject and text before sending.</span>@endunless
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <a href="{{ route('admin.newsletter-issues.edit', $issue) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</a>

                    <form method="POST" action="{{ route('admin.newsletter-issues.test', $issue) }}" class="flex items-center gap-2">
                        @csrf
                        <label for="test-lang" class="sr-only">Language of the test</label>
                        <select id="test-lang" name="lang" class="rounded-lg border border-gray-300 px-2 py-2 text-sm">
                            <option value="en" @selected($previewLang === 'en')>English</option>
                            <option value="dv" @selected($previewLang === 'dv')>Dhivehi</option>
                        </select>
                        <button type="submit" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Send me a test</button>
                    </form>
                    @if($issue->test_sent_at)<span class="text-xs text-gray-500">Last test {{ $issue->test_sent_at->diffForHumans() }}</span>@endif

                    <form method="POST" action="{{ route('admin.newsletter-issues.send', $issue) }}" class="ms-auto" onsubmit="return confirm('Send this newsletter to {{ number_format($audience['total']) }} addresses now? This cannot be undone.')">
                        @csrf
                        <button type="submit" class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50" @disabled(! $issue->hasDhivehi() || $audience['total'] === 0)>Send</button>
                    </form>
                    <form method="POST" action="{{ route('admin.newsletter-issues.destroy', $issue) }}" onsubmit="return confirm('Delete this draft?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-sm text-red-700 hover:underline">Delete draft</button>
                    </form>
                </div>
            @else
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-5" data-delivery-counts>
                    <div class="rounded-lg bg-gray-50 p-3"><dt class="text-gray-500">Addresses</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($issue->recipients_count) }}</dd></div>
                    <div class="rounded-lg bg-gray-50 p-3"><dt class="text-gray-500">Sent</dt><dd class="text-lg font-semibold tabular-nums text-green-700">{{ number_format($counts['sent']) }}</dd></div>
                    <div class="rounded-lg bg-gray-50 p-3"><dt class="text-gray-500">Waiting</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($counts['pending'] + $counts['sending']) }}</dd></div>
                    <div class="rounded-lg bg-gray-50 p-3"><dt class="text-gray-500">Failed</dt><dd class="text-lg font-semibold tabular-nums {{ $counts['failed'] ? 'text-red-700' : '' }}">{{ number_format($counts['failed']) }}</dd></div>
                    <div class="rounded-lg bg-gray-50 p-3"><dt class="text-gray-500">Skipped</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($counts['skipped']) }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-gray-500">
                    Sent by {{ $issue->sender?->name ?? 'someone' }}{{ $issue->queued_at ? ' on '.$issue->queued_at->format('j M Y, H:i') : '' }}@if($issue->sent_at); finished {{ $issue->sent_at->format('j M Y, H:i') }}@endif.
                    Skipped addresses unsubscribed after the send started. Each address gets an issue at most once.
                </p>
                @if($counts['failed'] > 0)
                    <form method="POST" action="{{ route('admin.newsletter-issues.retry', $issue) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Try the failed ones again</button>
                    </form>
                @endif
            @endif
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-gray-900">Preview</h2>
                <nav class="flex gap-1 text-sm" aria-label="Preview language">
                    @foreach(['en' => 'English', 'dv' => 'Dhivehi'] as $lang => $label)
                        <a href="{{ route('admin.newsletter-issues.show', ['issue' => $issue, 'lang' => $lang]) }}" class="rounded-lg px-3 py-1.5 {{ $previewLang === $lang ? 'bg-primary-50 font-semibold text-primary-700' : 'text-gray-600 hover:bg-gray-50' }}" @if($previewLang === $lang) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
            <p class="mt-1 text-xs text-gray-500">{{ $issue->isDraft() ? 'Products are picked again when you press Send, so they may change a little.' : 'The products as sent; prices are today\'s.' }}</p>
            <iframe src="{{ route('admin.newsletter-issues.preview', ['issue' => $issue, 'lang' => $previewLang]) }}" title="Newsletter preview" sandbox="allow-popups allow-popups-to-escape-sandbox" class="mt-4 h-[48rem] w-full rounded-lg border border-gray-200 bg-white" loading="lazy"></iframe>
        </section>
    </div>
</div>
@endsection
