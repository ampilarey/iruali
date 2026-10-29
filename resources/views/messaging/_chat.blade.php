{{--
    One conversation with its reply form (item questions, iruali support, item requests; order threads use _thread).
    Needs: $conversation (Conversation, may be new), $role ('customer'|'seller'|'admin'), $action (POST url).
    Optional: $guestForm (a visitor's first message: name and email or mobile, MessagesController::postAsGuest()).
    The team ($role admin) also sees what was written where contact details were hidden, and reports.
--}}
@php
    $messages = $conversation->exists ? $conversation->messages : collect();
    $canReply = ! $conversation->exists || $conversation->isOpen() || $role === 'admin';
    $mine = fn ($m) => $m->sender_role === $role;
@endphp
<div id="messages" class="rounded-xl border border-gray-200 bg-white p-4 space-y-3 scroll-mt-36" data-chat="{{ $conversation->id ?? 'new' }}">
    @if($messages->isEmpty())
        @if($conversation->exists)<p class="text-sm text-gray-500">{{ __('No messages yet.') }}</p>@endif
    @else
        <ol class="space-y-2 max-h-[32rem] overflow-y-auto pe-1">
            @foreach($messages as $m)
                <li class="flex {{ $mine($m) ? 'justify-end' : 'justify-start' }}" data-chat-message="{{ $m->id }}">
                    <div class="max-w-[85%] rounded-xl px-3 py-2 text-sm {{ $mine($m) ? 'bg-primary text-white' : ($m->sender_role === 'admin' ? 'bg-sun-soft text-sun-ink' : 'bg-gray-100 text-gray-900') }}">
                        <p class="text-[11px] font-semibold {{ $mine($m) ? 'text-white/80' : 'opacity-70' }}">
                            {{ $m->senderName($role) }} · <time datetime="{{ $m->created_at->toIso8601String() }}">{{ $m->created_at->translatedFormat('j M, H:i') }}</time>
                        </p>
                        <p class="whitespace-pre-line break-words">{{ $m->body }}</p>
                        @if($role === 'admin' && $m->wasMasked())
                            <p class="mt-1 rounded bg-white/70 px-2 py-1 text-xs text-gray-700" data-chat-original>Written (contact details hidden from the other side): {{ $m->original_body }}</p>
                        @endif
                        @if($m->attachment_path)
                            <a href="{{ route('messages.attachment', $m) }}" target="_blank" rel="noopener" class="mt-1 block"><img src="{{ route('messages.attachment', $m) }}" alt="{{ __('Attached photo') }}" class="max-h-40 rounded-lg border border-white/30"></a>
                        @endif
                        @if($m->reported_at)
                            <p class="mt-1 text-[11px] font-semibold text-danger" data-chat-reported>{{ __('Reported') }}@if($role === 'admin'): {{ $m->report_reason }} ({{ $m->reportedBy?->name }})@endif</p>
                        @elseif(! $mine($m) && $role !== 'admin' && auth()->check())
                            <details class="mt-1 text-[11px]">
                                <summary class="cursor-pointer opacity-70 hover:underline">{{ __('Report') }}</summary>
                                <form method="POST" action="{{ route('messages.report', $m) }}" class="mt-1 flex gap-1">
                                    @csrf
                                    <input name="reason" required maxlength="500" placeholder="{{ __('What is wrong?') }}" class="min-w-0 flex-1 rounded border-gray-300 px-2 py-1 text-xs text-gray-900">
                                    <button class="rounded bg-white px-2 py-1 text-xs font-semibold text-danger">{{ __('Send') }}</button>
                                </form>
                            </details>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    @if($canReply)
        @php $field = 'block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-dark placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20'; @endphp
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-4 {{ $messages->isNotEmpty() ? 'border-t border-gray-100 pt-4' : '' }}">
            @csrf
            @if($guestForm ?? false)
                @include('messaging._guest_fields')
            @endif
            <div>
                <label for="chat-body" class="block text-sm font-semibold text-dark {{ ($guestForm ?? false) ? '' : 'sr-only' }}">{{ __('Your message') }}</label>
                <textarea id="chat-body" name="body" rows="4" maxlength="2000" required placeholder="{{ __('Write a message…') }}" class="mt-1 {{ $field }} {{ $errors->has('body') ? 'border-danger' : '' }}">{{ old('body') }}</textarea>
                @error('body')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <label class="text-xs text-gray-600">
                    <span class="sr-only">{{ __('Photo (optional)') }}</span>
                    <input type="file" name="attachment" accept="image/jpeg,image/png,image/webp,image/gif" class="block w-full text-xs text-gray-600 file:me-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-primary hover:file:bg-primary-100">
                    <span class="mt-1 block text-gray-500">{{ __('Photo (optional)') }}</span>
                </label>
                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover sm:w-auto" data-chat-send><x-icon name="chat" class="h-4 w-4" />{{ __('Send') }}</button>
            </div>
            @error('attachment')<p class="text-xs text-danger">{{ $message }}</p>@enderror
            <div class="space-y-1 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">
                @if($conversation->hidesContactDetails() && $role !== 'admin')
                    <p>{{ __('Phone numbers, emails and links are hidden in these messages: please keep everything on iruali, where your order is protected.') }}</p>
                @endif
                <p>{{ __('Messages are kept and can be read by the iruali team.') }}</p>
            </div>
        </form>
    @else
        <p class="text-xs text-gray-500">{{ __('This conversation is closed.') }}</p>
    @endif
</div>
