@extends('layouts.app')

@section('title', __('Rewards'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-dark mb-8">{{ __('Rewards') }}</h1>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <aside class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <p class="text-sm text-gray-500">{{ __('Loyalty Points') }}</p>
                    <p class="text-4xl font-bold text-dark" dir="ltr">{{ max(0, (int) $user->loyalty_points) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Each point is worth MVR 1 at checkout.') }}</p>
                    @if($expiringSoon > 0)
                        <p class="mt-3 rounded-lg bg-sun-soft px-3 py-2 text-xs text-sun-ink">{{ __(':points points expire within a month. Use them soon!', ['points' => $expiringSoon]) }}</p>
                    @elseif($expiryMonths > 0)
                        <p class="mt-3 text-xs text-gray-500">{{ __('Points expire :months months after you earn them.', ['months' => $expiryMonths]) }}</p>
                    @endif
                    <nav class="mt-6 space-y-1" aria-label="{{ __('Account') }}">
                        <a href="{{ route('account') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('Profile') }}</a>
                        <a href="{{ route('orders') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('My Orders') }}</a>
                        <a href="{{ route('account.rewards') }}" class="block px-4 py-2 text-primary bg-primary/10 rounded-lg font-medium" aria-current="page">{{ __('Rewards') }}</a>
                        @if(\Illuminate\Support\Facades\Route::has('account.wallet'))
                            <a href="{{ route('account.wallet') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('Wallet') }}</a>
                        @endif
                    </nav>
                </div>
            </aside>

            <div class="lg:col-span-2 space-y-6">
                <section class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <h2 class="text-xl font-semibold text-dark">{{ __('Invite friends, earn points') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Share your link. When a friend signs up and pays for their first order, you get :referrer points and they get :referee.', ['referrer' => (int) \App\Models\Setting::get('referral_referrer_points'), 'referee' => (int) \App\Models\Setting::get('referral_referee_points')]) }}</p>

                    <dl class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="text-gray-500">{{ __('Your referral code') }}</dt>
                            <dd class="mt-1 font-mono text-lg font-bold tracking-widest text-dark" dir="ltr">{{ $user->referral_code }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500">{{ __('Your link') }}</dt>
                            <dd class="mt-1 flex flex-wrap gap-2">
                                <input id="referral-link" type="text" readonly value="{{ $shareUrl }}" dir="ltr" class="flex-1 min-w-0 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm">
                                <button type="button" data-copy="#referral-link" data-copied="{{ __('Copied!') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Copy link') }}</button>
                                <a href="https://wa.me/?text={{ urlencode($shareText.' '.$shareUrl) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-lg bg-[#128C7E] px-3 py-2 text-sm font-semibold text-white hover:bg-[#0f7a6d]">{{ __('Share on WhatsApp') }}</a>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <h2 class="text-xl font-semibold text-dark mb-4">{{ __('Your referrals') }}</h2>
                    @if($referrals->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('Nobody has signed up with your link yet.') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-xs uppercase tracking-wider text-gray-500">
                                    <tr><th class="py-2 text-start">{{ __('Friend') }}</th><th class="py-2 text-start">{{ __('Signed up') }}</th><th class="py-2 text-start">{{ __('Status') }}</th><th class="py-2 text-end">{{ __('Points') }}</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($referrals as $row)
                                        <tr>
                                            <td class="py-2 text-gray-900">{{ $row['name'] }}</td>
                                            <td class="py-2 text-gray-600">{{ $row['signed_up_at']->translatedFormat('j M Y') }}</td>
                                            <td class="py-2">
                                                @if($row['status'] === 'rewarded')
                                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">{{ __('Reward given') }}</span>
                                                @elseif($row['status'] === 'paid')
                                                    <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800">{{ __('First order paid') }}</span>
                                                @else
                                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ __('Signed up') }}</span>
                                                @endif
                                                @if($row['status_at'])<span class="block text-xs text-gray-500">{{ $row['status_at']->translatedFormat('j M Y') }}</span>@endif
                                            </td>
                                            <td class="py-2 text-end font-medium" dir="ltr">{{ $row['points'] > 0 ? '+'.$row['points'] : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <h2 class="text-xl font-semibold text-dark mb-4">{{ __('Points history') }}</h2>
                    @if($history->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('No points yet. Every MVR :amount you spend earns a point once your order is paid.', ['amount' => (int) \App\Models\Setting::get('loyalty_spend_per_point')]) }}</p>
                    @else
                        <ul class="divide-y divide-gray-100 text-sm">
                            @foreach($history as $row)
                                <li class="py-2 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-gray-900">{{ \App\Models\PointsTransaction::typeLabel($row->type) }}@if($row->order) · <a href="{{ route('orders.show', $row->order) }}" class="text-primary hover:underline" dir="ltr">{{ $row->order->order_number }}</a>@endif</p>
                                        <p class="text-xs text-gray-500">{{ $row->created_at->translatedFormat('j M Y, H:i') }}@if($row->note) · {{ $row->note }}@endif</p>
                                    </div>
                                    <span class="shrink-0 font-semibold {{ $row->points < 0 ? 'text-coral' : 'text-success' }}" dir="ltr">{{ $row->points > 0 ? '+' : '' }}{{ $row->points }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4">{{ $history->links() }}</div>
                    @endif
                </section>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.querySelector(button.dataset.copy);
            if (! input) return;
            var done = function () { var label = button.textContent; button.textContent = button.dataset.copied; setTimeout(function () { button.textContent = label; }, 1500); };
            if (navigator.clipboard) { navigator.clipboard.writeText(input.value).then(done); } else { input.select(); document.execCommand('copy'); done(); }
        });
    });
</script>
@endsection
