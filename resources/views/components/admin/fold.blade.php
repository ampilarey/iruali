{{-- An admin page section folded to its heading (flush: no padding around the content, for lists and tables; body: classes for the content box): tap the heading to open it. Opens by itself when
     the form came back with errors (and, in the admin layout's script, when a field inside it is
     invalid or a link points into it); the layout remembers which ones were left open on this page. --}}
@props(['title', 'open' => false, 'id' => null, 'flush' => false, 'body' => ''])
@php
    $foldId = $id ?? \Illuminate\Support\Str::slug(html_entity_decode(strip_tags((string) $title)));
    $isOpen = $open || (isset($errors) && $errors->any());
@endphp
<details @if($id) id="{{ $id }}" @endif {{ $attributes->class(['group rounded-lg bg-white shadow']) }} data-fold="{{ $foldId }}" @if($isOpen) open @endif>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-4 py-3 hover:bg-gray-50 group-open:rounded-b-none sm:px-6 sm:py-4 [&::-webkit-details-marker]:hidden">
        <h2 class="min-w-0 text-base font-semibold text-gray-900 sm:text-lg">{{ $title }}</h2>
        <span class="flex shrink-0 items-center gap-2">
            @isset($badge){{ $badge }}@endisset
            <x-icon name="chevron-down" class="h-5 w-5 text-gray-400 transition-transform group-open:rotate-180" />
        </span>
    </summary>
    <div @class(['border-t border-gray-100', 'px-4 py-4 sm:px-6 [&>:first-child]:mt-0' => ! $flush, $body])>
        {{ $slot }}
    </div>
</details>
