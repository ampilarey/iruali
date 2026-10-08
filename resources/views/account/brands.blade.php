@extends('layouts.app')

@section('title', __('Brands you follow'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-dark mb-8">{{ __('Brands you follow') }}</h1>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <aside class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <nav class="space-y-1" aria-label="{{ __('Account') }}">
                        <a href="{{ route('account') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('Profile') }}</a>
                        <a href="{{ route('orders') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('My Orders') }}</a>
                        <a href="{{ route('wishlist') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('Wishlist') }}</a>
                        <a href="{{ route('account.brands') }}" class="block px-4 py-2 text-primary bg-primary/10 rounded-lg font-medium" aria-current="page">{{ __('Brands you follow') }}</a>
                        <a href="{{ route('account.notifications') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('Notifications') }}</a>
                    </nav>
                </div>
            </aside>

            <div class="lg:col-span-2 space-y-6">
                @if($brands->isEmpty())
                    <section class="bg-white rounded-lg shadow-sm border border-gray-100 p-10 text-center">
                        <span class="mx-auto w-14 h-14 rounded-full bg-primary-50 text-primary flex items-center justify-center mb-4"><x-icon name="badge" class="w-7 h-7" /></span>
                        <p class="font-semibold text-lg text-dark">{{ __('You do not follow any brands yet.') }}</p>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Press Follow on a brand page and we will email you when the brand puts products on sale.') }}</p>
                        <a href="{{ route('brands.index') }}" class="inline-block mt-5 px-5 py-2.5 rounded-lg bg-primary text-white font-semibold hover:bg-primary-hover">{{ __('Browse brands') }}</a>
                    </section>
                @else
                    <section class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                        <p class="text-sm text-gray-600">
                            @if($user->wantsBrandUpdates())
                                {{ __('Once a day, we email you what these brands put on sale or into a sale since our last email.') }}
                            @elseif($user->emailPreference('brand_updates') === 'off')
                                {{ __('Emails about the brands you follow are off.') }}
                            @elseif($user->marketing_opt_out_at)
                                {{ __('Marketing emails are off, so we do not email you about the brands you follow.') }}
                            @endif
                            <a href="{{ route('account.notifications') }}" class="font-semibold text-primary hover:underline">{{ __('Email settings') }}</a>
                        </p>

                        <ul class="mt-5 grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach($brands as $brand)
                                <li class="flex flex-col gap-2">
                                    @if($brand->activeProductCount() > 0)
                                        @include('brands._tile', ['brand' => $brand])
                                    @else
                                        {{-- Nothing on sale right now, so there is no brand page to open --}}
                                        <div class="bg-white border border-gray-200 rounded-xl p-3 flex flex-col items-center text-center gap-2">
                                            @include('brands._logo', ['brand' => $brand, 'class' => 'w-12 h-12 lg:w-14 lg:h-14 text-lg'])
                                            <span class="w-full text-xs lg:text-sm font-semibold text-dark truncate">{{ $brand->localizedName() }}</span>
                                            <span class="-mt-1.5 text-[11px] text-gray-500">{{ __('Nothing on sale right now') }}</span>
                                        </div>
                                    @endif
                                    <form method="POST" action="{{ route('brands.unfollow', $brand) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                            {{ __('Unfollow') }}<span class="sr-only"> {{ $brand->localizedName() }}</span>
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
