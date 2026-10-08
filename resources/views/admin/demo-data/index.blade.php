@extends('layouts.app')

@section('title', __('Sample data'))

@section('content')
@php
    $field = 'mt-1 block w-full max-w-xs rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
    $removedElsewhere = max(0, $status['products_removed'] - $status['restorable_products']);
@endphp
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => __('Sample data'), 'back' => route('admin.settings')])

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <p class="text-sm text-gray-600">{{ __('The test site was filled with sample shops and products so there was something to click on. When the real shops are ready, take the sample data off the site here. Nothing is deleted for good: Restore puts it back.') }}</p>

        <dl class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-white p-4 shadow">
                <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Demo shops') }}</dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900 tabular-nums" data-count="shops">{{ $status['shops'] }}</dd>
                <dd class="text-xs text-gray-500" data-count="shops_open">{{ __(':count still open', ['count' => $status['shops_open']]) }}</dd>
            </div>
            <div class="rounded-lg bg-white p-4 shadow">
                <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Sample products still on the site') }}</dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900 tabular-nums" data-count="products_live">{{ $status['products_live'] }}</dd>
            </div>
            <div class="rounded-lg bg-white p-4 shadow">
                <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Sample products removed') }}</dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900 tabular-nums" data-count="products_removed">{{ $status['products_removed'] }}</dd>
            </div>
        </dl>

        @if(! $status['can_remove'])
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" data-state="nothing-to-remove">
                @if($status['shops'] === 0 && $status['products_removed'] === 0 && ! $status['can_restore'])
                    {{ __('There is no sample data on this site, so there is nothing to remove.') }}
                @else
                    {{ __('The sample data is already off the site. There is nothing left to remove.') }}
                @endif
            </div>
        @else
            <section class="rounded-lg bg-white p-6 shadow" aria-labelledby="remove-heading">
                <h2 id="remove-heading" class="text-lg font-semibold text-gray-900">{{ __('Remove sample data') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ __('What happens when you remove it:') }}</p>
                <ul class="mt-2 list-disc space-y-1.5 ps-5 text-sm text-gray-700">
                    <li>{{ trans_choice(':count sample product is taken off the site (hidden, not deleted for good).|:count sample products are taken off the site (hidden, not deleted for good).', $status['products_live'], ['count' => $status['products_live']]) }}</li>
                    @if($status['shops'] > 0)
                        <li>
                            {{ __('The demo shops are closed, as Suspend on the Sellers page does: their shop pages go and they can no longer sell or use the Seller Centre. Their accounts are kept.') }}
                            <span class="text-gray-500">({{ implode(', ', $status['shop_names']) }})</span>
                        </li>
                    @endif
                    @if($status['other_products_live'] > 0)
                        <li>{{ trans_choice(':count other product listed by these shops is switched off with them.|:count other products listed by these shops are switched off with them.', $status['other_products_live'], ['count' => $status['other_products_live']]) }}</li>
                    @endif
                    <li>{{ __("Sample products are taken out of customers' carts, saved items, wishlists and stock alerts, and out of campaigns.") }}</li>
                    <li>{{ __('Orders that already contain sample products are not changed: customers, shops and admins can still open them, print receipts and see payouts.') }}</li>
                    <li>{{ __('The sitemap and the product feeds are refreshed.') }}</li>
                </ul>

                <form method="POST" action="{{ route('admin.sample-data.remove') }}" class="mt-5 space-y-3">
                    @csrf
                    <div>
                        <label for="confirm" class="block text-sm font-medium text-gray-700">{{ __('Type REMOVE to confirm') }}</label>
                        <input id="confirm" name="confirm" required autocomplete="off" spellcheck="false" dir="ltr" class="{{ $field }}" aria-describedby="confirm-hint">
                        <p id="confirm-hint" class="mt-1 text-xs text-gray-500">{{ __('Capital letters, exactly as shown.') }}</p>
                        @error('confirm')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('Remove sample data') }}</button>
                </form>
            </section>
        @endif

        @if($status['can_restore'])
            <section class="rounded-lg bg-white p-6 shadow" aria-labelledby="restore-heading">
                <h2 id="restore-heading" class="text-lg font-semibold text-gray-900">{{ __('Restore sample data') }}</h2>
                @if($status['removed_at'])
                    <p class="mt-1 text-sm text-gray-600">{{ __('The sample data was removed on :date.', ['date' => \Illuminate\Support\Carbon::parse($status['removed_at'])->format('d M Y H:i')]) }}</p>
                @endif
                <p class="mt-2 text-sm text-gray-700">{{ __('Restore brings back exactly what was removed: the products, the demo shops as they were, and anything switched off with them.') }} {{ trans_choice(':count product can be restored.|:count products can be restored.', $status['restorable_products'], ['count' => $status['restorable_products']]) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ __("Customers' carts, saved items, wishlists and stock alerts are not refilled.") }}</p>
                <form method="POST" action="{{ route('admin.sample-data.restore') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Restore sample data') }}</button>
                </form>
            </section>
        @endif

        @if($removedElsewhere > 0)
            <p class="text-sm text-gray-600">{{ trans_choice(':count sample product was removed some other way (for example by the old clean-up seeder). Restore leaves it as it is.|:count sample products were removed some other way (for example by the old clean-up seeder). Restore leaves them as they are.', $removedElsewhere, ['count' => $removedElsewhere]) }}</p>
        @endif

        <div class="space-y-1 text-xs text-gray-500">
            <p>{{ __('On the server: php artisan demo:remove and php artisan demo:restore do the same (add --force to skip the question).') }}</p>
            <p>{{ __('The smoke test after each deploy orders the cheapest product on sale, so keep at least one real product on sale once the sample data is gone.') }}</p>
        </div>
    </div>
</div>
@endsection
