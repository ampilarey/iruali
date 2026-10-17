@extends('layouts.admin')

@section('title', 'SMS')

@php
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
    $statusLabels = ['sent' => 'Sent', 'logged' => 'Logged only', 'held' => 'Held (quiet hours)', 'blocked' => 'Not sent (switched off / limit)', 'failed' => 'Failed', 'invalid' => 'Invalid number'];
    $limit = $settings['limit'];
    $used = $settings['sentThisMonth'];
@endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-6 lg:pb-12">
    @include('admin.payouts._header', ['title' => 'SMS'])

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-3">
        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        {{-- At a glance --}}
        <section class="grid grid-cols-2 gap-2 sm:grid-cols-4" data-sms-glance>
            <div class="rounded-xl bg-white p-3 shadow-sm">
                <p class="text-xs text-gray-500">SMS</p>
                <p class="mt-1 font-semibold {{ $settings['enabled'] ? 'text-green-700' : 'text-red-700' }}">{{ $settings['enabled'] ? 'On' : 'All switched off' }}</p>
            </div>
            <div class="rounded-xl bg-white p-3 shadow-sm">
                <p class="text-xs text-gray-500">Gateway</p>
                <p class="mt-1 font-semibold {{ $live ? 'text-green-700' : 'text-amber-700' }}">{{ $live ? 'Connected' : 'Not connected (log)' }}</p>
            </div>
            <div class="rounded-xl bg-white p-3 shadow-sm">
                <p class="text-xs text-gray-500">Sent this month</p>
                <p class="mt-1 font-semibold text-gray-900">{{ number_format($used) }}@if($limit) <span class="text-sm font-normal text-gray-500">of {{ number_format($limit) }}</span>@endif</p>
                @if($limit)<div class="mt-1 h-1.5 rounded-full bg-gray-100"><div class="h-1.5 rounded-full {{ $used >= $limit ? 'bg-red-500' : ($used >= $limit * 0.8 ? 'bg-amber-500' : 'bg-primary-500') }}" style="width: {{ min(100, $used / max(1, $limit) * 100) }}%"></div></div>@endif
            </div>
            <div class="rounded-xl bg-white p-3 shadow-sm">
                <p class="text-xs text-gray-500">This month</p>
                <p class="mt-1 text-xs text-gray-700">{{ $month['failed'] ?? 0 }} failed · {{ $month['held'] ?? 0 }} held · {{ $month['blocked'] ?? 0 }} not sent</p>
            </div>
        </section>

        {{-- Switches and limits --}}
        <form method="POST" action="{{ route('admin.sms.settings') }}">
            @csrf
            @method('PUT')
            <x-admin.fold title="Switches and limits" body="space-y-4 text-sm" :open="! $settings['enabled']" id="sms-settings">
                <x-slot:badge>@if($settings['quietNow'])<span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800">Quiet hours now</span>@endif</x-slot:badge>
                @unless($canManage)<p class="text-amber-800">Only admins can change these.</p>@endunless
                <fieldset @disabled(! $canManage) class="space-y-4">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3" data-sms-switch>
                        <input type="hidden" name="sms_enabled" value="0">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="sms_enabled" value="1" @checked(old('sms_enabled', $settings['enabled'])) class="mt-0.5 rounded text-primary-600 focus:ring-primary-500">
                            <span><span class="font-medium text-gray-900">Send SMS</span>
                                <span class="block text-xs text-gray-500 mt-0.5">Off stops every text message at once, including verification codes. Emails are not affected. Use it if the gateway misbehaves or the bill looks wrong.</span></span>
                        </label>
                    </div>
                    <div>
                        <label for="sms_monthly_limit" class="block font-medium text-gray-700">Monthly limit (messages)</label>
                        <input id="sms_monthly_limit" name="sms_monthly_limit" type="number" min="0" max="1000000" inputmode="numeric" value="{{ old('sms_monthly_limit', $limit) }}" class="{{ $field }} max-w-xs">
                        <p class="mt-1 text-xs text-gray-500">0 means no limit. When the gateway has accepted this many in a month, order updates stop until the 1st and the team is emailed once. Verification codes still go.</p>
                        @error('sms_monthly_limit')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <p class="font-medium text-gray-700">Quiet hours (Maldives time)</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <label for="sms_quiet_from" class="sr-only">From</label>
                            <input id="sms_quiet_from" name="sms_quiet_from" type="time" value="{{ old('sms_quiet_from', $settings['quiet_from']) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <span class="text-gray-500">to</span>
                            <label for="sms_quiet_until" class="sr-only">Until</label>
                            <input id="sms_quiet_until" name="sms_quiet_until" type="time" value="{{ old('sms_quiet_until', $settings['quiet_until']) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Messages in these hours wait and go out when they end (e.g. 22:00 to 08:00). Codes go at once. Leave both empty for no quiet hours.</p>
                        @error('sms_quiet_from')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        @error('sms_quiet_until')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end"><button class="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">Save</button></div>
                </fieldset>
            </x-admin.fold>
        </form>

        {{-- Each message --}}
        <x-admin.fold title="Messages" flush id="sms-types" data-sms-types>
            <x-slot:badge><span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ count($types) }}</span></x-slot:badge>
            <p class="px-4 py-3 text-xs text-gray-500 sm:px-6">Switch a message off, or write your own wording. Leave a box empty to use the built-in wording (shown greyed). Use the {placeholders} listed for each; they are filled in when it is sent.</p>
            @foreach(\App\Support\SmsTypes::GROUPS as $group => $groupLabel)
                <h3 class="border-t border-gray-100 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500 sm:px-6">{{ $groupLabel }}</h3>
                <ul class="divide-y divide-gray-100">
                    @foreach($types as $key => $type)
                        @continue($type['group'] !== $group)
                        @php
                            $saved = $typeSettings[$key] ?? null;
                            $on = $saved ? $saved['enabled'] : true;
                            $own = $saved && (filled($saved['template_en']) || filled($saved['template_dv']));
                            $errorsHere = old('_type') === $key;
                        @endphp
                        <li>
                            <details id="sms-type-{{ $key }}" data-fold="sms-type-{{ $key }}" data-sms-type="{{ $key }}" class="group/type" @if($errorsHere) open @endif>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 sm:px-6 [&::-webkit-details-marker]:hidden">
                                    <span class="min-w-0">
                                        <span class="block font-medium text-gray-900">{{ $type['label'] }}</span>
                                        <span class="block truncate text-xs text-gray-500">{{ $type['urgent'] ?? false ? 'Urgent: sent at night and over the limit · ' : '' }}{{ number_format($sentByType[$key] ?? 0) }} sent this month</span>
                                    </span>
                                    <span class="flex shrink-0 items-center gap-1.5">
                                        @if($own)<span class="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700">Own wording</span>@endif
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $on ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600' }}">{{ $on ? 'On' : 'Off' }}</span>
                                        <x-icon name="chevron-down" class="h-4 w-4 text-gray-400 transition-transform group-open/type:rotate-180" />
                                    </span>
                                </summary>
                                <div class="space-y-3 border-t border-gray-100 bg-gray-50/50 px-4 py-4 text-sm sm:px-6">
                                    <form method="POST" action="{{ route('admin.sms.types.update', $key) }}" class="space-y-3">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="_type" value="{{ $key }}">
                                        <fieldset @disabled(! $canManage) class="space-y-3">
                                            @unless($type['urgent'] ?? false)
                                                <input type="hidden" name="enabled" value="0">
                                                <label class="flex items-center gap-2">
                                                    <input type="checkbox" name="enabled" value="1" @checked($errorsHere ? old('enabled') : $on) class="rounded text-primary-600 focus:ring-primary-500">
                                                    <span class="font-medium text-gray-800">Send this message</span>
                                                </label>
                                            @else
                                                <input type="hidden" name="enabled" value="1">
                                                <p class="text-xs text-gray-500">Always on: people need it to verify their phone or collect their order.</p>
                                            @endunless
                                            @if($errorsHere)@error('enabled')<p class="text-xs text-red-700">{{ $message }}</p>@enderror @endif
                                            @foreach(['en' => 'English', 'dv' => 'Dhivehi'] as $locale => $language)
                                                <div>
                                                    <label for="tpl-{{ $key }}-{{ $locale }}" class="block font-medium text-gray-700">{{ $language }}</label>
                                                    <textarea id="tpl-{{ $key }}-{{ $locale }}" name="template_{{ $locale }}" rows="3" maxlength="480" @if($locale === 'dv') dir="rtl" lang="dv" @endif
                                                        placeholder="{{ \App\Support\SmsTypes::builtIn($key, $locale) }}" data-sms-template data-sms-length
                                                        class="{{ $field }}">{{ $errorsHere ? old('template_'.$locale) : ($saved['template_'.$locale] ?? '') }}</textarea>
                                                    <p class="mt-1 flex justify-between text-xs text-gray-500"><span data-sms-count></span></p>
                                                    @if($errorsHere)@error('template_'.$locale)<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror @endif
                                                </div>
                                            @endforeach
                                            <p class="text-xs text-gray-500">Placeholders: @foreach($type['data'] as $name => $example)<code class="rounded bg-white px-1 py-0.5 font-mono text-primary-700" title="e.g. {{ $example }}">{{ '{'.$name.'}' }}</code>@if(in_array($name, $type['required'] ?? [], true))<span class="text-red-700">*</span>@endif @endforeach</p>
                                            @if($canManage)<div class="flex justify-end"><button class="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">Save</button></div>@endif
                                        </fieldset>
                                    </form>

                                    <div class="rounded-lg border border-gray-200 bg-white p-3" data-sms-preview>
                                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Preview with example values (saved wording)</p>
                                        <p class="mt-1 whitespace-pre-line text-gray-800">{{ \App\Support\SmsTypes::example($key, 'en')->text ?: 'Switched off: nothing is sent.' }}</p>
                                        <p class="mt-1 whitespace-pre-line text-gray-800" dir="rtl" lang="dv">{{ \App\Support\SmsTypes::example($key, 'dv')->text }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('admin.sms.types.test', $key) }}" class="flex flex-wrap items-end gap-2">
                                        @csrf
                                        <div>
                                            <label for="test-{{ $key }}" class="block text-xs font-medium text-gray-700">Send the preview to</label>
                                            <input id="test-{{ $key }}" name="to" required dir="ltr" placeholder="777 1234" value="{{ auth()->user()->phone }}" class="mt-1 w-36 rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                                        </div>
                                        <label for="test-locale-{{ $key }}" class="sr-only">Language</label>
                                        <select id="test-locale-{{ $key }}" name="locale" class="rounded-lg border border-gray-300 px-2 py-1.5 text-sm">
                                            <option value="en">English</option>
                                            <option value="dv">Dhivehi</option>
                                        </select>
                                        <button class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Send test</button>
                                    </form>
                                </div>
                            </details>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </x-admin.fold>

        {{-- Gateway and a free-text test --}}
        <x-admin.fold title="Gateway and test" body="text-sm space-y-3">
            @if($live)
                <p class="text-green-800">Driver <code class="rounded bg-gray-100 px-1">{{ $driver }}</code>: messages are sent through <span class="break-all">{{ $driver === 'dhiraagu' ? 'Dhiraagu Bulk SMS ('.config('sms.dhiraagu.url').')' : config('sms.http.url') }}</span>.</p>
            @else
                <p class="text-amber-800">Driver <code class="rounded bg-gray-100 px-1">log</code>: nothing is sent. Messages are written to the log and listed here. For Dhiraagu set <code>SMS_DRIVER=dhiraagu</code>, <code>SMS_DHIRAAGU_USERNAME</code> and <code>SMS_DHIRAAGU_PASSWORD</code> in <code>.env</code>; for another gateway set <code>SMS_DRIVER=http</code> with <code>SMS_URL</code>, <code>SMS_METHOD</code>, <code>SMS_AUTH_HEADER</code>, <code>SMS_BODY_TEMPLATE</code>, <code>SMS_SENDER_ID</code> and <code>SMS_SUCCESS_REGEX</code> in <code>.env</code> to connect a Dhiraagu / Ooredoo bulk-SMS account (see config/sms.php).</p>
            @endif
            <form method="POST" action="{{ route('admin.sms.test') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="to" class="block font-medium text-gray-700">Mobile number</label>
                    <input id="to" name="to" value="{{ old('to', auth()->user()->phone) }}" required dir="ltr" placeholder="777 1234" class="{{ $field }} max-w-xs">
                    @error('to')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="message" class="block font-medium text-gray-700">Message</label>
                    <textarea id="message" name="message" rows="3" maxlength="320" required class="{{ $field }}">{{ old('message', 'Test message from iruali.') }}</textarea>
                    @error('message')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
                <button class="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">Send test</button>
            </form>
        </x-admin.fold>

        {{-- Log --}}
        <x-admin.fold title="Log" flush :open="true" id="sms-log">
            <form method="GET" action="{{ route('admin.sms') }}#sms-log" class="flex flex-wrap items-end gap-2 border-b border-gray-100 px-4 py-3 text-sm sm:px-6">
                <div class="min-w-0">
                    <label for="f-status" class="block text-xs text-gray-500">Status</label>
                    <select id="f-status" name="status" class="mt-1 rounded-lg border border-gray-300 px-2 py-1.5 text-sm">
                        <option value="">All</option>
                        @foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="min-w-0 max-w-full">
                    <label for="f-type" class="block text-xs text-gray-500">Message</label>
                    <select id="f-type" name="type" class="mt-1 w-full max-w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm">
                        <option value="">All</option>
                        @foreach($types as $key => $type)<option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ $type['label'] }}</option>@endforeach
                        <option value="test" @selected(($filters['type'] ?? '') === 'test')>Tests</option>
                        <option value="team_alert" @selected(($filters['type'] ?? '') === 'team_alert')>Team alerts</option>
                    </select>
                </div>
                <div>
                    <label for="f-q" class="block text-xs text-gray-500">Number</label>
                    <input id="f-q" name="q" value="{{ $filters['q'] ?? '' }}" dir="ltr" placeholder="777 1234" class="mt-1 w-32 rounded-lg border border-gray-300 px-2 py-1.5 text-sm">
                </div>
                <button class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-50">Show</button>
                <a href="{{ route('admin.sms.export', $filters) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-50" data-sms-export>Download CSV</a>
            </form>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse($messages as $m)
                    <li class="px-4 py-3 sm:px-6" data-sms-row="{{ $m->status }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-gray-900" dir="ltr">{{ $m->to }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $m->status_badge }}">{{ $statusLabels[$m->status] ?? $m->status }}</span>
                                @if($m->type)<span class="text-xs text-gray-500">{{ $types[$m->type]['label'] ?? (['test' => 'Test', 'team_alert' => 'Team alert'][$m->type] ?? $m->type) }}</span>@endif
                            </span>
                            <span class="text-xs text-gray-500">{{ $m->created_at?->format('d M, H:i') }}@if($m->status === 'held' && $m->send_after) · goes {{ $m->send_after->format('d M, H:i') }}@endif</span>
                        </div>
                        <p class="mt-1 whitespace-pre-line text-gray-800">{{ $m->message }}</p>
                        @if($m->provider_response || $m->cost !== null)
                            <p class="mt-1 truncate text-xs text-gray-500" title="{{ $m->provider_response }}">{{ \Illuminate\Support\Str::limit((string) $m->provider_response, 120) }}@if($m->cost !== null) · MVR {{ $m->cost }}@endif</p>
                        @endif
                        @if(in_array($m->status, ['failed', 'blocked', 'held'], true))
                            <form method="POST" action="{{ route('admin.sms.resend', $m) }}" class="mt-1">
                                @csrf
                                <button class="text-xs font-semibold text-primary-700 hover:underline">{{ $m->status === 'held' ? 'Send now' : 'Send again' }}</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-gray-500">No messages.</li>
                @endforelse
            </ul>
            <div class="px-4 py-3">{{ $messages->links() }}</div>
        </x-admin.fold>
    </div>
</div>

@push('scripts')
<script>
    // Characters and SMS parts as the wording is typed (Dhivehi uses 70 characters a part)
    document.querySelectorAll('[data-sms-length]').forEach(function (box) {
        var out = box.parentElement.querySelector('[data-sms-count]');
        function update() {
            var text = box.value || box.placeholder;
            var unicode = /[^\u0000-\u007f]/.test(text);
            var per = unicode ? (text.length > 70 ? 67 : 70) : (text.length > 160 ? 153 : 160);
            var parts = Math.max(1, Math.ceil(text.length / per));
            out.textContent = text.length + ' characters · ' + parts + ' SMS' + (box.value ? '' : ' (built-in wording)');
        }
        box.addEventListener('input', update);
        update();
    });
</script>
@endpush
@endsection
