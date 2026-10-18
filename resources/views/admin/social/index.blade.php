@extends('layouts.admin')

@section('title', 'Social posts')

@php $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm'; @endphp

@section('content')
<div class="max-w-5xl mx-auto py-6 px-4 space-y-4">
    <div>
        <h1 class="text-xl font-bold lg:text-2xl">Social posts</h1>
        <p class="text-sm text-gray-500">Posts for iruali's Facebook page, Instagram and Telegram channel. Nothing goes out until an admin approves it.</p>
    </div>
    @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>@endif

    <x-admin.fold title="Channels and settings" body="space-y-3 text-sm" :open="$channels === []" id="social-settings">
        <ul class="space-y-1">
            @foreach(\App\Models\SocialPost::CHANNELS as $key => $label)
                <li>{{ $label }}: @if(isset($channels[$key]))<span class="font-semibold text-green-700">ready</span>@else<span class="text-gray-500">not set up</span>@endif</li>
            @endforeach
        </ul>
        <p class="text-xs text-gray-500">Facebook and Instagram: FACEBOOK_PAGE_ID, FACEBOOK_PAGE_TOKEN (a long-lived page token) and INSTAGRAM_ACCOUNT_ID in the server's .env. Telegram: the team-alerts bot, made an admin of the channel, and the channel below.</p>
        <form method="POST" action="{{ route('admin.social.settings') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @csrf @method('PUT')
            <div><label for="tg-channel" class="block text-xs font-medium text-gray-700">Telegram channel (@name or id)</label>
                <input id="tg-channel" name="telegram_channel_id" value="{{ old('telegram_channel_id', $telegramChannel) }}" placeholder="@iruali_deals" class="{{ $field }}">
                @error('telegram_channel_id')<p class="mt-1 text-xs text-red-700">Use @channelname or the numeric id.</p>@enderror</div>
            <label class="flex items-start gap-2 sm:pt-5"><input type="hidden" name="social_auto_drafts" value="0"><input type="checkbox" name="social_auto_drafts" value="1" @checked($autoDrafts) class="mt-1"> <span>Draft a post when a new product is approved</span></label>
            <div class="sm:col-span-2"><button class="rounded-lg border border-gray-300 px-4 py-2 font-semibold hover:bg-gray-50">Save</button></div>
        </form>
    </x-admin.fold>

    <form method="POST" action="{{ route('admin.social.store') }}">
        @csrf
        <x-admin.fold title="New post" body="space-y-3 text-sm" :open="$errors->any()" id="social-new">
            <div><label for="s-message" class="block text-xs font-medium text-gray-700">Text</label><textarea id="s-message" name="message" rows="3" required maxlength="2000" class="{{ $field }}">{{ old('message') }}</textarea></div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div><label for="s-link" class="block text-xs font-medium text-gray-700">Link</label><input id="s-link" name="link" type="url" value="{{ old('link') }}" class="{{ $field }}" placeholder="https://iruali.mv/deals"></div>
                <div><label for="s-image" class="block text-xs font-medium text-gray-700">Picture URL (needed for Instagram)</label><input id="s-image" name="image_url" type="url" value="{{ old('image_url') }}" class="{{ $field }}"></div>
            </div>
            <div class="flex flex-wrap gap-4">@foreach(\App\Models\SocialPost::CHANNELS as $key => $label)<label class="flex items-center gap-2"><input type="checkbox" name="channels[]" value="{{ $key }}" @checked(isset($channels[$key]))> {{ $label }}</label>@endforeach</div>
            <button class="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">Save draft</button>
        </x-admin.fold>
    </form>

    <x-admin.fold title="Waiting for approval" :open="true" body="space-y-3 text-sm" id="social-pending">
        <x-slot:badge><span class="text-xs text-gray-500">{{ $pending->count() }}</span></x-slot:badge>
        @forelse($pending as $post)
            <div class="rounded-lg border border-gray-200 p-3 space-y-2" data-social-pending>
                <form method="POST" action="{{ route('admin.social.update', $post) }}" class="space-y-2">
                    @csrf @method('PUT')
                    <textarea name="message" rows="2" maxlength="2000" class="{{ $field }}" aria-label="Text">{{ $post->message }}</textarea>
                    <input type="hidden" name="link" value="{{ $post->link }}"><input type="hidden" name="image_url" value="{{ $post->image_url }}">
                    <div class="flex flex-wrap items-center gap-4">
                        @foreach(\App\Models\SocialPost::CHANNELS as $key => $label)<label class="flex items-center gap-1"><input type="checkbox" name="channels[]" value="{{ $key }}" @checked(in_array($key, (array) $post->channels, true))> {{ $label }}</label>@endforeach
                        <button class="text-primary-700 underline">Save changes</button>
                    </div>
                </form>
                <p class="text-xs text-gray-500">@if($post->product)Product: {{ $post->product->getTranslation('name', 'en') }} · @endif{{ $post->link }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('admin.social.approve', $post) }}" class="flex flex-wrap items-center gap-2">@csrf
                        <input type="datetime-local" name="scheduled_at" class="rounded border border-gray-300 px-2 py-1 text-xs" aria-label="Post at (optional)">
                        <button class="rounded-lg bg-primary-600 px-3 py-1.5 font-semibold text-white hover:bg-primary-700">Approve</button></form>
                    <form method="POST" action="{{ route('admin.social.reject', $post) }}">@csrf<button class="rounded-lg border border-gray-300 px-3 py-1.5 hover:bg-gray-50">Drop</button></form>
                </div>
            </div>
        @empty
            <p class="text-gray-500">Nothing waiting.</p>
        @endforelse
    </x-admin.fold>

    <x-admin.fold title="Posted and scheduled" flush>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                @forelse($recent as $post)
                    <tr>
                        <td class="p-3">{{ \Illuminate\Support\Str::limit($post->message, 80) }}</td>
                        <td class="p-3 whitespace-nowrap">{{ ucfirst($post->status) }}@if($post->status === 'approved') · {{ $post->scheduled_at?->format('j M H:i') }}@endif</td>
                        <td class="p-3 text-xs text-gray-500">@foreach((array) $post->results as $channel => $result){{ $channel }}: {{ $result['ok'] ? 'ok' : ($result['error'] ?? 'failed') }}<br>@endforeach</td>
                    </tr>
                @empty
                    <tr><td class="p-3 text-gray-500">Nothing posted yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-admin.fold>

    <x-admin.fold title="What customers share (30 days)" body="text-sm space-y-2">
        <p class="text-xs text-gray-500">@foreach($byNetwork as $network => $count){{ ucfirst($network) }}: {{ $count }} @endforeach</p>
        <ol class="list-decimal ps-5 space-y-1">
            @forelse($shared as $row)
                @php $sharedProduct = \App\Models\Product::find($row->product_id); @endphp
                <li>@if($sharedProduct)<a href="{{ route('products.show', $sharedProduct) }}" class="text-primary-700 hover:underline" target="_blank">{{ $sharedProduct->getTranslation('name', 'en') }}</a>@endif · {{ $row->shares }} shares</li>
            @empty
                <li class="list-none text-gray-500">No shares yet.</li>
            @endforelse
        </ol>
    </x-admin.fold>
</div>
@endsection
