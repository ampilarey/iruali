{{--
    One order thread with its reply form.
    Needs: $part (SellerOrder), $conversation (Conversation|null), $role ('customer'|'seller'|'admin'),
           $action (POST url), $canReply (bool), $closedNote (string|null shown instead of the form).
--}}
@php
    $messages = $conversation?->messages ?? collect();
    $bag = 'conversation-'.($conversation?->id ?? 'new');
    $anchor = 'conversation-'.($conversation?->id ?? 'part-'.$part->id);
    $mine = fn ($m) => $m->sender_role === $role;
@endphp
<div id="{{ $anchor }}" class="rounded-lg border border-gray-200 bg-white p-4 space-y-3 scroll-mt-36">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-gray-900">
            @if($role === 'customer'){{ __('Message the shop') }}@elseif($role === 'seller'){{ __('Messages with the customer') }}@else{{ __('Conversation') }}: {{ $part->shopName() }} · {{ $conversation?->customer?->name ?? __('Customer') }}@endif
        </h3>
        @if($conversation && ! $conversation->isOpen())<span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ __('Closed') }}</span>@endif
    </div>

    @if($messages->isEmpty())
        <p class="text-sm text-gray-500">{{ $role === 'customer' ? __('No messages yet. Ask the shop about your order here; iruali support can see the thread too.') : __('No messages yet.') }}</p>
    @else
        <ol class="space-y-2 max-h-96 overflow-y-auto pe-1">
            @foreach($messages as $m)
                <li class="flex {{ $mine($m) ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[85%] rounded-xl px-3 py-2 text-sm {{ $mine($m) ? 'bg-primary text-white' : ($m->sender_role === 'admin' ? 'bg-sun-soft text-sun-ink' : 'bg-gray-100 text-gray-900') }}">
                        <p class="text-[11px] font-semibold {{ $mine($m) ? 'text-white/80' : 'opacity-70' }}">
                            {{ $m->senderName() }}
                            @if($m->sender_role === 'admin')<span class="ms-1 rounded-full bg-sun px-1.5 text-[10px] text-sun-on">{{ __('iruali support') }}</span>@endif
                            · <time datetime="{{ $m->created_at->toIso8601String() }}">{{ $m->created_at->translatedFormat('j M, H:i') }}</time>
                        </p>
                        <p class="whitespace-pre-line break-words">{{ $m->body }}</p>
                        @if($m->attachment_path)
                            <a href="{{ route('messages.attachment', $m) }}" target="_blank" rel="noopener" class="mt-1 block"><img src="{{ route('messages.attachment', $m) }}" alt="{{ __('Attached photo') }}" class="max-h-40 rounded-lg border border-white/30"></a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    @if($canReply)
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-2">
            @csrf
            <label for="body-{{ $anchor }}" class="sr-only">{{ __('Your message') }}</label>
            <textarea id="body-{{ $anchor }}" name="body" rows="2" maxlength="2000" required placeholder="{{ __('Write a message…') }}" class="w-full rounded-lg border-gray-300 text-sm">{{ $errors->{$bag}->any() ? old('body') : '' }}</textarea>
            @if($errors->{$bag}->has('body'))<p class="text-sm text-danger">{{ $errors->{$bag}->first('body') }}</p>@endif
            @error('body')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            @error('attachment')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            <div class="flex flex-wrap items-center justify-between gap-2">
                <label class="text-xs text-gray-600">{{ __('Photo (optional)') }} <input type="file" name="attachment" accept="image/jpeg,image/png,image/webp,image/gif" class="text-xs"></label>
                <button type="submit" class="rounded-lg bg-primary px-4 py-1.5 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Send') }}</button>
            </div>
        </form>
    @elseif($closedNote)
        <p class="text-xs text-gray-500">{{ $closedNote }}</p>
    @endif
</div>
