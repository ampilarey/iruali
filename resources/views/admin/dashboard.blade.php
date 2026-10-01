@extends('layouts.app')

@section('content')

<div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700">Welcome, {{ Auth::user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @include('admin.partials.nav')
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Total Users -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="shrink-0">
                            <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Users</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['total_users'] ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Products -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="shrink-0">
                            <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Products</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['total_products'] ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Orders -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="shrink-0">
                            <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Orders</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['total_orders'] ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Sellers -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="shrink-0">
                            <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Sellers</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['total_sellers'] ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Sellers -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="shrink-0">
                            <div class="w-8 h-8 bg-orange-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Pending Sellers</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['pending_sellers'] ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Products -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="shrink-0">
                            <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Pending Products</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['pending_products'] ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(! empty($readyChecks))
        <!-- System status: the same checks as `php artisan iruali:ready` (offline, cached for 5 minutes) -->
        @php $readyFailures = \App\Support\ReadyChecks::failures($readyChecks); @endphp
        <div class="bg-white shadow rounded-lg mb-8" id="system-status">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">System status</h3>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $readyFailures ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                        {{ $readyFailures ? 'Not ready: '.$readyFailures.' problem(s)' : 'Ready' }}
                    </span>
                </div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach($readyChecks as $check)
                        @php $tone = ['pass' => 'bg-green-500', 'warn' => 'bg-amber-500', 'fail' => 'bg-red-500', 'skip' => 'bg-gray-300'][$check['status']] ?? 'bg-gray-300'; @endphp
                        <li class="flex items-start gap-3 py-2" data-status="{{ $check['status'] }}">
                            <span class="mt-1.5 inline-block h-2.5 w-2.5 shrink-0 rounded-full {{ $tone }}" title="{{ $check['status'] }}"></span>
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
                <p class="mt-3 text-xs text-gray-400">Refreshes every 5 minutes. Network checks run from the server with <code>php artisan iruali:ready</code>.</p>
            </div>
        </div>
        @endif

        <!-- Needs attention: the admin inbox rows this person may open (App\Support\AdminInbox) -->
        @php $inboxItems = array_filter(\App\Support\AdminInbox::items(), fn ($i) => $i['count'] > 0); @endphp
        @if($inboxItems)
        <div class="bg-white shadow rounded-lg mb-8" id="needs-attention">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Needs attention</h3>
                    <a href="{{ route('admin.inbox') }}" class="text-sm font-medium text-primary-700 hover:underline">Open inbox</a>
                </div>
                <ul class="flex flex-wrap gap-2 text-sm">
                    @foreach($inboxItems as $item)
                        @php $tone = ['danger' => 'bg-red-100 text-red-800', 'warn' => 'bg-amber-100 text-amber-800', 'info' => 'bg-blue-100 text-blue-800'][$item['severity']]; @endphp
                        <li><a href="{{ $item['url'] }}" class="inline-flex items-center gap-2 rounded-full bg-gray-50 px-3 py-1.5 hover:bg-gray-100" data-inbox="{{ $item['key'] }}">{{ $item['label'] }} <span class="rounded-full px-2 text-xs font-semibold {{ $tone }}">{{ $item['count'] }}</span></a></li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        <!-- Quick Actions -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Quick Actions</h3>
                @php $can = fn ($route) => \App\Support\StaffAccess::can($route); @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    @if($can('admin.users'))
                    <a href="{{ route('admin.users') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Manage Users
                    </a>
                    @endif
                    @if($can('admin.sellers'))
                    <a href="{{ route('admin.sellers') }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Manage Sellers
                    </a>
                    @endif
                    @if($can('admin.products'))
                    <a href="{{ route('admin.products') }}" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Manage Products
                    </a>
                    @endif
                    @if($can('admin.orders'))
                    <a href="{{ route('admin.orders') }}" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Manage Orders
                    </a>
                    @endif
                    @if($can('admin.vouchers.index'))
                    <a href="{{ route('admin.vouchers.index') }}" class="bg-pink-600 hover:bg-pink-700 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Manage Vouchers
                    </a>
                    @endif
                    @if($can('admin.analytics'))
                    <a href="{{ route('admin.analytics') }}" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Analytics
                    </a>
                    @endif
                    @if($can('admin.reviews'))
                    @php $openQuestions = \App\Models\ProductQuestion::whereNull('answer')->count(); @endphp
                    <a href="{{ route('admin.reviews') }}" class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Reviews &amp; Questions @if($openQuestions)<span class="ms-1 rounded-full bg-white/25 px-2 text-xs">{{ $openQuestions }} open</span>@endif
                    </a>
                    @endif
                    @if($can('admin.returns'))
                    @php $openReturns = \App\Models\ReturnRequest::whereIn('status', ['requested', 'approved'])->count() + \App\Models\Order::where('refund_status', 'due')->count(); @endphp
                    <a href="{{ route('admin.returns') }}" class="bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Returns @if($openReturns)<span class="ms-1 rounded-full bg-white/25 px-2 text-xs">{{ $openReturns }} open</span>@endif
                    </a>
                    @endif
                    @if($can('admin.payouts'))
                    <a href="{{ route('admin.payouts') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Shop payouts
                    </a>
                    @endif
                    @if($can('admin.legal'))
                    <a href="{{ route('admin.legal') }}" class="bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Legal pages
                    </a>
                    @endif
                    @if($can('admin.newsletter'))
                    <a href="{{ route('admin.newsletter') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Newsletter
                    </a>
                    @endif
                    @if($can('admin.disputes'))
                    @php $openDisputes = \App\Models\Dispute::open()->count(); @endphp
                    <a href="{{ route('admin.disputes') }}" class="bg-orange-800 hover:bg-orange-900 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Disputes @if($openDisputes)<span class="ms-1 rounded-full bg-white/25 px-2 text-xs">{{ $openDisputes }} open</span>@endif
                    </a>
                    <a href="{{ route('admin.messages') }}" class="bg-sky-700 hover:bg-sky-800 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Messages @if(($messageBadges['admin'] ?? 0) > 0)<span class="ms-1 rounded-full bg-white/25 px-2 text-xs">{{ $messageBadges['admin'] }} unread</span>@endif
                    </a>
                    <a href="{{ route('admin.sms') }}" class="bg-cyan-700 hover:bg-cyan-800 text-white px-4 py-2 rounded-lg text-center font-medium">
                        SMS
                    </a>
                    @endif
                    @if($can('admin.settings'))
                    <a href="{{ route('admin.settings') }}" class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Settings
                    </a>
                    @endif
                    @if($can('admin.errors'))
                    <a href="{{ route('admin.errors') }}" class="bg-red-700 hover:bg-red-800 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Errors @if($n = \App\Models\ErrorEvent::unresolved()->count())<span class="ms-1 rounded-full bg-white/25 px-2 text-xs">{{ $n }} open</span>@endif
                    </a>
                    @endif
                    @if($can('admin.audit'))
                    <a href="{{ route('admin.audit') }}" class="bg-cyan-700 hover:bg-cyan-800 text-white px-4 py-2 rounded-lg text-center font-medium">
                        Audit log
                    </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Users -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Recent Users</h3>
                    <div class="space-y-3">
                        @forelse($recent_users ?? [] as $user)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                                    <span class="text-sm font-medium text-gray-700">{{ substr($user->name, 0, 1) }}</span>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">{{ $user->name }}</p>
                                    <p class="text-sm text-gray-500">{{ $user->email }}</p>
                                </div>
                            </div>
                            <span class="text-xs text-gray-500">{{ $user->created_at->diffForHumans() }}</span>
                        </div>
                        @empty
                        <p class="text-gray-500 text-center py-4">No recent users</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Recent Orders</h3>
                    <div class="space-y-3">
                        @forelse($recent_orders ?? [] as $order)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-green-300 rounded-full flex items-center justify-center">
                                    <span class="text-sm font-medium text-gray-700">#{{ $order->id }}</span>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">{{ $order->customerName() ?? 'Deleted user' }}@if($order->isGuest()) <span class="text-xs text-amber-700 font-medium">(Guest)</span>@endif</p>
                                    <p class="text-sm text-gray-500">{{ \App\Support\Money::format($order->total_amount ?? 0) }}</p>
                                </div>
                            </div>
                            <span class="text-xs text-gray-500">{{ $order->created_at->diffForHumans() }}</span>
                        </div>
                        @empty
                        <p class="text-gray-500 text-center py-4">No recent orders</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
