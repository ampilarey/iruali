{{-- Newsletters written and sent from here (admins; the support role only sees the subscriber list) --}}
<section class="mb-8" aria-labelledby="newsletter-issues">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <h2 id="newsletter-issues" class="text-lg font-semibold text-gray-900">Newsletters</h2>
        <a href="{{ route('admin.newsletter-issues.create') }}" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-semibold hover:bg-primary-700">Write a newsletter</a>
    </div>
    <div class="bg-white shadow rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr><th class="text-start px-4 py-2 font-medium">Subject</th><th class="text-start px-4 py-2 font-medium">Status</th><th class="text-end px-4 py-2 font-medium">Sent</th><th class="text-start px-4 py-2 font-medium">Date</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($issues as $issue)
                    <tr>
                        <td class="px-4 py-2"><a href="{{ route('admin.newsletter-issues.show', $issue) }}" class="font-medium text-primary-700 hover:underline">{{ $issue->subject_en }}</a></td>
                        <td class="px-4 py-2">{{ ucfirst($issue->status) }}</td>
                        <td class="px-4 py-2 text-end tabular-nums">{{ $issue->isDraft() ? '–' : number_format($issue->sent_count).' / '.number_format($issue->recipients_count) }}@if($issue->failed_count) <span class="text-red-700">({{ $issue->failed_count }} failed)</span>@endif</td>
                        <td class="px-4 py-2 text-gray-500">{{ ($issue->queued_at ?? $issue->created_at)?->format('j M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No newsletters yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
