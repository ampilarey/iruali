@extends('layouts.app')

@section('title', __('Campaigns'))

@section('content')
<div class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 lg:px-6 py-6 space-y-6">
        <h1 class="font-display text-2xl lg:text-3xl font-bold text-dark">{{ __('Sales and events') }}</h1>
        @forelse($campaigns as $campaign)
            @include('campaigns._banner', ['campaign' => $campaign, 'size' => 'hero'])
        @empty
            <p class="rounded-xl bg-white border border-gray-200 p-8 text-center text-gray-600">{{ __('No campaign is running right now. Check back soon.') }}</p>
        @endforelse
    </div>
</div>
@endsection
