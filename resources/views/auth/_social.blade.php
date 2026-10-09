{{-- "Continue with Google / Facebook / Apple": only the providers whose keys are set (SocialLoginService) --}}
@php $socialProviders = app(\App\Services\SocialLoginService::class)->enabled(); @endphp
@if($socialProviders !== [] || $errors->has('social'))
    <div class="space-y-3" data-social-login>
        @error('social')
            <p role="alert" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
        @enderror
        @if($socialProviders !== [])
            <div class="flex items-center gap-3 text-sm text-gray-500" aria-hidden="true">
                <span class="h-px flex-1 bg-gray-200"></span>{{ __('or') }}<span class="h-px flex-1 bg-gray-200"></span>
            </div>
            @foreach($socialProviders as $socialProvider)
                @php
                    $socialName = $socialProvider->name();
                    $socialStyle = [
                        'google' => 'border-gray-300 bg-white text-gray-800 hover:bg-gray-50',
                        'facebook' => 'border-[#1877F2] bg-[#1877F2] text-white hover:bg-[#166FE5]',
                        'apple' => 'border-black bg-black text-white hover:bg-gray-900',
                    ][$socialName] ?? 'border-gray-300 bg-white text-gray-800';
                @endphp
                <a href="{{ route('social.redirect', ['provider' => $socialName]) }}" data-social="{{ $socialName }}"
                   class="flex w-full items-center justify-center gap-3 rounded-md border px-4 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 {{ $socialStyle }}">
                    @if($socialName === 'google')
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.3 0-9.7-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C36.9 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
                    @elseif($socialName === 'facebook')
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
                    @else
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.37 1.43c0 1.14-.42 2.2-1.25 3.17-1 1.17-2.21 1.85-3.52 1.74a3.5 3.5 0 01-.03-.43c0-1.1.48-2.27 1.33-3.24.42-.49.96-.89 1.62-1.21.65-.32 1.27-.49 1.85-.52.02.17.02.33.02.49zm4.43 15.8c-.33.77-.73 1.48-1.19 2.13-.62.89-1.13 1.5-1.52 1.84-.61.56-1.27.85-1.97.87-.5 0-1.11-.14-1.82-.43-.71-.29-1.36-.43-1.96-.43-.63 0-1.3.14-2.02.43-.72.29-1.3.44-1.74.46-.67.03-1.34-.27-2.01-.89-.43-.37-.96-1.01-1.6-1.92-.69-.96-1.25-2.08-1.69-3.36-.47-1.38-.71-2.71-.71-4 0-1.48.32-2.76.96-3.83a5.64 5.64 0 012.01-2.03 5.4 5.4 0 012.72-.77c.53 0 1.23.17 2.1.49.86.33 1.42.49 1.66.49.18 0 .8-.19 1.86-.58 1-.36 1.84-.51 2.53-.45 1.87.15 3.28.89 4.21 2.22-1.68 1.02-2.5 2.44-2.48 4.26.01 1.42.53 2.6 1.54 3.54.46.43.97.77 1.54 1.01-.12.36-.25.7-.39 1.03z"/></svg>
                    @endif
                    <span>{{ __('Continue with :provider', ['provider' => $socialProvider->label()]) }}</span>
                </a>
            @endforeach
            <p class="text-center text-xs text-gray-500">{{ __('We only receive your name and email address from them. Staff accounts sign in with their password.') }}</p>
        @endif
    </div>
@endif
