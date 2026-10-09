{{--
    A quote request's messages with the form to add one.
    Needs: $quote (messages loaded), $role ('customer'|'seller'|'admin'), $action (POST url).
--}}
@php
    $mine = fn ($m) => $m->sender_role === $role;
    $canPost = $quote->isOpen();
@endphp
<section id="messages" class="rounded-xl border border-gray-200 bg-white p-5 space-y-3 scroll-mt-36" data-quote-thread>
    <h2 class="font-semibold text-dark">{{ __('Messages') }}</h2>

    @if($quote->messages->isEmpty())
        <p class="text-sm text-gray-500">{{ $role === 'customer' ? __('No messages yet. Ask the shop anything about the quote here.') : __('No messages yet.') }}</p>
    @else
        <ol class="max-h-96 space-y-2 overflow-y-auto pe-1">
            @foreach($quote->messages as $m)
                @php $m->setRelation('quoteRequest', $quote); @endphp
                <li class="flex {{ $mine($m) ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[85%] rounded-xl px-3 py-2 text-sm {{ $mine($m) ? 'bg-primary text-white' : ($m->sender_role === 'admin' ? 'bg-sun-soft text-sun-ink' : 'bg-gray-100 text-gray-900') }}">
                        <p class="text-[11px] font-semibold {{ $mine($m) ? 'text-white/80' : 'opacity-70' }}">
                            {{ $m->senderName() }} · <time datetime="{{ $m->created_at->toIso8601String() }}">{{ $m->created_at->translatedFormat('j M, H:i') }}</time>
                        </p>
                        <p class="whitespace-pre-line break-words" dir="auto">{{ $m->body }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    @if($canPost)
        <form method="POST" action="{{ $action }}" class="space-y-2">
            @csrf
            <label for="quote-message-body" class="sr-only">{{ __('Your message') }}</label>
            <textarea id="quote-message-body" name="body" rows="2" maxlength="{{ \App\Services\QuoteService::MESSAGE_MAX_LENGTH }}" required placeholder="{{ __('Write a short message…') }}" class="w-full rounded-lg border-gray-300 text-sm">{{ $errors->quoteMessage->any() ? old('body') : '' }}</textarea>
            @if($errors->quoteMessage->has('body'))<p class="text-sm text-danger">{{ $errors->quoteMessage->first('body') }}</p>@endif
            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-primary px-4 py-1.5 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Send') }}</button>
            </div>
        </form>
    @else
        <p class="text-xs text-gray-500">{{ __('This request is closed, so no more messages can be added.') }}</p>
    @endif
</section>
