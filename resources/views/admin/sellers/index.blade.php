@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100">
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Manage Sellers</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="{{ route('admin.dashboard') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shop</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applied</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" title="{{ __('Paid orders not shipped within :n days of payment, last :days days.', ['n' => \App\Models\Setting::get('late_shipment_days'), 'days' => \App\Services\SellerPerformanceService::WINDOW_DAYS]) }}">{{ __('Late') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Rating') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($sellers ?? [] as $seller)
                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $seller->business_name ?: $seller->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $seller->name }}</div>
                                    @if($seller->business_description)
                                        <p class="mt-1 max-w-sm text-xs text-gray-600">{{ \Illuminate\Support\Str::limit($seller->business_description, 140) }}</p>
                                    @endif
                                    @include('admin.tax._seller', ['seller' => $seller])
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div>{{ $seller->email }}</div>
                                    <div class="text-xs text-gray-500">{{ collect([$seller->phone, $seller->city, $seller->state])->filter()->join(' · ') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($seller->status === 'suspended')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Suspended</span>
                                    @elseif($seller->seller_approved)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Approved</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending</span>
                                    @endif
                                    @include('admin.sellers._trust_status', ['seller' => $seller])
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ ($seller->seller_applied_at ?? $seller->created_at)->format('M d, Y') }}
                                </td>
                                @php $perf = $performance[$seller->id] ?? null; @endphp
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($perf && $perf['late_rate'] !== null)
                                        <a href="{{ route('admin.sellers.performance', $seller) }}" class="font-medium {{ $perf['late_rate'] > 10 ? 'text-red-700' : 'text-gray-900' }} hover:underline">{{ rtrim(rtrim(number_format($perf['late_rate'], 1), '0'), '.') }}%</a>
                                        <span class="block text-xs text-gray-500">{{ $perf['late_count'] }}/{{ $perf['shipped_total'] }}</span>
                                    @else
                                        <a href="{{ route('admin.sellers.performance', $seller) }}" class="text-gray-400 hover:underline">—</a>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($perf && $perf['rating'] !== null){{ number_format($perf['rating'], 1) }} ★ <span class="text-xs text-gray-500">({{ $perf['reviews'] }})</span>@else<span class="text-gray-400">—</span>@endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('admin.sellers.performance', $seller) }}" class="text-primary-700 hover:text-primary-900 me-3">{{ __('Performance') }}</a>
                                    @if(! $seller->seller_approved)
                                    <form method="POST" action="{{ route('admin.sellers.approve', $seller->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-green-600 hover:text-green-900 mr-3">Approve</button>
                                    </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.sellers.reject', $seller->id) }}" class="inline" onsubmit="return confirm('Remove seller access for this user?')">
                                        @csrf
                                        <button type="submit" class="text-red-600 hover:text-red-900">{{ $seller->seller_approved ? 'Revoke' : 'Reject' }}</button>
                                    </form>
                                    @if($seller->seller_approved && $seller->status !== 'suspended')
                                    <form method="POST" action="{{ route('admin.sellers.suspend', $seller->id) }}" class="inline ml-3" onsubmit="return confirm('Suspend this seller? Every product in their shop will be deactivated.')">
                                        @csrf
                                        <button type="submit" class="text-orange-600 hover:text-orange-900" title="Deactivates every product in this shop">Suspend</button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">No sellers found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if(isset($sellers) && $sellers->hasPages())
                <div class="mt-4">
                    {{ $sellers->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
