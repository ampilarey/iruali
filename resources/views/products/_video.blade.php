{{--
    The product's video (App\Support\ProductVideo). Click to load: a placeholder with a play button
    comes first and the provider's player (an iframe) is only created when the shopper presses play,
    so nothing is requested from YouTube, TikTok, Instagram or Facebook before they choose to watch.
--}}
@php $video = \App\Support\ProductVideo::fromProduct($product); @endphp
@if($video)
    @php
        $videoFrame = match ($video->aspect()) {
            'portrait' => 'aspect-[9/16] w-full max-w-[18rem]',
            'post' => 'aspect-[4/5] w-full max-w-sm',
            default => 'aspect-video w-full',
        };
    @endphp
    <section class="mt-4" aria-label="{{ __('Product video') }}" data-product-video="{{ $video->provider }}">
        <div class="relative mx-auto overflow-hidden rounded-xl bg-reef {{ $videoFrame }}">
            <button type="button" data-video-play data-src="{{ $video->embedUrl() }}" data-title="{{ __('Video: :product', ['product' => $product->name]) }}"
                    class="group absolute inset-0 flex h-full w-full flex-col items-center justify-center gap-3 p-4 text-center text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-white/70">
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-white/95 text-reef shadow-lg transition-transform group-hover:scale-105" aria-hidden="true">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor"><path d="M9 6.5v11l9-5.5-9-5.5z"/></svg>
                </span>
                <span class="font-semibold">{{ __('Play the video') }}</span>
                <span class="max-w-xs text-xs text-white/80">{{ __('From :provider. Nothing loads from :provider until you press play.', ['provider' => $video->providerName()]) }}</span>
            </button>
        </div>
        <noscript>
            <p class="mt-2 text-center text-sm"><a href="{{ $video->watchUrl() }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-primary hover:underline">{{ __('Watch the video on :provider', ['provider' => $video->providerName()]) }}</a></p>
        </noscript>
    </section>
    @once
        @push('scripts')
            <script>
                // Replace the placeholder with the player only when asked (no third-party request before)
                document.querySelectorAll('[data-video-play]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var frame = document.createElement('iframe');
                        frame.src = button.dataset.src;
                        frame.title = button.dataset.title;
                        frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
                        frame.setAttribute('allowfullscreen', '');
                        frame.referrerPolicy = 'strict-origin-when-cross-origin';
                        frame.className = 'absolute inset-0 h-full w-full border-0';
                        button.replaceWith(frame);
                        frame.focus();
                    }, { once: true });
                });
            </script>
        @endpush
    @endonce
@endif
