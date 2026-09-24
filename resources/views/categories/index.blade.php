@extends('layouts.app')

@section('content')
<div class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 lg:px-6 py-6 lg:py-8">
        <nav class="text-sm text-gray-500 mb-3" aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ route('home') }}" class="hover:text-primary hover:underline">{{ __('Home') }}</a>
            <x-icon name="chevron-right" class="w-3 h-3 inline rtl:rotate-180" />
            <span class="text-dark font-medium">{{ __('Departments') }}</span>
        </nav>
        <h1 class="font-display text-2xl lg:text-3xl font-bold text-dark mb-5">{{ __('All Departments') }}</h1>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            @foreach($categories as $category)
                <div class="bg-white border border-gray-200 rounded-xl p-5 flex flex-col hover:border-primary hover:shadow-sm">
                    <a href="{{ route('categories.show', $category) }}" class="flex items-center gap-3 group">
                        <span class="w-12 h-12 rounded-xl bg-primary-50 text-primary flex items-center justify-center group-hover:bg-primary group-hover:text-white transition"><x-icon :name="$category->slug" class="w-6 h-6" /></span>
                        <span>
                            <span class="block font-semibold text-dark group-hover:text-primary">{{ $category->localized_name }}</span>
                            <span class="block text-xs text-gray-500">{{ trans_choice(':count product|:count products', $category->products_count, ['count' => $category->products_count]) }}</span>
                        </span>
                    </a>
                    @if($category->localized_description)
                        <p class="mt-3 text-sm text-gray-600 line-clamp-2">{{ $category->localized_description }}</p>
                    @endif
                    @if($category->children->isNotEmpty())
                        <ul class="mt-3 space-y-1 text-sm">
                            @foreach($category->children as $child)
                                <li><a href="{{ route('categories.show', $child) }}" class="text-gray-700 hover:text-primary hover:underline">{{ $child->localized_name }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                    <a href="{{ route('categories.show', $category) }}" class="mt-auto pt-4 text-sm font-semibold text-primary hover:underline">{{ __('Shop now') }}</a>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
