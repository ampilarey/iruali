@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
{{-- Compact on a phone: what needs doing first, the numbers as small tiles, shortcuts as icons,
     the latest orders and users as short lists, and the system checks folded away (the badge says if something fails). --}}
@php
    $can = fn ($route) => \App\Support\StaffAccess::can($route);
    $inboxItems = array_filter(\App\Support\AdminInbox::items(), fn ($i) => $i['count'] > 0);
@endphp
<div class="max-w-7xl mx-auto px-3 py-4 sm:px-6 lg:px-8 lg:py-6 space-y-4 lg:space-y-6">
    <div class="hidden lg:flex flex-wrap items-baseline justify-between gap-x-3">
        <h1 class="text-xl font-bold text-gray-900 lg:text-2xl">Dashboard</h1>
        <span class="text-sm text-gray-500">Welcome, {{ Auth::user()->name }}</span>
    </div>

    <!-- Needs attention: the admin inbox rows this person may open (App\Support\AdminInbox) -->
    @if($inboxItems)
    <section class="bg-white shadow-sm rounded-xl p-3 sm:p-4" id="needs-attention">
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-semibold text-gray-900">Needs attention</h2>
            <a href="{{ route('admin.inbox') }}" class="text-sm font-medium text-primary-700 hover:underline">Open inbox</a>
        </div>
        <ul class="flex flex-wrap gap-1.5 text-sm">
            @foreach($inboxItems as $item)
                @php $tone = ['danger' => 'bg-red-100 text-red-800', 'warn' => 'bg-amber-100 text-amber-800', 'info' => 'bg-blue-100 text-blue-800'][$item['severity']]; @endphp
                <li><a href="{{ $item['url'] }}" class="inline-flex items-center gap-1.5 rounded-full bg-gray-50 border border-gray-200 px-2.5 py-1 hover:bg-gray-100" data-inbox="{{ $item['key'] }}">{{ $item['label'] }} <span class="rounded-full px-1.5 text-xs font-semibold {{ $tone }}">{{ $item['count'] }}</span></a></li>
            @endforeach
        </ul>
    </section>
    @endif

    <!-- The numbers -->
    @php
        $tiles = [
            ['Orders', $stats['total_orders'] ?? 0, 'admin.orders', 'box', 'text-purple-600 bg-purple-50'],
            ['Products', $stats['total_products'] ?? 0, 'admin.products', 'grid', 'text-green-600 bg-green-50'],
            ['Users', $stats['total_users'] ?? 0, 'admin.users', 'user', 'text-blue-600 bg-blue-50'],
            ['Sellers', $stats['total_sellers'] ?? 0, 'admin.sellers', 'store', 'text-amber-600 bg-amber-50'],
            ['Shops waiting', $stats['pending_sellers'] ?? 0, 'admin.sellers', 'clock', 'text-orange-600 bg-orange-50'],
            ['Products off', $stats['pending_products'] ?? 0, 'admin.products', 'list', 'text-red-600 bg-red-50'],
        ];
    @endphp
    <dl class="grid grid-cols-3 gap-2 sm:gap-3 lg:grid-cols-6">
        @foreach($tiles as [$label, $value, $route, $icon, $tone])
            @php $tag = Route::has($route) && $can($route) ? 'a' : 'div'; @endphp
            <{{ $tag }} @if($tag === 'a') href="{{ route($route) }}" @endif class="flex flex-col gap-1 rounded-xl bg-white p-2 shadow-sm sm:p-3 {{ $tag === 'a' ? 'hover:ring-1 hover:ring-gray-200' : '' }}">
                <span class="flex items-center gap-2">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $tone }}"><x-icon :name="$icon" class="w-4 h-4" /></span>
                    <dd class="text-lg font-bold leading-tight text-gray-900 sm:text-xl">{{ number_format($value) }}</dd>
                </span>
                <dt class="truncate text-xs text-gray-500">{{ $label }}</dt>
            </{{ $tag }}>
        @endforeach
    </dl>

    <!-- Shortcuts (the menu has every page) -->
    @php
        $badge = fn ($n, $word) => $n > 0 ? $n.' '.$word : null;
        $shortcuts = [];
        if ($can('admin.returns')) {
            $openReturns = \App\Models\ReturnRequest::whereIn('status', ['requested', 'approved'])->count() + \App\Models\Order::where('refund_status', 'due')->count();
            $shortcuts[] = ['admin.returns', 'Returns', 'return', $badge($openReturns, 'open')];
        }
        if ($can('admin.disputes')) {
            $shortcuts[] = ['admin.disputes', 'Disputes', 'shield', $badge(\App\Models\Dispute::open()->count(), 'open')];
            $shortcuts[] = ['admin.messages', 'Messages', 'chat', $badge($messageBadges['admin'] ?? 0, 'unread')];
        }
        if ($can('admin.reviews')) {
            $shortcuts[] = ['admin.reviews', 'Reviews', 'thumb', $badge(\App\Models\ProductQuestion::whereNull('answer')->count(), 'open')];
        }
        foreach ([
            ['admin.payouts', 'Payouts', 'bank'], ['admin.vouchers.index', 'Vouchers', 'tag'], ['admin.campaigns.index', 'Campaigns', 'squares'],
            ['admin.analytics', 'Analytics', 'columns'], ['admin.settings', 'Settings', 'filter'],
        ] as [$route, $label, $icon]) {
            if ($can($route)) {
                $shortcuts[] = [$route, $label, $icon, null];
            }
        }
        if ($can('admin.errors')) {
            $shortcuts[] = ['admin.errors', 'Errors', 'shield', $badge(\App\Models\ErrorEvent::unresolved()->count(), 'open')];
        }
        $shortcuts = array_filter($shortcuts, fn ($s) => Route::has($s[0]));
    @endphp
    @if($shortcuts)
    <section class="bg-white shadow-sm rounded-xl p-3 sm:p-4" id="shortcuts">
        <h2 class="text-sm font-semibold text-gray-900 mb-2">Shortcuts</h2>
        <ul class="grid grid-cols-4 gap-1 sm:grid-cols-5 lg:grid-cols-10">
            @foreach($shortcuts as [$route, $label, $icon, $count])
                <li>
                    <a href="{{ route($route) }}" class="relative flex flex-col items-center gap-1 rounded-lg px-1 py-2 text-center text-xs font-medium text-gray-700 hover:bg-gray-50">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-700"><x-icon :name="$icon" class="w-5 h-5" /></span>
                        <span class="w-full truncate">{{ $label }}</span>
                        @if($count)<span class="rounded-full bg-red-600 px-1.5 text-[10px] font-semibold leading-4 text-white">{{ $count }}</span>@endif
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
    @endif

    <!-- Latest orders and users -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-6">
        <section class="bg-white shadow-sm rounded-xl">
            <div class="flex items-center justify-between px-3 pt-3 sm:px-4">
                <h2 class="text-sm font-semibold text-gray-900">Recent orders</h2>
                @if($can('admin.orders'))<a href="{{ route('admin.orders') }}" class="text-sm font-medium text-primary-700 hover:underline">All</a>@endif
            </div>
            <ul class="divide-y divide-gray-100 px-3 pb-1 sm:px-4">
                @forelse($recent_orders ?? [] as $order)
                    @php $orderUrl = $can('admin.orders.show') ? route('admin.orders.show', $order) : null; @endphp
                    <li>
                        <{{ $orderUrl ? 'a' : 'div' }} @if($orderUrl) href="{{ $orderUrl }}" @endif class="flex items-center gap-3 py-2 text-sm">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-gray-900">{{ $order->customerName() ?? 'Deleted user' }}@if($order->isGuest()) <span class="text-xs text-amber-700 font-medium">(Guest)</span>@endif</span>
                                <span class="block truncate text-xs text-gray-500">#{{ $order->id }} · {{ $order->created_at->diffForHumans() }}</span>
                            </span>
                            <span class="shrink-0 font-semibold text-gray-900">{{ \App\Support\Money::format($order->total_amount ?? 0) }}</span>
                        </{{ $orderUrl ? 'a' : 'div' }}>
                    </li>
                @empty
                    <li class="py-4 text-center text-sm text-gray-500">No recent orders</li>
                @endforelse
            </ul>
        </section>

        <section class="bg-white shadow-sm rounded-xl">
            <div class="flex items-center justify-between px-3 pt-3 sm:px-4">
                <h2 class="text-sm font-semibold text-gray-900">Recent users</h2>
                @if($can('admin.users'))<a href="{{ route('admin.users') }}" class="text-sm font-medium text-primary-700 hover:underline">All</a>@endif
            </div>
            <ul class="divide-y divide-gray-100 px-3 pb-1 sm:px-4">
                @forelse($recent_users ?? [] as $user)
                    <li class="flex items-center gap-3 py-2 text-sm">
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-700">{{ mb_substr($user->name, 0, 1) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-gray-900">{{ $user->name }}</span>
                            <span class="block truncate text-xs text-gray-500">{{ $user->email }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-gray-500">{{ $user->created_at->diffForHumans(null, true) }}</span>
                    </li>
                @empty
                    <li class="py-4 text-center text-sm text-gray-500">No recent users</li>
                @endforelse
            </ul>
        </section>
    </div>

    @if(! empty($launchChecks))
    <!-- Launch checklist (App\Support\LaunchChecks): the business side of going live; open while anything is red -->
    @php
        $launchCounts = \App\Support\LaunchChecks::counts($launchChecks);
        $launchRank = ['fail' => 0, 'warn' => 1, 'pass' => 2];
        $sortedLaunch = collect($launchChecks)->sortBy(fn ($c) => $launchRank[$c['status']] ?? 9)->values();
    @endphp
    <details class="group bg-white shadow-sm rounded-xl" id="launch-checklist" data-launch-checklist @if($launchCounts['fail'] > 0) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 p-3 sm:p-4 [&::-webkit-details-marker]:hidden">
            <h2 class="text-sm font-semibold text-gray-900">Launch checklist</h2>
            <span class="flex items-center gap-2">
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $launchCounts['fail'] ? 'bg-red-100 text-red-800' : ($launchCounts['warn'] ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800') }}">
                    {{ $launchCounts['fail'] ? $launchCounts['fail'].' to do before launch' : ($launchCounts['warn'] ? 'Ready, '.$launchCounts['warn'].' to look at' : 'Ready to launch') }}
                </span>
                <x-icon name="chevron-down" class="w-4 h-4 text-gray-400 transition-transform group-open:rotate-180" />
            </span>
        </summary>
        <div class="border-t border-gray-100 px-3 pb-3 sm:px-4">
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach($sortedLaunch as $check)
                    @php $tone = ['pass' => 'bg-green-500', 'warn' => 'bg-amber-500', 'fail' => 'bg-red-500'][$check['status']] ?? 'bg-gray-300'; @endphp
                    <li class="flex items-start gap-2.5 py-2" data-launch-check="{{ $check['status'] }}">
                        <span class="mt-1.5 inline-block h-2 w-2 shrink-0 rounded-full {{ $tone }}" title="{{ $check['status'] }}"></span>
                        <div class="min-w-0 flex-1">
                            <span class="font-medium text-gray-900">{{ $check['name'] }}</span>
                            <span class="text-gray-600">— {{ $check['detail'] }}</span>
                            @if($check['status'] !== 'pass' && $check['fix'])
                                <p class="text-xs text-gray-500">{{ $check['fix'] }}@if($check['link']) <a href="{{ $check['link'] }}" class="font-medium text-primary-600 hover:underline">Fix</a>@endif</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-gray-400">Checked each time this page opens. The technical checks are under System status below.</p>
        </div>
    </details>
    @endif

    @if(! empty($readyChecks))
    <!-- System status: the same checks as `php artisan iruali:ready` (offline, cached for 5 minutes); folded, problems listed first -->
    @php
        $readyFailures = \App\Support\ReadyChecks::failures($readyChecks);
        $rank = ['fail' => 0, 'warn' => 1, 'skip' => 2, 'pass' => 3];
        $sortedChecks = collect($readyChecks)->sortBy(fn ($c) => $rank[$c['status']] ?? 9)->values();
    @endphp
    <details class="group bg-white shadow-sm rounded-xl" id="system-status">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 p-3 sm:p-4 [&::-webkit-details-marker]:hidden">
            <h2 class="text-sm font-semibold text-gray-900">System status</h2>
            <span class="flex items-center gap-2">
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $readyFailures ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                    {{ $readyFailures ? 'Not ready: '.$readyFailures.' problem(s)' : 'Ready' }}
                </span>
                <x-icon name="chevron-down" class="w-4 h-4 text-gray-400 transition-transform group-open:rotate-180" />
            </span>
        </summary>
        <div class="border-t border-gray-100 px-3 pb-3 sm:px-4">
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach($sortedChecks as $check)
                    @php $tone = ['pass' => 'bg-green-500', 'warn' => 'bg-amber-500', 'fail' => 'bg-red-500', 'skip' => 'bg-gray-300'][$check['status']] ?? 'bg-gray-300'; @endphp
                    <li class="flex items-start gap-2.5 py-2" data-status="{{ $check['status'] }}">
                        <span class="mt-1.5 inline-block h-2 w-2 shrink-0 rounded-full {{ $tone }}" title="{{ $check['status'] }}"></span>
                        <div class="min-w-0 flex-1">
                            <span class="font-medium text-gray-900">{{ $check['name'] }}</span>
                            <span class="text-gray-600">— {{ $check['detail'] }}</span>
                            @if($check['status'] !== 'pass' && $check['fix'])
                                <p class="text-xs text-gray-500">{{ $check['fix'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-gray-400">Refreshes every 5 minutes. Network checks run from the server with <code>php artisan iruali:ready</code>.</p>
        </div>
    </details>
    @endif
</div>
@endsection
