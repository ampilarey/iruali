{{-- The product's optional video: a link, kept as provider + id (App\Support\ProductVideo) --}}
@php
    $savedVideo = \App\Support\ProductVideo::fromProduct($product);
@endphp
<fieldset class="rounded-lg border border-gray-200 p-4">
    <legend class="px-1 text-sm font-medium text-gray-700">{{ __('Video') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></legend>
    <label for="video_url" class="block text-sm text-gray-700">{{ __('Video link') }}</label>
    <input id="video_url" name="video_url" type="text" inputmode="url" dir="ltr" maxlength="500" autocomplete="off" spellcheck="false"
           placeholder="https://www.youtube.com/watch?v=…"
           value="{{ old('video_url', $savedVideo?->watchUrl()) }}"
           aria-describedby="video_url-hint"
           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500 @error('video_url') border-red-500 @enderror">
    <p id="video_url-hint" class="mt-1 text-xs text-gray-500">{{ __('A link to a video on YouTube (also Shorts), TikTok, Instagram (post or reel) or Facebook. It shows on the product page and only plays when a shopper presses play. Leave empty for no video.') }}</p>
    @error('video_url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    @if($savedVideo && ! $errors->has('video_url'))
        <p class="mt-2 text-xs text-gray-600">{{ __('Now showing a :provider video.', ['provider' => $savedVideo->providerName()]) }}
            <a href="{{ $savedVideo->watchUrl() }}" target="_blank" rel="noopener noreferrer" class="font-medium text-primary-600 hover:underline">{{ __('Open it') }}</a></p>
    @endif
</fieldset>
