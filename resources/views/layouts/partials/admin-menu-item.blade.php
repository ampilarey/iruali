{{-- One admin menu link (layouts/partials/admin-menu). Needs $item, $where and $inboxTotal. --}}
<li>
    <a href="{{ $item['url'] }}" @if($where === 'sidebar') data-nav="{{ $item['route'] }}" @endif @if($item['active']) aria-current="page" @endif
       class="flex items-center gap-3 rounded-lg px-3 {{ $where === 'drawer' ? 'py-2.5' : 'py-2' }} text-sm {{ $item['active'] ? 'bg-white/15 font-semibold text-white' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
        <x-icon :name="$item['icon']" class="w-4 h-4 shrink-0 {{ $item['active'] ? 'text-sun' : 'text-white/60' }}" />
        <span class="flex-1 truncate">{{ $item['label'] }}</span>
        @if($item['route'] === 'admin.inbox' && $inboxTotal)
            <span class="rounded-full bg-red-600 px-1.5 text-xs font-semibold text-white" {{ $where === 'sidebar' ? 'data-inbox-badge' : 'data-inbox-count' }}>{{ $inboxTotal }}</span>
        @endif
    </a>
</li>
