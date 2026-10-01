{{-- Admin navigation: only the pages this staff member may open (config/staff.php) --}}
@php
    use App\Support\StaffAccess;
    $adminNav = [
        ['admin.dashboard', 'Dashboard'],
        ['admin.inbox', 'Inbox'],
        ['admin.orders', 'Orders'],
        ['admin.returns', 'Returns'],
        ['admin.payouts', 'Payouts'],
        ['admin.sellers', 'Sellers'],
        ['admin.products', 'Products'],
        ['admin.users', 'Users'],
        ['admin.reviews', 'Moderation'],
        ['admin.vouchers.index', 'Vouchers'],
        ['admin.analytics', 'Analytics'],
        ['admin.errors', 'Errors'],
        ['admin.audit', 'Audit log'],
        ['admin.legal', 'Legal pages'],
        ['admin.settings', 'Settings'],
    ];
    $inboxTotal = (Route::has('admin.inbox') && StaffAccess::can('admin.inbox')) ? \App\Support\AdminInbox::total() : 0;
@endphp
<nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex flex-wrap gap-x-4 gap-y-1 text-sm" aria-label="Admin">
    @foreach($adminNav as [$routeName, $label])
        @if(Route::has($routeName) && StaffAccess::can($routeName))
            <a href="{{ route($routeName) }}" class="{{ request()->routeIs($routeName) || request()->routeIs(\Illuminate\Support\Str::beforeLast($routeName, '.index').'.*') ? 'font-semibold text-primary-700' : 'text-gray-600 hover:text-gray-900' }}" data-nav="{{ $routeName }}">
                {{ $label }}@if($routeName === 'admin.inbox' && $inboxTotal)<span class="ms-1 rounded-full bg-red-600 px-1.5 text-xs font-semibold text-white" data-inbox-badge>{{ $inboxTotal }}</span>@endif
            </a>
        @endif
    @endforeach
</nav>
