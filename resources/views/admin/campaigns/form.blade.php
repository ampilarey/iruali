@extends('layouts.app')

@php
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
    $editing = $campaign->exists;
    $t = fn ($attr, $locale) => old($attr.'_'.$locale, $campaign->getTranslation($attr, $locale, false));
@endphp

@section('title', $editing ? 'Edit campaign' : 'New campaign')

@section('content')
<div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold">{{ $editing ? 'Edit campaign: '.$campaign->name : 'New campaign' }}</h1>
        <div class="flex gap-2">
            @if($editing && $campaign->isJoinable())
                <a href="{{ route('campaigns.show', $campaign) }}" target="_blank" class="bg-white border border-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">View page</a>
            @endif
            <a href="{{ route('admin.campaigns.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">All campaigns</a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $editing ? route('admin.campaigns.update', $campaign) : route('admin.campaigns.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if($editing) @method('PUT') @endif

        <section class="rounded-lg bg-white p-6 shadow grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name (internal) *</label>
                <input id="name" name="name" required maxlength="120" class="{{ $field }}" value="{{ old('name', $campaign->name) }}">
            </div>
            <div>
                <label for="slug" class="block text-sm font-medium text-gray-700">URL slug</label>
                <input id="slug" name="slug" maxlength="140" placeholder="eid-sale" class="{{ $field }}" value="{{ old('slug', $campaign->slug) }}">
                <p class="mt-1 text-xs text-gray-500">The page lives at /campaigns/&lt;slug&gt;. Left empty, it is made from the name.</p>
            </div>
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700">Type *</label>
                <select id="type" name="type" class="{{ $field }}">
                    @foreach(\App\Models\Campaign::TYPES as $type)<option value="{{ $type }}" @selected(old('type', $campaign->type) === $type)>{{ ucfirst($type) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="placement" class="block text-sm font-medium text-gray-700">Placement *</label>
                <select id="placement" name="placement" class="{{ $field }}">
                    @foreach(\App\Models\Campaign::PLACEMENTS as $placement)<option value="{{ $placement }}" @selected(old('placement', $campaign->placement) === $placement)>{{ ['home_hero' => 'Home page hero', 'home_strip' => 'Home page strip', 'category' => 'Category pages strip'][$placement] }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="starts_at" class="block text-sm font-medium text-gray-700">Starts *</label>
                <input id="starts_at" name="starts_at" type="datetime-local" required class="{{ $field }}" value="{{ old('starts_at', $campaign->starts_at?->format('Y-m-d\TH:i')) }}">
            </div>
            <div>
                <label for="ends_at" class="block text-sm font-medium text-gray-700">Ends *</label>
                <input id="ends_at" name="ends_at" type="datetime-local" required class="{{ $field }}" value="{{ old('ends_at', $campaign->ends_at?->format('Y-m-d\TH:i')) }}">
            </div>
            <div>
                <label for="discount_percent" class="block text-sm font-medium text-gray-700">Minimum discount shops must give (%)</label>
                <input id="discount_percent" name="discount_percent" type="number" step="0.01" min="0" max="90" class="{{ $field }}" value="{{ old('discount_percent', $campaign->discount_percent) }}">
                <p class="mt-1 text-xs text-gray-500">Also the discount used when a shop gives none of its own. Leave empty for an event with no price rule.</p>
            </div>
            <div>
                <label for="sort_order" class="block text-sm font-medium text-gray-700">Order</label>
                <input id="sort_order" name="sort_order" type="number" min="0" class="{{ $field }}" value="{{ old('sort_order', $campaign->sort_order ?? 0) }}">
                <p class="mt-1 text-xs text-gray-500">Lower comes first when several campaigns are live.</p>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 sm:col-span-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $campaign->is_active)) class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                Switched on (shown and open to shops within its dates)
            </label>
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-lg font-semibold text-gray-900">Banner</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="headline_en" class="block text-sm font-medium text-gray-700">Headline (English) *</label>
                    <input id="headline_en" name="headline_en" required maxlength="120" class="{{ $field }}" value="{{ $t('headline', 'en') }}">
                </div>
                <div>
                    <label for="headline_dv" class="block text-sm font-medium text-gray-700">Headline (Dhivehi)</label>
                    <input id="headline_dv" name="headline_dv" maxlength="120" dir="rtl" class="{{ $field }}" value="{{ $t('headline', 'dv') }}">
                </div>
                <div>
                    <label for="subheadline_en" class="block text-sm font-medium text-gray-700">Subheadline (English)</label>
                    <input id="subheadline_en" name="subheadline_en" maxlength="200" class="{{ $field }}" value="{{ $t('subheadline', 'en') }}">
                </div>
                <div>
                    <label for="subheadline_dv" class="block text-sm font-medium text-gray-700">Subheadline (Dhivehi)</label>
                    <input id="subheadline_dv" name="subheadline_dv" maxlength="200" dir="rtl" class="{{ $field }}" value="{{ $t('subheadline', 'dv') }}">
                </div>
                <div>
                    <label for="cta_text_en" class="block text-sm font-medium text-gray-700">Button text (English)</label>
                    <input id="cta_text_en" name="cta_text_en" maxlength="40" class="{{ $field }}" value="{{ $t('cta_text', 'en') }}">
                </div>
                <div>
                    <label for="cta_text_dv" class="block text-sm font-medium text-gray-700">Button text (Dhivehi)</label>
                    <input id="cta_text_dv" name="cta_text_dv" maxlength="40" dir="rtl" class="{{ $field }}" value="{{ $t('cta_text', 'dv') }}">
                </div>
                <div>
                    <label for="cta_url" class="block text-sm font-medium text-gray-700">Button link</label>
                    <input id="cta_url" name="cta_url" maxlength="255" placeholder="Leave empty for the campaign page" class="{{ $field }}" value="{{ old('cta_url', $campaign->cta_url) }}">
                </div>
                <div>
                    <label for="theme_colour" class="block text-sm font-medium text-gray-700">Theme colour *</label>
                    <input id="theme_colour" name="theme_colour" type="color" class="mt-1 h-10 w-20 rounded border border-gray-300" value="{{ old('theme_colour', $campaign->theme_colour ?: '#0E7C86') }}">
                </div>
                <div class="sm:col-span-2">
                    <label for="banner_image" class="block text-sm font-medium text-gray-700">Banner image</label>
                    <input id="banner_image" name="banner_image" type="file" accept="image/*" class="mt-1 block w-full text-sm text-gray-700">
                    <p class="mt-1 text-xs text-gray-500">JPG, PNG or WebP up to 4 MB; shown behind the headline (wide images work best).</p>
                    @if($campaign->banner_image)
                        <div class="mt-3 flex items-center gap-4">
                            <img src="{{ $campaign->banner_image }}" alt="" class="h-20 w-40 rounded object-cover border border-gray-200">
                            <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="remove_banner" value="1" class="rounded border-gray-300"> Remove image</label>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ $editing ? 'Save campaign' : 'Create campaign' }}</button>
        </div>
    </form>

    @if($editing)
        <section class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-lg font-semibold text-gray-900">Products from shops</h2>
            <p class="text-sm text-gray-500">Approve a product and its campaign price goes live on the site and at checkout while the campaign runs.</p>
            @if($campaign->participations->isEmpty())
                <p class="mt-4 text-sm text-gray-500">No shop has joined yet. Shops join from Seller Centre → Campaigns.</p>
            @else
                <table class="mt-4 w-full text-sm">
                    <thead><tr class="bg-gray-100"><th class="p-2 text-start">Product</th><th class="p-2 text-start">Shop</th><th class="p-2 text-end">List price</th><th class="p-2 text-end">Discount</th><th class="p-2 text-end">Campaign price</th><th class="p-2 text-start">Status</th><th class="p-2"></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($campaign->participations->sortBy('approved_at') as $row)
                            @php $base = (float) ($row->product->sale_price ?? $row->product->price); $pct = $campaign->discountFor($row); @endphp
                            <tr>
                                <td class="p-2">{{ $row->product->name }}</td>
                                <td class="p-2">{{ $row->seller?->business_name ?: $row->seller?->name }}</td>
                                <td class="p-2 text-end">{{ \App\Support\Money::format($base) }}</td>
                                <td class="p-2 text-end">{{ rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.') }}%</td>
                                <td class="p-2 text-end font-medium">{{ \App\Support\Money::format(round($base * (1 - $pct / 100), 2)) }}</td>
                                <td class="p-2"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $row->isApproved() ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ $row->isApproved() ? 'Approved '.$row->approved_at->format('d M') : 'Pending' }}</span></td>
                                <td class="p-2 text-end whitespace-nowrap">
                                    @unless($row->isApproved())
                                        <form method="POST" action="{{ route('admin.campaigns.approve', [$campaign, $row]) }}" class="inline">@csrf<button class="text-green-700 hover:underline">Approve</button></form>
                                    @endunless
                                    <form method="POST" action="{{ route('admin.campaigns.reject', [$campaign, $row]) }}" class="inline ms-2" onsubmit="return confirm('Remove this product from the campaign?');">@csrf @method('DELETE')<button class="text-red-600 hover:underline">Remove</button></form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endif
</div>
@endsection
