@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => __('Business verification')])

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @include('seller.settings._nav')

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc ps-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-lg bg-white p-5 shadow text-sm" data-verification-status="{{ $verification?->status ?? 'none' }}">
            @if(! $verification)
                <h2 class="flex items-center gap-1.5 font-semibold text-gray-900"><x-icon name="shield" class="w-5 h-5 text-primary" />{{ __('Get the "Verified business" badge') }}</h2>
                <p class="mt-1 text-gray-600">{{ __('Send your business registration certificate and the owner\'s ID card. Once iruali has checked them, your shop shows "Verified business" next to its name on your products, your shop page and brand pages.') }}</p>
            @elseif($verification->isPending())
                <h2 class="font-semibold text-amber-800">{{ __('Waiting for iruali to check your documents') }}</h2>
                <p class="mt-1 text-gray-600">{{ __('Sent on :date. We usually check them within two working days and email you the result.', ['date' => $verification->submitted_at?->translatedFormat('j M Y')]) }}</p>
            @elseif($verification->isApproved())
                <h2 class="flex items-center gap-1.5 font-semibold text-green-800"><x-icon name="shield" class="w-5 h-5" />{{ __('Your business is verified') }}</h2>
                <p class="mt-1 text-gray-600">{{ __('Checked on :date. Customers see "Verified business" next to your shop\'s name.', ['date' => $verification->reviewed_at?->translatedFormat('j M Y')]) }}</p>
            @else
                <h2 class="font-semibold text-red-800">{{ __('We could not verify your business') }}</h2>
                <p class="mt-1 text-gray-800">{{ __('Reason: :reason', ['reason' => $verification->rejection_reason]) }}</p>
                <p class="mt-1 text-gray-600">{{ __('Please send corrected documents below and we will check them again.') }}</p>
            @endif

            @if($verification)
                <dl class="mt-4 grid gap-x-6 gap-y-2 border-t border-gray-100 pt-4 sm:grid-cols-2">
                    <div><dt class="text-gray-500">{{ __('Business registration number') }}</dt><dd class="font-medium text-gray-900" dir="ltr">{{ $verification->business_registration_number }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Owner\'s national ID card number') }}</dt><dd class="font-medium text-gray-900" dir="ltr">{{ $verification->maskedNationalId() }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Business registration certificate') }}</dt><dd><a href="{{ route('seller.settings.verification.document', 'certificate') }}" target="_blank" rel="noopener" class="font-medium text-primary-700 hover:underline">{{ __('View the file we have') }}</a></dd></div>
                    <div><dt class="text-gray-500">{{ __('Photo of the ID card (front)') }}</dt><dd><a href="{{ route('seller.settings.verification.document', 'id_card') }}" target="_blank" rel="noopener" class="font-medium text-primary-700 hover:underline">{{ __('View the file we have') }}</a></dd></div>
                </dl>
            @endif
        </div>

        <form method="POST" action="{{ route('seller.settings.verification.update') }}" enctype="multipart/form-data" class="space-y-4 rounded-lg bg-white p-6 shadow">
            @csrf
            @method('PUT')
            <div>
                <h2 class="text-base font-semibold text-gray-900">{{ $verification ? __('Update your documents') : __('Send your documents') }}</h2>
                <p class="text-sm text-gray-500">{{ __('The certificate must be for this shop\'s business, and the ID card for its owner.') }}</p>
            </div>
            @if($verification?->isApproved())
                <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ __('Changing anything sends your shop back for checking: the "Verified business" badge is hidden until iruali has checked the new details.') }}</p>
            @endif

            @include('seller.verification._fields', ['verification' => $verification])

            <div class="flex justify-end border-t border-gray-100 pt-4">
                <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Send for checking') }}</button>
            </div>
        </form>

        <p class="flex items-start gap-1.5 text-xs text-gray-500"><x-icon name="shield" class="w-4 h-4 shrink-0" />{{ __('Your documents are stored privately. Only the iruali staff who check shops can see them; customers never do.') }}</p>
    </div>
</div>
@endsection
