{{-- Product page buy box: "Ask about this item", a private question answered by the shop that sells it (customers see
     the answer as iruali; contact details are hidden; the iruali team can read it). Not on a shop's own products. --}}
@php
    $askViewer = auth()->user();
    // Visitors may ask too (name and email or mobile); open the box again after a validation error
    $askConversation = $askViewer ? \App\Models\Conversation::where('kind', 'product')->where('customer_id', $askViewer->id)->where('product_id', $product->id)->first() : null;
@endphp
@if((int) $product->seller_id !== (int) $askViewer?->id)
    <details class="mt-3 rounded-lg border border-gray-200 px-3 py-2.5 text-sm" data-ask-about-item @if($errors->hasAny(['body', 'name', 'email', 'phone'])) open @endif>
        <summary class="flex cursor-pointer items-center gap-1.5 font-semibold text-dark"><x-icon name="chat" class="w-4 h-4 shrink-0 text-primary" />{{ __('Ask about this item') }}</summary>
        @if($askConversation)
            <a href="{{ route('messages.show', $askConversation) }}" class="mt-2 inline-flex items-center gap-1 font-semibold text-primary hover:underline" data-ask-conversation>{{ __('See your conversation') }}@if($askConversation->customer_unread_count) ({{ $askConversation->customer_unread_count }})@endif</a>
        @endif
        @php $askGuest = ! auth()->check(); @endphp
            <form method="POST" action="{{ route('messages.product.store', $product) }}" class="mt-2 space-y-2">
                @csrf
                @if($askGuest)
                    @include('messaging._guest_fields')
                @endif
                <label for="ask-body" class="sr-only">{{ __('Your question') }}</label>
                <textarea id="ask-body" name="body" rows="2" maxlength="2000" required placeholder="{{ __('e.g. Is it original? Do you have it in blue?') }}" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                @error('body')<p class="text-xs text-danger">{{ $message }}</p>@enderror
                <button class="w-full h-10 rounded-lg border border-primary text-primary font-semibold hover:bg-primary-50">{{ __('Send question') }}</button>
                <p class="text-xs text-gray-500">{{ __('Only you and iruali see this. For answers everyone can read, use Q&A below.') }}</p>
            </form>
    </details>
@endif
