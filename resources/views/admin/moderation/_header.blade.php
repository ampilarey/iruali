<div class="bg-white shadow">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3 py-4">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-primary-600">Moderation</p>
                <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Back to Dashboard</a>
        </div>
        <nav class="flex gap-1 overflow-x-auto -mb-px">
            @foreach(['admin.reviews' => 'Reviews', 'admin.questions' => 'Questions', 'admin.newsletter' => 'Newsletter'] as $route => $label)
                <a href="{{ route($route) }}" class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium {{ request()->routeIs($route) ? 'border-primary-500 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </div>
</div>
@if(session('success'))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    </div>
@endif
