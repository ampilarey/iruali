{{--
    One conversation with its reply form (item questions, iruali support, item requests; order threads use _thread).
    Needs: $conversation (Conversation, may be new), $role ('customer'|'seller'|'admin'), $action (POST url).
    The team ($role admin) also sees what was written where contact details were hidden, and reports.
--}}
@php
    $messages = $conversation->exists ? $conversation->messages : collect();
    $canReply = ! $conversation->exists || $conversation->isOpen() || $role === 'admin';
    $mine = fn ($m) => $m->sender_role === $role;
@endphp
<div id="messages" class="rounded-xl border border-gray-200 bg-white p-4 space-y-3 scroll-mt-36" data-chat="{{ $conversation->id ?? 'new' }}">
    @if($messages->isEmpty())
        <p class="text-sm text-gray-500">{{ __('No messages yet.') }}</p>
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
                        @elseif(! $mine($m) && $role !== 'admin')
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
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-2">
            @csrf
            <label for="chat-body" class="sr-only">{{ __('Your message') }}</label>
            <textarea id="chat-body" name="body" rows="3" maxlength="2000" required placeholder="{{ __('Write a message…') }}" class="w-full rounded-lg border-gray-300 text-sm">{{ old('body') }}</textarea>
            @error('body')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            @error('attachment')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            <div class="flex flex-wrap items-center justify-between gap-2">
                <label class="text-xs text-gray-600">{{ __('Photo (optional)') }} <input type="file" name="attachment" accept="image/jpeg,image/png,image/webp,image/gif" class="text-xs"></label>
                <button type="submit" class="rounded-lg bg-primary px-4 py-1.5 text-sm font-semibold text-white hover:bg-primary-hover" data-chat-send>{{ __('Send') }}</button>
            </div>
            @if($conversation->hidesContactDetails() && $role !== 'admin')
                <p class="text-xs text-gray-500">{{ __('Phone numbers, emails and links are hidden in these messages: please keep everything on iruali, where your order is protected.') }}</p>
            @endif
            <p class="text-xs text-gray-500">{{ __('Messages are kept and can be read by the iruali team.') }}</p>
        </form>
    @else
        <p class="text-xs text-gray-500">{{ __('This conversation is closed.') }}</p>
    @endif
</div>
