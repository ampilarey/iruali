@extends('layouts.app')

@section('title', __('Business verifications'))

@php
    $filters = ['pending' => __('To check'), 'approved' => __('Verified'), 'rejected' => __('Rejected'), 'all' => __('All')];
    $statusLabels = ['pending' => __('To check'), 'approved' => __('Verified business'), 'rejected' => __('Rejected')];
    $statusStyles = ['pending' => 'bg-yellow-100 text-yellow-800', 'approved' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800'];
    $documents = ['certificate' => __('Business registration certificate'), 'id_card' => __('Photo of the ID card (front)')];
@endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => __('Business verifications'), 'back' => route('admin.sellers')])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <p class="text-sm text-gray-600 max-w-3xl">{{ __('Shops send their business registration certificate and the owner\'s ID card. Check that the business name and numbers match the shop, then approve it to show "Verified business" next to its name, or reject it with a reason the shop can act on. The shop is emailed either way.') }}</p>

        <div class="flex w-fit flex-wrap overflow-hidden rounded-lg border border-gray-300 bg-white text-sm" role="group" aria-label="{{ __('Show') }}">
            @foreach($filters as $key => $label)
                <a href="{{ route('admin.verifications', $key === 'pending' ? [] : ['status' => $key]) }}" class="px-4 py-2 {{ $status === $key ? 'bg-primary-600 text-white' : 'text-gray-700 hover:bg-gray-50' }}" @if($status === $key) aria-current="true" @endif>{{ $label }} ({{ (int) ($counts[$key] ?? 0) }})</a>
            @endforeach
        </div>

        @forelse($verifications as $verification)
            @php $shop = $verification->user; $version = $verification->submitted_at?->getTimestamp(); @endphp
            <article class="rounded-lg bg-white p-5 shadow" data-verification="{{ $verification->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-gray-900">{{ $shop->shopName() }}</h2>
                        <p class="text-sm text-gray-600">{{ $shop->name }} · {{ $shop->email }}@if($shop->phone) · <span dir="ltr">{{ $shop->phone }}</span>@endif</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ __('Sent :date', ['date' => $verification->submitted_at?->format('d M Y, H:i')]) }}
                            @unless($shop->seller_approved) · <span class="font-medium text-amber-700">{{ __('Shop not approved yet') }}</span>@endunless
                            @if($shop->isSeller()) · <a href="{{ route('sellers.show', $shop) }}" target="_blank" rel="noopener" class="text-primary-700 hover:underline">{{ __('Shop page') }}</a>@endif
                        </p>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusStyles[$verification->status] ?? 'bg-gray-100 text-gray-700' }}">{{ $statusLabels[$verification->status] ?? $verification->status }}</span>
                </div>

                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">{{ __('Business registration number') }}</dt><dd class="font-mono font-semibold text-gray-900" dir="ltr">{{ $verification->business_registration_number }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Owner\'s national ID card number') }}</dt><dd class="font-mono font-semibold text-gray-900" dir="ltr">{{ $verification->nationalIdNumber() ?? __('Cannot be read') }}</dd></div>
                </dl>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach($documents as $document => $label)
                        @php $url = route('admin.verifications.document', [$verification, $document]); @endphp
                        <figure class="rounded-lg border border-gray-200 p-3" data-document="{{ $document }}">
                            <figcaption class="flex items-center justify-between gap-2 text-sm font-medium text-gray-700">
                                <span>{{ $label }}</span>
                                @if($verification->documentExists($document))<a href="{{ $url }}" target="_blank" rel="noopener" class="shrink-0 text-primary-700 hover:underline">{{ __('Open file') }}</a>@endif
                            </figcaption>
                            @if(! $verification->documentExists($document))
                                <p class="mt-2 text-sm text-red-700">{{ __('The file is missing.') }}</p>
                            @elseif($verification->isPdf($document))
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="mt-2 flex h-32 items-center justify-center gap-2 rounded bg-gray-50 text-sm font-medium text-gray-700 hover:bg-gray-100"><x-icon name="box" class="w-5 h-5" />{{ __('PDF: open to check it') }}</a>
                            @else
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="mt-2 block"><img src="{{ $url }}" alt="{{ $label }}" loading="lazy" class="max-h-72 w-full rounded bg-gray-50 object-contain"></a>
                            @endif
                        </figure>
                    @endforeach
                </div>

                @if($verification->reviewed_at)
                    <p class="mt-4 text-sm text-gray-600">
                        {{ $verification->isApproved() ? __('Verified by :name on :date.', ['name' => $verification->reviewer?->name ?? '—', 'date' => $verification->reviewed_at->format('d M Y')]) : __('Rejected by :name on :date.', ['name' => $verification->reviewer?->name ?? '—', 'date' => $verification->reviewed_at->format('d M Y')]) }}
                        @if($verification->isRejected() && $verification->rejection_reason)<span class="block text-gray-800">{{ __('Reason: :reason', ['reason' => $verification->rejection_reason]) }}</span>@endif
                    </p>
                @endif

                <div class="mt-4 flex flex-wrap items-start gap-4 border-t border-gray-100 pt-4">
                    @unless($verification->isApproved())
                        <form method="POST" action="{{ route('admin.verifications.approve', $verification) }}">
                            @csrf
                            <input type="hidden" name="version" value="{{ $version }}">
                            <button class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">{{ __('Approve') }}</button>
                        </form>
                    @endunless
                    @unless($verification->isRejected())
                        <form method="POST" action="{{ route('admin.verifications.reject', $verification) }}" class="min-w-[16rem] flex-1 space-y-2">
                            @csrf
                            <input type="hidden" name="version" value="{{ $version }}">
                            <label for="reason-{{ $verification->id }}" class="block text-sm font-medium text-gray-700">{{ $verification->isApproved() ? __('Withdraw the verification, with a reason for the shop') : __('Or reject, with a reason for the shop') }}</label>
                            <textarea id="reason-{{ $verification->id }}" name="reason" rows="2" required minlength="5" maxlength="1000" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" placeholder="{{ __('e.g. The certificate is for a different business name.') }}"></textarea>
                            <button class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">{{ $verification->isApproved() ? __('Withdraw verification') : __('Reject') }}</button>
                        </form>
                    @endunless
                </div>
            </article>
        @empty
            <div class="rounded-lg bg-white p-10 text-center text-gray-500 shadow">{{ $status === 'pending' ? __('No shops are waiting to be checked.') : __('Nothing to show here.') }}</div>
        @endforelse

        @if($verifications->hasPages())
            <div>{{ $verifications->links() }}</div>
        @endif
    </div>
</div>
@endsection
