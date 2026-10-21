<!DOCTYPE html>
{{-- The admin area's own layout: a grouped sidebar on a computer; on a phone a top bar with the menu
     (a drawer with the same groups) and a bottom bar for the pages used most. Only the pages the
     staff member may open are listed (App\Support\AdminNav, StaffAccess). Admin pages are English. --}}
@php
    use App\Support\AdminNav;
    use App\Support\StaffAccess;
    $navGroups = AdminNav::visible();
    $navFlat = collect($navGroups)->flatten(1)->keyBy('route');
    $inboxTotal = (Route::has('admin.inbox') && StaffAccess::can('admin.inbox')) ? \App\Support\AdminInbox::total() : 0;
    $currentLabel = AdminNav::currentLabel() ?? 'Admin';
    $staff = auth()->user();
@endphp
<html lang="en" dir="ltr" class="h-full bg-gray-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0F2A3A">
    <link rel="icon" href="/images/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title>{{ trim($__env->yieldContent('title')) ?: $currentLabel }} · iruali admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&family=Noto+Sans+Thaana:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full font-sans antialiased text-dark" data-admin-layout>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:start-2 focus:z-[100] focus:bg-white focus:px-4 focus:py-2 focus:rounded-lg focus:shadow">Skip to content</a>

    {{-- The menu: the sidebar on a computer, and the phone's drawer --}}
    @php
        $menu = function (string $where) use ($navGroups, $inboxTotal) {
            return view('layouts.partials.admin-menu', ['navGroups' => $navGroups, 'inboxTotal' => $inboxTotal, 'where' => $where]);
        };
    @endphp
    <aside class="hidden lg:fixed lg:inset-y-0 lg:start-0 lg:z-40 lg:flex lg:w-64 lg:flex-col bg-reef text-white" aria-label="Admin menu">
        <a href="{{ route('admin.dashboard') }}" class="flex h-16 shrink-0 items-center gap-2 px-5 border-b border-white/10">
            <img src="/images/brand/iruali-logo-reversed.svg" alt="iruali" class="h-7 w-auto">
            <span class="rounded bg-white/10 px-1.5 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-white/80">Admin</span>
        </a>
        <div class="flex-1 overflow-y-auto px-3 py-4">{!! $menu('sidebar') !!}</div>
        <div class="border-t border-white/10 p-3 space-y-1 text-sm">
            <a href="{{ route('home') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-white/80 hover:bg-white/10 hover:text-white"><x-icon name="store" class="w-4 h-4" />View the shop</a>
            <a href="{{ route('account') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-white/80 hover:bg-white/10 hover:text-white" data-admin-account-link><x-icon name="user" class="w-4 h-4" />My account <span class="ms-auto text-xs text-white/50">password, mobile</span></a>
            <a href="{{ route('profile.2fa.setup') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-white/80 hover:bg-white/10 hover:text-white" data-admin-2fa-link><x-icon name="shield" class="w-4 h-4" />Two-step sign-in <span class="ms-auto text-xs text-white/50">{{ $staff?->isTwoFactorEnabled() ? 'On' : 'Off' }}</span></a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-start text-white/80 hover:bg-white/10 hover:text-white"><x-icon name="user" class="w-4 h-4 shrink-0" /><span class="min-w-0"><span class="block">Sign out</span><span class="block truncate text-xs text-white/50">{{ $staff?->email }}</span></span></button>
            </form>
        </div>
    </aside>

    <div class="lg:ps-64 min-h-full flex flex-col">
        {{-- Phone top bar: menu, the page, the inbox --}}
        <header class="lg:hidden sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-gray-200 bg-white px-2 shadow-sm">
            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-dark hover:bg-gray-100" data-admin-menu-open aria-controls="admin-drawer" aria-expanded="false" aria-label="Open the admin menu">
                <x-icon name="menu" class="w-6 h-6" />
            </button>
            <a href="{{ route('admin.dashboard') }}" class="shrink-0"><img src="/images/brand/iruali-mark.svg" alt="iruali admin" class="h-7 w-7"></a>
            <p class="min-w-0 flex-1 truncate font-semibold text-dark" data-admin-current>{{ $currentLabel }}</p>
            @if($navFlat->has('admin.inbox'))
                <a href="{{ route('admin.inbox') }}" class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg text-dark hover:bg-gray-100" aria-label="Inbox{{ $inboxTotal ? ' ('.$inboxTotal.' waiting)' : '' }}">
                    <x-icon name="bell" class="w-6 h-6" />
                    @if($inboxTotal)<span class="absolute top-1 end-1 min-w-[1.1rem] rounded-full bg-red-600 px-1 text-center text-[11px] font-semibold leading-[1.1rem] text-white">{{ $inboxTotal }}</span>@endif
                </a>
            @endif
        </header>

        <main id="main" class="flex-1 pb-20 lg:pb-0">
            @yield('content')
        </main>
    </div>

    {{-- Phone menu drawer --}}
    <div id="admin-drawer" class="fixed inset-0 z-50 hidden lg:hidden" data-admin-drawer role="dialog" aria-modal="true" aria-label="Admin menu">
        <div class="absolute inset-0 bg-reef/60" data-admin-menu-close></div>
        <nav class="absolute inset-y-0 start-0 flex w-[85%] max-w-xs flex-col bg-reef text-white shadow-xl">
            <div class="flex h-14 shrink-0 items-center justify-between border-b border-white/10 px-4">
                <img src="/images/brand/iruali-logo-reversed.svg" alt="iruali" class="h-6 w-auto">
                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg hover:bg-white/10" data-admin-menu-close data-dialog-close aria-label="Close the menu"><x-icon name="x" class="w-6 h-6" /></button>
            </div>
            <div class="flex-1 overflow-y-auto px-3 py-4">{!! $menu('drawer') !!}</div>
            <div class="border-t border-white/10 p-3 space-y-1 text-sm">
                <a href="{{ route('home') }}" class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-white/80 hover:bg-white/10"><x-icon name="store" class="w-4 h-4" />View the shop</a>
                <a href="{{ route('account') }}" class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-white/80 hover:bg-white/10"><x-icon name="user" class="w-4 h-4" />My account <span class="ms-auto text-xs text-white/50">password, mobile</span></a>
                <a href="{{ route('profile.2fa.setup') }}" class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-white/80 hover:bg-white/10"><x-icon name="shield" class="w-4 h-4" />Two-step sign-in <span class="ms-auto text-xs text-white/50">{{ $staff?->isTwoFactorEnabled() ? 'On' : 'Off' }}</span></a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-white/80 hover:bg-white/10"><x-icon name="user" class="w-4 h-4" />Sign out</button>
                </form>
            </div>
        </nav>
    </div>

    {{-- Phone bottom bar: the pages used most, and the full menu --}}
    <nav class="lg:hidden fixed inset-x-0 bottom-0 z-30 grid border-t border-gray-200 bg-white" style="grid-template-columns: repeat({{ collect(AdminNav::BOTTOM_BAR)->filter(fn ($r) => $navFlat->has($r))->count() + 1 }}, minmax(0, 1fr)); padding-bottom: env(safe-area-inset-bottom);" aria-label="Admin shortcuts">
        @foreach(AdminNav::BOTTOM_BAR as $route)
            @if($item = $navFlat->get($route))
                <a href="{{ $item['url'] }}" class="relative flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium {{ $item['active'] ? 'text-primary' : 'text-gray-500' }}" @if($item['active']) aria-current="page" @endif>
                    <x-icon :name="$item['icon']" class="w-5 h-5" />{{ $item['label'] }}
                    @if($route === 'admin.inbox' && $inboxTotal)<span class="absolute top-1 start-1/2 ms-2 min-w-[1rem] rounded-full bg-red-600 px-1 text-center text-[10px] font-semibold leading-4 text-white">{{ $inboxTotal }}</span>@endif
                </a>
            @endif
        @endforeach
        <button type="button" class="flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium text-gray-500" data-admin-menu-open aria-controls="admin-drawer" aria-expanded="false">
            <x-icon name="menu" class="w-5 h-5" />More
        </button>
    </nav>

    @if(session('notification'))
        <div id="session-notification" data-notification="{{ json_encode(session('notification')) }}" hidden></div>
    @endif

    <script>
        (function () {
            var drawer = document.querySelector('[data-admin-drawer]');
            if (!drawer) return;
            var openers = document.querySelectorAll('[data-admin-menu-open]');
            var lastOpener = null;
            function set(open) {
                drawer.classList.toggle('hidden', !open);
                document.body.classList.toggle('overflow-hidden', open);
                openers.forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
                if (open) {
                    var current = drawer.querySelector('[aria-current="page"]');
                    if (current) current.scrollIntoView({ block: 'center' });
                    var close = drawer.querySelector('[data-dialog-close]');
                    if (close) close.focus();
                } else if (lastOpener) {
                    lastOpener.focus();
                }
            }
            openers.forEach(function (b) { b.addEventListener('click', function () { lastOpener = b; set(true); }); });
            drawer.querySelectorAll('[data-admin-menu-close]').forEach(function (b) { b.addEventListener('click', function () { set(false); }); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !drawer.classList.contains('hidden')) set(false); });
        })();
    </script>
    <script>
        // Folded sections (x-admin.fold): remember which were left open on this page, and open one when a
        // field inside it is invalid or a link points into it.
        (function () {
            var folds = document.querySelectorAll('details[data-fold]');
            if (!folds.length) return;
            var key = function (d) { return 'admin-fold:' + location.pathname + ':' + d.getAttribute('data-fold'); };
            folds.forEach(function (d) {
                try {
                    if (!d.open && localStorage.getItem(key(d)) === '1') d.open = true;
                } catch (e) {}
                d.addEventListener('toggle', function () {
                    try { d.open ? localStorage.setItem(key(d), '1') : localStorage.removeItem(key(d)); } catch (e) {}
                });
            });
            var openAround = function (el) {
                for (var d = el && el.closest('details[data-fold]'); d; d = d.parentElement && d.parentElement.closest('details[data-fold]')) d.open = true;
            };
            document.addEventListener('invalid', function (e) { openAround(e.target); }, true);
            var fromHash = function () {
                if (location.hash.length > 1) { try { openAround(document.querySelector(location.hash)); } catch (e) {} }
            };
            fromHash();
            window.addEventListener('hashchange', fromHash);
        })();
    </script>
    @stack('scripts')
</body>
</html>
