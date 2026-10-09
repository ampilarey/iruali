<x-mail::message>
# {{ $name ? __('Hello :name,', ['name' => $name]) : __('Hello,') }}

@foreach($paragraphs as $paragraph)
<p>{!! nl2br(e($paragraph)) !!}</p>
@endforeach

@if(($sections['new_arrivals'] ?? null) && $sections['new_arrivals']->isNotEmpty())
## {{ trans_choice('New in the last day|New in the last :count days', $sections['new_arrival_days'], ['count' => $sections['new_arrival_days']]) }}

@include('mail._product-rows', ['products' => $sections['new_arrivals']])

[{{ __('See everything new') }}]({{ route('shop', ['sort' => 'newest']) }})

@endif
@if(($sections['deals'] ?? null) && $sections['deals']->isNotEmpty())
## {{ __('Deals right now') }}

@include('mail._product-rows', ['products' => $sections['deals']])

[{{ __('See all deals') }}]({{ route('deals') }})

@endif
@if($sections['campaign'] ?? null)
## {{ $sections['campaign']->headline }}

@if(filled($sections['campaign']->subheadline))
<p>{{ $sections['campaign']->subheadline }}</p>
@endif
@if($sections['campaign_products']->isNotEmpty())
@include('mail._product-rows', ['products' => $sections['campaign_products']])
@endif

[{{ filled($sections['campaign']->cta_text) ? $sections['campaign']->cta_text : __('Shop the sale') }}]({{ route('campaigns.show', $sections['campaign']) }})

@endif
@if(($sections['brands'] ?? null) && $sections['brands']->isNotEmpty())
## {{ __('Brands to discover') }}

<p>@foreach($sections['brands'] as $brand)<a href="{{ route('brands.show', $brand) }}">{{ $brand->localizedName() }}</a>{{ $loop->last ? '' : ' · ' }}@endforeach</p>

@endif
<x-mail::button :url="route('home')">
{{ __('Shop on iruali') }}
</x-mail::button>

{{ __('The iruali team') }}

<small style="color: #718096;">{{ __('You get this email because you signed up for the iruali newsletter or have an iruali account.') }} <a href="{{ $unsubscribeUrl }}">{{ __('Unsubscribe') }}</a></small>
</x-mail::message>
