{{-- Google, Facebook and Apple accounts this customer signs in with (SocialLoginController) --}}
@php
    $socialAccounts = $user->socialAccounts()->orderBy('id')->get();
    $canUnlink = $user->hasPassword() || $socialAccounts->count() > 1;
@endphp
@if($socialAccounts->isNotEmpty() || ! $user->hasPassword() || $errors->has('social'))
    <div id="connected-accounts" class="border-t border-gray-100 pt-5 space-y-3">
        <div>
            <h3 class="font-medium text-gray-900">{{ __('Connected accounts') }}</h3>
            <p class="text-sm text-gray-600">{{ __('You can use these to sign in to iruali.') }}</p>
        </div>
        @error('social')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
        @if($socialAccounts->isNotEmpty())
            <ul class="divide-y divide-gray-100 rounded-lg border border-gray-100">
                @foreach($socialAccounts as $socialAccount)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3" data-social-account="{{ $socialAccount->provider }}">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">{{ $socialAccount->providerLabel() }}</p>
                            <p class="text-sm text-gray-500 truncate">@if($socialAccount->email)<span dir="ltr">{{ $socialAccount->email }}</span> · @endif{{ __('Connected :date', ['date' => $socialAccount->created_at?->translatedFormat('j F Y')]) }}</p>
                        </div>
                        @if($canUnlink)
                            <form method="POST" action="{{ route('account.social.unlink', ['account' => $socialAccount->id]) }}" onsubmit="return confirm(@js(__('Stop signing in with :provider?', ['provider' => $socialAccount->providerLabel()])))">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Unlink') }}</button>
                            </form>
                        @else
                            <span class="text-xs text-gray-500">{{ __('Your only way to sign in') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        @unless($user->hasPassword())
            <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700">
                <p>{{ __('Your account has no password yet. Set one to sign in with your email too; you need one to unlink your last connected account.') }}</p>
                <form method="POST" action="{{ route('account.password.link') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Email me a link to set a password') }}</button>
                </form>
            </div>
        @endunless
    </div>
@endif
