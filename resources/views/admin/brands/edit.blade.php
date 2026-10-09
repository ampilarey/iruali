@extends('layouts.app')

@section('title', $brand->name.' · Brands')

@section('content')
@php $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500'; @endphp
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => $brand->name, 'back' => route('admin.brands')])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('admin.brands.update', $brand) }}" enctype="multipart/form-data" class="lg:col-span-2 space-y-5 rounded-lg bg-white p-6 shadow">
            @csrf
            @method('PUT')

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input id="name" name="name" required maxlength="120" class="{{ $field }}" value="{{ old('name', $brand->name) }}">
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-gray-500">Spelled the way the brand spells itself. Every product of this brand shows this name.</p>
                </div>
                <div>
                    <label for="slug" class="block text-sm font-medium text-gray-700">Web address</label>
                    <div class="mt-1 flex rounded-lg border border-gray-300 focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500">
                        <span class="flex items-center ps-3 text-sm text-gray-500" dir="ltr">/brands/</span>
                        <input id="slug" name="slug" required maxlength="140" class="block w-full rounded-e-lg border-0 px-1 py-2 text-sm focus:ring-0" value="{{ old('slug', $brand->slug) }}" dir="ltr">
                    </div>
                    @error('slug')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-gray-500">If you change it, the old address keeps working and redirects here.</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name_dv" class="block text-sm font-medium text-gray-700">{{ __('Name in Dhivehi (optional)') }}</label>
                    <input id="name_dv" name="name_dv" maxlength="120" dir="rtl" lang="dv" class="{{ $field }}" value="{{ old('name_dv', $brand->name_dv) }}" aria-describedby="name_dv-hint">
                    @error('name_dv')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    <p id="name_dv-hint" class="mt-1 text-xs text-gray-500">{{ __('Only if shoppers know the brand by a name in Thaana. Dhivehi pages show it, Dhivehi search finds it, and shops typing it get this brand.') }}</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="description_en" class="block text-sm font-medium text-gray-700">Description (English)</label>
                    <textarea id="description_en" name="description_en" rows="4" maxlength="600" class="{{ $field }}">{{ old('description_en', $brand->getTranslation('description', 'en', false)) }}</textarea>
                    @error('description_en')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="description_dv" class="block text-sm font-medium text-gray-700">Description (Dhivehi)</label>
                    <textarea id="description_dv" name="description_dv" rows="4" maxlength="600" dir="rtl" lang="dv" class="{{ $field }}">{{ old('description_dv', $brand->getTranslation('description', 'dv', false)) }}</textarea>
                    @error('description_dv')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <p class="sm:col-span-2 -mt-2 text-xs text-gray-500">One or two sentences shown under the name on the brand page and to search engines. Up to 600 characters. Without a Dhivehi text, Dhivehi visitors see the English one.</p>
            </div>

            <div>
                <span class="block text-sm font-medium text-gray-700">Logo</span>
                <div class="mt-2 flex flex-wrap items-center gap-4">
                    @include('brands._logo', ['brand' => $brand, 'class' => 'w-16 h-16 text-2xl'])
                    <div class="space-y-2">
                        <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="block text-sm" aria-describedby="logo-hint">
                        @if($brand->logo)
                            <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300"> Remove the logo</label>
                        @endif
                    </div>
                </div>
                @error('logo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                <p id="logo-hint" class="mt-1 text-xs text-gray-500">PNG, JPG or WebP under 1 MB, ideally square with a transparent or white background. Only use a logo the brand allows resellers to use.</p>
            </div>

            <div>
                <span class="block text-sm font-medium text-gray-700">Banner</span>
                @if($brand->banner)
                    <img src="{{ $brand->bannerUrl(400) }}" alt="" class="mt-2 w-full max-w-md aspect-[4/1] rounded-lg border border-gray-200 object-cover" data-banner-preview>
                @endif
                <div class="mt-2 space-y-2">
                    <input id="banner" name="banner" type="file" accept="image/png,image/jpeg,image/webp" class="block text-sm" aria-describedby="banner-hint">
                    @if($brand->banner)
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="remove_banner" value="1" class="rounded border-gray-300"> Remove the banner</label>
                    @endif
                </div>
                @error('banner')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                <p id="banner-hint" class="mt-1 text-xs text-gray-500">A wide image across the top of the brand page: PNG, JPG or WebP under 2 MB, ideally 1600 × 400 pixels (at least 1000 wide). Phones show the middle three quarters, so keep text and faces away from the left and right edges. Only use images the brand allows resellers to use.</p>
            </div>

            <label class="flex items-start gap-2 text-sm text-gray-700">
                <input type="checkbox" name="reviewed" value="1" class="mt-0.5 rounded border-gray-300" @checked(old('reviewed', $brand->reviewed_at !== null))>
                <span><span class="font-medium">Reviewed</span> — the name is right and this is not a duplicate of another brand.</span>
            </label>

            <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                @if($brand->active_products_count > 0)
                    <a href="{{ route('brands.show', $brand) }}" target="_blank" rel="noopener" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">View page</a>
                @endif
                <button type="submit" class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-medium text-white hover:bg-primary-700">Save</button>
            </div>
        </form>

        <div class="space-y-6">
            <section class="rounded-lg bg-white p-6 shadow text-sm">
                <h2 class="text-base font-semibold text-gray-900">About this brand</h2>
                <dl class="mt-3 space-y-2">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Products on sale</dt><dd class="font-medium tabular-nums">{{ $brand->active_products_count }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">All products</dt><dd class="font-medium tabular-nums">{{ $brand->products_count }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('Followers') }}</dt><dd class="font-medium tabular-nums">{{ $brand->followers_count }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Added by</dt><dd class="text-end">{{ $brand->creator ? ($brand->creator->business_name ?: $brand->creator->name) : 'Existing listings' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Added</dt><dd>{{ $brand->created_at?->format('d M Y') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Search engines</dt><dd class="text-end">{{ $brand->isIndexable((int) $brand->active_products_count) ? 'Listed' : 'Not yet (fewer than '.\App\Models\Brand::INDEX_MIN_PRODUCTS.' products on sale)' }}</dd></div>
                </dl>
                @if($brand->aliases->isNotEmpty())
                    <h3 class="mt-4 font-semibold text-gray-900">Also reached by</h3>
                    <p class="text-xs text-gray-500">Old names shops may still type, and old addresses that redirect here.</p>
                    <ul class="mt-2 space-y-1 text-gray-700">
                        @foreach($brand->aliases as $alias)
                            @if($alias->slug)<li dir="ltr">/brands/{{ $alias->slug }}</li>@endif
                            @if($alias->key)<li>Name matching “{{ $alias->key }}”</li>@endif
                        @endforeach
                    </ul>
                @endif
            </section>

            @include('admin.brands._authorised_sellers')

            <section class="rounded-lg bg-white p-6 shadow text-sm">
                <h2 class="text-base font-semibold text-gray-900">Merge into another brand</h2>
                <p class="mt-1 text-gray-600">For duplicates. Every product of “{{ $brand->name }}” moves to the brand you choose, this brand's page redirects there, and shops typing “{{ $brand->name }}” get the other brand from now on.</p>
                @if($similar->isEmpty() && $others->isEmpty())
                    <p class="mt-3 text-gray-500">There is no other brand yet.</p>
                @else
                    <form method="POST" action="{{ route('admin.brands.merge', $brand) }}" class="mt-3 space-y-3" onsubmit="return confirm('Merge “{{ addslashes($brand->name) }}” into the brand you chose? This cannot be undone.');">
                        @csrf
                        <label for="target_id" class="sr-only">Merge into</label>
                        <select id="target_id" name="target_id" required class="{{ $field }}">
                            <option value="">Choose a brand…</option>
                            @if($similar->isNotEmpty())
                                <optgroup label="Looks similar">
                                    @foreach($similar as $other)<option value="{{ $other->id }}">{{ $other->name }}</option>@endforeach
                                </optgroup>
                            @endif
                            @if($others->isNotEmpty())
                                <optgroup label="All brands">
                                    @foreach($others as $other)<option value="{{ $other->id }}">{{ $other->name }}</option>@endforeach
                                </optgroup>
                            @endif
                        </select>
                        @error('target_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <button type="submit" class="w-full rounded-lg bg-gray-800 px-4 py-2 font-medium text-white hover:bg-gray-900">Merge</button>
                    </form>
                @endif
            </section>

            @if($brand->products_count === 0)
                <section class="rounded-lg bg-white p-6 shadow text-sm">
                    <h2 class="text-base font-semibold text-gray-900">Delete</h2>
                    <p class="mt-1 text-gray-600">No product uses this brand, so it can be deleted.</p>
                    <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}" class="mt-3" onsubmit="return confirm('Delete “{{ addslashes($brand->name) }}”?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg border border-red-300 px-4 py-2 font-medium text-red-700 hover:bg-red-50">Delete brand</button>
                    </form>
                </section>
            @endif
        </div>
    </div>
</div>
@endsection
