{{-- Cart: quoted lines taken out since the customer last looked (their quote expired or was closed), shown once (QuoteService::pruneCart). --}}
@php $quoteNotices = app(\App\Services\QuoteService::class)->pullNotices(); @endphp
@if($quoteNotices !== [])
    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 space-y-1" role="alert" data-quote-notices>
        @foreach($quoteNotices as $notice)
            <p class="flex items-start gap-2"><x-icon name="clock" class="w-5 h-5 shrink-0" /><span>{{ $notice }}</span></p>
        @endforeach
    </div>
@endif
