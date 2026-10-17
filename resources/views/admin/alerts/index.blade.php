@extends('layouts.admin')

@section('title', 'Team alerts')

@section('content')
<div class="min-h-screen bg-gray-100 pb-6 lg:pb-12">
    @include('admin.payouts._header', ['title' => 'Team alerts'])

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-3">
        @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif

        <p class="text-sm text-gray-600">Get told the moment something needs a person: in a Telegram group, and/or by SMS to the staff who choose it. The business inbox ({{ \App\Models\Setting::get('contact_email') ?: 'the contact email in Settings' }}) keeps getting its emails as before.</p>

        <form method="POST" action="{{ route('admin.alerts.update') }}" class="space-y-3" id="alerts-form">
            @csrf
            @method('PUT')

            <x-admin.fold title="Telegram group" body="space-y-4 text-sm" :open="! $chatId" id="alerts-telegram">
                <x-slot:badge><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $telegramConfigured && $chatId ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $telegramConfigured && $chatId ? 'On' : 'Not set up' }}</span></x-slot:badge>
                @unless($telegramConfigured)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
                        <p class="font-semibold">To set up</p>
                        <ol class="mt-1 list-decimal ps-5 space-y-1">
                            <li>In Telegram, message <strong>@BotFather</strong>, send <code>/newbot</code> and follow the steps. It gives you a token.</li>
                            <li>On the server, put it in <code>.env</code> as <code>TELEGRAM_BOT_TOKEN=…</code> and run <code>php artisan config:cache</code>.</li>
                            <li>Add the bot to your team's group and send any message there, then come back here and press "Find my group".</li>
                        </ol>
                    </div>
                @endunless
                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <label for="telegram_chat_id" class="block font-medium text-gray-700">Group chat id</label>
                        <input id="telegram_chat_id" name="telegram_chat_id" value="{{ old('telegram_chat_id', $chatId) }}" dir="ltr" placeholder="-1001234567890" class="mt-1 w-56 rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    @if($telegramConfigured)
                        <button form="find-chats" class="rounded-lg border border-gray-300 bg-white px-3 py-2 font-semibold text-gray-700 hover:bg-gray-50">Find my group</button>
                    @endif
                </div>
                @if($chats)
                    <ul class="space-y-1" data-telegram-chats>
                        @foreach($chats as $chat)
                            <li><button type="button" class="text-primary-700 hover:underline" onclick="document.getElementById('telegram_chat_id').value='{{ $chat['id'] }}'">{{ $chat['title'] }} <span class="text-gray-500" dir="ltr">({{ $chat['id'] }})</span></button></li>
                        @endforeach
                    </ul>
                @endif
                <fieldset>
                    <legend class="font-medium text-gray-700">Post these to the group</legend>
                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach($events as $key => [$label, $when])
                            <label class="flex items-start gap-2">
                                <input type="checkbox" name="telegram_events[]" value="{{ $key }}" @checked(in_array($key, old('telegram_events', $telegramEvents), true)) class="mt-0.5 rounded text-primary-600 focus:ring-primary-500">
                                <span><span class="text-gray-900">{{ $label }}</span><span class="block text-xs text-gray-500">{{ $when }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                @if($telegramConfigured && $chatId)
                    <button form="test-telegram" class="rounded-lg border border-gray-300 bg-white px-3 py-2 font-semibold text-gray-700 hover:bg-gray-50">Send a test to the group</button>
                @endif
            </x-admin.fold>

            <x-admin.fold title="Text messages to staff" flush :open="true" id="alerts-sms">
                <p class="px-4 py-3 text-xs text-gray-500 sm:px-6">Tick what each person wants a text about. They go to the phone on their account, follow the SMS switches and quiet hours in <a href="{{ route('admin.sms') }}" class="text-primary-700 hover:underline">Admin → SMS</a> (payment errors and failed backups come at night too), and count towards the monthly limit.</p>
                <ul class="divide-y divide-gray-100">
                    @foreach($staff as $member)
                        @php $chosen = old('sms.'.$member->id, (array) ($member->team_alerts ?? [])); @endphp
                        <li class="px-4 py-3 sm:px-6" data-alert-staff="{{ $member->id }}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-medium text-gray-900">{{ $member->name }} <span class="text-xs font-normal text-gray-500">{{ $member->roles->pluck('name')->intersect(config('staff.roles'))->join(', ') }}</span></p>
                                @if($member->phone)
                                    <span class="flex items-center gap-2 text-xs text-gray-600"><span dir="ltr">{{ $member->phone }}</span>
                                        <button form="test-sms-{{ $member->id }}" class="font-semibold text-primary-700 hover:underline">Send a test</button></span>
                                @else
                                    <span class="text-xs text-amber-700">No phone on this account: add one in their profile to get texts.</span>
                                @endif
                            </div>
                            <div class="mt-2 grid grid-cols-1 gap-1.5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($events as $key => [$label])
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="sms[{{ $member->id }}][]" value="{{ $key }}" @checked(in_array($key, $chosen, true)) @disabled(! $member->phone) class="rounded text-primary-600 focus:ring-primary-500">
                                        <span class="text-gray-800">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-admin.fold>

            <div class="flex justify-end"><button class="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">Save team alerts</button></div>
        </form>

        {{-- Small forms the buttons above point to (forms can't be nested) --}}
        <form id="find-chats" method="POST" action="{{ route('admin.alerts.find-chats') }}" class="hidden">@csrf</form>
        <form id="test-telegram" method="POST" action="{{ route('admin.alerts.test') }}" class="hidden">@csrf<input type="hidden" name="to" value="telegram"></form>
        @foreach($staff as $member)
            <form id="test-sms-{{ $member->id }}" method="POST" action="{{ route('admin.alerts.test') }}" class="hidden">@csrf<input type="hidden" name="to" value="{{ $member->id }}"></form>
        @endforeach
    </div>
</div>
@endsection
