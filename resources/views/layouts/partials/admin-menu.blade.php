{{-- The admin menu's groups (layouts/admin): in the sidebar and in the phone's drawer. In the drawer the groups fold
     away, with the current page's group open. Needs $navGroups (AdminNav::visible()), $inboxTotal and $where
     ("sidebar" or "drawer"; only the sidebar carries the data-nav markers). --}}
@php
    $link = function (array $item) use ($where, $inboxTotal) {
        return view('layouts.partials.admin-menu-item', ['item' => $item, 'where' => $where, 'inboxTotal' => $inboxTotal]);
    };
@endphp
<div class="{{ $where === 'drawer' ? 'space-y-1' : 'space-y-5' }}">
    @foreach($navGroups as $heading => $items)
        @if($heading === '')
            <ul class="space-y-0.5 {{ $where === 'drawer' ? 'pb-2' : '' }}">@foreach($items as $item){!! $link($item) !!}@endforeach</ul>
        @elseif($where === 'drawer')
            <details class="group" @if(collect($items)->contains('active', true)) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg px-3 py-2.5 text-xs font-semibold uppercase tracking-wider text-white/60 hover:bg-white/5 [&::-webkit-details-marker]:hidden">
                    {{ $heading }}<span class="inline-block text-base transition-transform group-open:rotate-90">›</span>
                </summary>
                <ul class="space-y-0.5 pb-2">@foreach($items as $item){!! $link($item) !!}@endforeach</ul>
            </details>
        @else
            <div>
                <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-white/50">{{ $heading }}</p>
                <ul class="space-y-0.5">@foreach($items as $item){!! $link($item) !!}@endforeach</ul>
            </div>
        @endif
    @endforeach
</div>
