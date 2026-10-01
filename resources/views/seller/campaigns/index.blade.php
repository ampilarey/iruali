@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @component('seller.partials.header', ['title' => __('Campaigns')])@endcomponent

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="mb-4 text-sm text-gray-600">{{ __('Put your products into a sale or event at a discount. iruali promotes the campaign on the home page; an admin approves each product before it shows.') }}</p>

        @if($campaigns->isEmpty())
            <div class="rounded-lg bg-white p-8 text-center text-gray-500 shadow">{{ __('No campaign is open for shops right now.') }}</div>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach($campaigns as $campaign)
                    <div class="rounded-lg bg-white p-5 shadow flex flex-col gap-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">{{ $campaign->name }}</h2>
                                <p class="text-sm text-gray-500">{{ $campaign->starts_at->translatedFormat('j M Y') }} – {{ $campaign->ends_at->translatedFormat('j M Y') }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $campaign->isLive() ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">{{ $campaign->isLive() ? __('Live') : __('Upcoming') }}</span>
                        </div>
                        <p class="text-sm text-gray-700">{{ $campaign->minimumDiscount() > 0 ? __('Minimum discount: :percent%', ['percent' => rtrim(rtrim(number_format($campaign->minimumDiscount(), 2, '.', ''), '0'), '.')]) : __('No minimum discount') }}</p>
                        <p class="text-sm text-gray-500">{{ trans_choice(':count of your products in, :approved approved|:count of your products in, :approved approved', $campaign->mine_count, ['count' => $campaign->mine_count, 'approved' => $campaign->mine_approved_count]) }}</p>
                        <a href="{{ route('seller.campaigns.show', $campaign) }}" class="self-start rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ $campaign->mine_count > 0 ? __('Manage products') : __('Join campaign') }}</a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
