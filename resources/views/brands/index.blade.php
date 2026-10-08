@extends('layouts.app')

@section('title', __('Brands'))

@section('content')
<div class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 lg:px-6 py-4 lg:py-6">
        <nav class="text-xs sm:text-sm text-gray-500 mb-3" aria-label="{{ __('Breadcrumb') }}">
            <ol class="flex items-center gap-1.5">
                <li><a href="{{ route('home') }}" class="hover:text-primary hover:underline">{{ __('Home') }}</a></li>
                <li class="flex items-center gap-1.5"><x-icon name="chevron-right" class="w-3 h-3 rtl:rotate-180" /><span class="text-dark font-medium">{{ __('Brands') }}</span></li>
            </ol>
        </nav>

        <div class="mb-5">
            <h1 class="font-display text-2xl lg:text-3xl font-bold text-dark">{{ __('Brands') }}</h1>
            <p class="text-sm text-gray-600 mt-1 max-w-3xl">
                {{ __('Every brand sold on iruali, from shops across the islands.') }}
                @if($brands->isNotEmpty())<span class="text-gray-500">{{ trans_choice(':count brand|:count brands', $brands->count(), ['count' => $brands->count()]) }}</span>@endif
            </p>
        </div>

        @if($brands->isEmpty())
            <div class="bg-white border border-gray-200 rounded-xl p-10 text-center">
                <span class="mx-auto w-14 h-14 rounded-full bg-primary-50 text-primary flex items-center justify-center mb-4"><x-icon name="badge" class="w-7 h-7" /></span>
                <p class="font-semibold text-lg">{{ __('No brands yet.') }}</p>
                <a href="{{ route('shop') }}" class="inline-block mt-5 px-5 py-2.5 rounded-lg bg-primary text-white font-semibold">{{ __('Shop all products') }}</a>
            </div>
        @else
            @if($popular->isNotEmpty())
                <section class="mb-6" aria-labelledby="popular-brands">
                    <h2 id="popular-brands" class="font-display text-lg lg:text-xl font-bold text-dark mb-3">{{ __('Popular brands') }}</h2>
                    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 lg:gap-3">
                        @foreach($popular as $brand)
                            @include('brands._tile', ['brand' => $brand])
                        @endforeach
                    </div>
                </section>
            @endif

            <div class="bg-white border border-gray-200 rounded-xl p-4 lg:p-6">
                <div class="space-y-3 mb-4">
                    {{-- Shown by the script below; without JavaScript the A–Z links do the job --}}
                    <div data-brand-filter-wrap hidden class="max-w-sm">
                        <label for="brand-filter" class="sr-only">{{ __('Find a brand') }}</label>
                        <div class="relative">
                            <x-icon name="search" class="w-4 h-4 absolute start-3 top-1/2 -translate-y-1/2 text-gray-400" />
                            <input id="brand-filter" type="search" placeholder="{{ __('Find a brand') }}" autocomplete="off" class="w-full rounded-lg border border-gray-300 ps-9 pe-3 py-2 text-sm focus:border-primary focus:ring-primary">
                        </div>
                    </div>
                    <nav aria-label="{{ __('Brands A to Z') }}" class="flex flex-wrap gap-1 text-sm" dir="ltr">
                        @foreach(array_merge(range('A', 'Z'), ['#']) as $letter)
                            @if($letters->has($letter))
                                <a href="#letter-{{ $letter === '#' ? 'other' : $letter }}" class="w-8 h-8 rounded-lg flex items-center justify-center font-semibold text-primary hover:bg-primary-50">{{ $letter }}</a>
                            @else
                                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-300" aria-hidden="true">{{ $letter }}</span>
                            @endif
                        @endforeach
                    </nav>
                </div>

                <div class="space-y-6">
                    @foreach($letters as $letter => $group)
                        <section id="letter-{{ $letter === '#' ? 'other' : $letter }}" data-letter-group class="scroll-mt-32">
                            <h2 class="font-display text-lg font-bold text-dark border-b border-gray-100 pb-1 mb-2" dir="ltr">{{ $letter }}</h2>
                            <ul class="grid sm:grid-cols-2 lg:grid-cols-4 gap-1">
                                @foreach($group as $brand)
                                    <li data-brand-name="{{ mb_strtolower($brand->name) }}">
                                        <a href="{{ route('brands.show', $brand) }}" class="group flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50">
                                            @include('brands._logo', ['brand' => $brand, 'class' => 'w-9 h-9 text-sm'])
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-sm font-semibold text-dark truncate group-hover:text-primary">{{ $brand->name }}</span>
                                                <span class="block text-xs text-gray-500">{{ trans_choice(':count product|:count products', $brand->active_products_count, ['count' => $brand->active_products_count]) }}</span>
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                    <p data-brand-none hidden class="py-6 text-center text-sm text-gray-600">{{ __('No brand matches that name.') }}</p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var wrap = document.querySelector('[data-brand-filter-wrap]');
        if (!wrap) return;
        var input = document.getElementById('brand-filter');
        var groups = document.querySelectorAll('[data-letter-group]');
        var none = document.querySelector('[data-brand-none]');
        wrap.hidden = false;
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase(), shown = 0;
            groups.forEach(function (group) {
                var visible = 0;
                group.querySelectorAll('[data-brand-name]').forEach(function (item) {
                    var match = q === '' || item.getAttribute('data-brand-name').indexOf(q) !== -1;
                    item.hidden = !match;
                    if (match) visible++;
                });
                group.hidden = visible === 0;
                shown += visible;
            });
            none.hidden = shown !== 0;
        });
    })();
</script>
@endpush
