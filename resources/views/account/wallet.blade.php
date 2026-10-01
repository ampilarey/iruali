@extends('layouts.app')

@section('title', __('Wallet'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-dark mb-8">{{ __('Wallet') }}</h1>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <aside class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <p class="text-sm text-gray-500">{{ __('Wallet balance') }}</p>
                    <p class="text-3xl font-bold text-dark" dir="ltr">{{ \App\Support\Money::format($user->wallet_balance) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Store credit from refunds and gift cards. Tick "Use wallet balance" at checkout to spend it.') }}</p>
                    <nav class="mt-6 space-y-1" aria-label="{{ __('Account') }}">
                        <a href="{{ route('account') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('Profile') }}</a>
                        <a href="{{ route('orders') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('My Orders') }}</a>
                        <a href="{{ route('account.rewards') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('Rewards') }}</a>
                        <a href="{{ route('account.wallet') }}" class="block px-4 py-2 text-primary bg-primary/10 rounded-lg font-medium" aria-current="page">{{ __('Wallet') }}</a>
                    </nav>
                </div>
            </aside>

            <div class="lg:col-span-2 space-y-6">
                <section class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <h2 class="text-xl font-semibold text-dark">{{ __('Redeem a gift card') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Enter the code from the gift card email. The amount goes into your wallet.') }}</p>
                    <form method="POST" action="{{ route('account.wallet.redeem') }}" class="mt-4 flex flex-wrap gap-2">
                        @csrf
                        <label for="code" class="sr-only">{{ __('Gift card code') }}</label>
                        <input id="code" name="code" required maxlength="30" dir="ltr" placeholder="IRU-XXXX-XXXX-XXXX" value="{{ old('code') }}" class="flex-1 min-w-[12rem] rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono uppercase focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('code') border-red-500 @enderror">
                        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Redeem') }}</button>
                    </form>
                    @error('code')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-3 text-sm"><a href="{{ route('gift-cards') }}" class="text-primary hover:underline">{{ __('Buy a gift card for someone') }}</a></p>
                </section>

                <section class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <h2 class="text-xl font-semibold text-dark mb-4">{{ __('Wallet history') }}</h2>
                    @if($history->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('Nothing here yet. Refunds you choose to keep as store credit and redeemed gift cards show up here.') }}</p>
                    @else
                        <ul class="divide-y divide-gray-100 text-sm">
                            @foreach($history as $row)
                                <li class="py-2 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-gray-900">{{ \App\Models\WalletTransaction::typeLabel($row->type) }}@if($row->order) · <a href="{{ route('orders.show', $row->order) }}" class="text-primary hover:underline" dir="ltr">{{ $row->order->order_number }}</a>@endif</p>
                                        <p class="text-xs text-gray-500">{{ $row->created_at->translatedFormat('j M Y, H:i') }}@if($row->note) · {{ $row->note }}@endif @if($row->reference) · <span dir="ltr">{{ $row->reference }}</span>@endif</p>
                                    </div>
                                    <span class="shrink-0 font-semibold {{ $row->amount < 0 ? 'text-coral' : 'text-success' }}" dir="ltr">{{ $row->amount > 0 ? '+' : '−' }}{{ \App\Support\Money::format(abs((float) $row->amount)) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4">{{ $history->links() }}</div>
                    @endif
                </section>

                @if($giftCardsBought->isNotEmpty())
                    <section class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                        <h2 class="text-xl font-semibold text-dark mb-4">{{ __('Gift cards you bought') }}</h2>
                        <ul class="divide-y divide-gray-100 text-sm">
                            @foreach($giftCardsBought as $card)
                                <li class="py-2 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-gray-900" dir="ltr">{{ \App\Support\Money::format($card->amount) }} → {{ $card->recipient_email }}</p>
                                        @php $cardStatus = ['pending' => __('Waiting for payment'), 'active' => __('Sent'), 'redeemed' => __('Redeemed'), 'expired' => __('Expired'), 'cancelled' => __('Cancelled')][$card->status] ?? $card->status; @endphp
                                        <p class="text-xs text-gray-500">{{ $card->created_at->translatedFormat('j M Y') }} · {{ $cardStatus }}</p>
                                    </div>
                                    @if($card->order && $card->status === 'pending')
                                        <a href="{{ route('orders.show', $card->order) }}" class="shrink-0 text-sm font-semibold text-primary hover:underline">{{ __('Pay now') }}</a>
                                    @endif
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
