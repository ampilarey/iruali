@extends('layouts.app')

@php $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500'; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
                </div>
                <div class="flex items-center">
                    <a href="{{ route('admin.dashboard') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Storefront</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <label for="announcement_text" class="block text-sm font-medium text-gray-700">Announcement bar</label>
                        <input id="announcement_text" name="announcement_text" class="{{ $field }}" value="{{ old('announcement_text', $settings['announcement_text']) }}">
                        <p class="mt-1 text-xs text-gray-500">Shown at the top of every page. Leave empty to hide the bar.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="contact_email" class="block text-sm font-medium text-gray-700">Contact email</label>
                            <input id="contact_email" name="contact_email" type="email" class="{{ $field }}" value="{{ old('contact_email', $settings['contact_email']) }}">
                        </div>
                        <div>
                            <label for="contact_phone" class="block text-sm font-medium text-gray-700">Contact phone</label>
                            <input id="contact_phone" name="contact_phone" class="{{ $field }}" value="{{ old('contact_phone', $settings['contact_phone']) }}">
                        </div>
                        <div>
                            <label for="whatsapp_number" class="block text-sm font-medium text-gray-700">WhatsApp number</label>
                            <input id="whatsapp_number" name="whatsapp_number" class="{{ $field }}" placeholder="+960 7xx xxxx" value="{{ old('whatsapp_number', $settings['whatsapp_number'] ?? '') }}">
                            @error('whatsapp_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">Used by the “Contact Us” links. The WhatsApp number adds “Chat on WhatsApp” buttons to the help centre and product pages.</p>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <input type="hidden" name="guest_checkout_enabled" value="0">
                        <label class="flex items-start gap-3 text-sm text-gray-800">
                            <input type="checkbox" name="guest_checkout_enabled" value="1" @checked(old('guest_checkout_enabled', $settings['guest_checkout_enabled'] ?? 0)) class="mt-0.5 rounded text-primary-600 focus:ring-primary-500">
                            <span>
                                <span class="font-medium">Allow checkout without an account (guest checkout)</span>
                                <span class="block text-xs text-gray-500 mt-0.5">Guests give an email, name and address and pay by card as usual. They track the order through signed links sent by email, and can create an account afterwards to see it in My Orders. Loyalty points and referrals are not available to guests; vouchers are.</span>
                            </span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Loyalty &amp; referrals</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="loyalty_spend_per_point" class="block text-sm font-medium text-gray-700">MVR spent per point</label>
                        <input id="loyalty_spend_per_point" name="loyalty_spend_per_point" type="number" min="1" step="0.01" required class="{{ $field }}" value="{{ old('loyalty_spend_per_point', $settings['loyalty_spend_per_point']) }}">
                    </div>
                    <div>
                        <label for="referral_referrer_points" class="block text-sm font-medium text-gray-700">Referrer reward (points)</label>
                        <input id="referral_referrer_points" name="referral_referrer_points" type="number" min="0" required class="{{ $field }}" value="{{ old('referral_referrer_points', $settings['referral_referrer_points']) }}">
                    </div>
                    <div>
                        <label for="referral_referee_points" class="block text-sm font-medium text-gray-700">New customer reward (points)</label>
                        <input id="referral_referee_points" name="referral_referee_points" type="number" min="0" required class="{{ $field }}" value="{{ old('referral_referee_points', $settings['referral_referee_points']) }}">
                    </div>
                </div>
                <div class="mt-4 max-w-xs">
                    <label for="points_expire_months" class="block text-sm font-medium text-gray-700">Points expire after (months)</label>
                    <input id="points_expire_months" name="points_expire_months" type="number" min="0" max="120" class="{{ $field }}" value="{{ old('points_expire_months', $settings['points_expire_months'] ?? 0) }}">
                    <p class="mt-1 text-xs text-gray-500">0 = never. Oldest points go first; customers are emailed a month before. Needs the scheduler cron. Report under <a href="{{ route('admin.rewards') }}" class="underline">Rewards</a>.</p>
                </div>
                <p class="mt-2 text-xs text-gray-500">Referral rewards are paid once, when a referred customer places their first order.</p>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Abandoned cart emails</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="flex items-start gap-3 text-sm text-gray-700">
                        <input type="hidden" name="abandoned_cart_emails_enabled" value="0">
                        <input type="checkbox" name="abandoned_cart_emails_enabled" value="1" @checked(old('abandoned_cart_emails_enabled', $settings['abandoned_cart_emails_enabled'] ?? 1)) class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        <span><span class="font-medium text-gray-900">Send reminders</span><br><span class="text-xs text-gray-500">Signed-in customers who leave items in their cart get an email after 3 hours and again after 48 hours. Needs the scheduler cron.</span></span>
                    </label>
                    <div>
                        <label for="abandoned_cart_voucher_percent" class="block text-sm font-medium text-gray-700">Voucher in the second email (%)</label>
                        <input id="abandoned_cart_voucher_percent" name="abandoned_cart_voucher_percent" type="number" min="0" max="100" step="0.01" class="{{ $field }}" value="{{ old('abandoned_cart_voucher_percent', $settings['abandoned_cart_voucher_percent'] ?? 0) }}">
                        <p class="mt-1 text-xs text-gray-500">0 sends no voucher. Otherwise the second email carries a single-use code for that customer, valid 7 days.</p>
                    </div>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Delivery fees (MVR)</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="delivery_fee_greater_male" class="block text-sm font-medium text-gray-700">Greater Malé</label>
                        <input id="delivery_fee_greater_male" name="delivery_fee_greater_male" type="number" min="0" step="0.01" required class="{{ $field }}" value="{{ old('delivery_fee_greater_male', $settings['delivery_fee_greater_male']) }}">
                    </div>
                    <div>
                        <label for="delivery_fee_islands" class="block text-sm font-medium text-gray-700">Other islands</label>
                        <input id="delivery_fee_islands" name="delivery_fee_islands" type="number" min="0" step="0.01" required class="{{ $field }}" value="{{ old('delivery_fee_islands', $settings['delivery_fee_islands']) }}">
                    </div>
                    <div>
                        <label for="free_delivery_over" class="block text-sm font-medium text-gray-700">Free delivery over</label>
                        <input id="free_delivery_over" name="free_delivery_over" type="number" min="0" step="0.01" required class="{{ $field }}" value="{{ old('free_delivery_over', $settings['free_delivery_over']) }}">
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">Greater Malé is Malé, Hulhumalé and Villimalé. Set "Free delivery over" to 0 to always charge delivery. Checked against the order total after discounts.</p>
            </section>

            @php $missing = \App\Support\Company::missing(); @endphp
            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Business details</h2>
                <p class="mt-1 text-sm text-gray-500">Shown on the About &amp; Contact page, the policies, the footer and at checkout. Bank of Maldives checks these before approving card payments.</p>
                @if($missing)
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <p class="font-semibold">Still needed for BML:</p>
                        <ul class="mt-1 list-disc ps-5">@foreach($missing as $label)<li>{{ $label }}</li>@endforeach</ul>
                    </div>
                @endif
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="company_legal_name" class="block text-sm font-medium text-gray-700">Registered business name</label>
                        <input id="company_legal_name" name="company_legal_name" class="{{ $field }}" value="{{ old('company_legal_name', $settings['company_legal_name']) }}">
                    </div>
                    <div>
                        <label for="company_trading_name" class="block text-sm font-medium text-gray-700">Trading name</label>
                        <input id="company_trading_name" name="company_trading_name" class="{{ $field }}" value="{{ old('company_trading_name', $settings['company_trading_name']) }}">
                    </div>
                    <div>
                        <label for="company_registration_no" class="block text-sm font-medium text-gray-700">Registration number</label>
                        <input id="company_registration_no" name="company_registration_no" class="{{ $field }}" value="{{ old('company_registration_no', $settings['company_registration_no']) }}">
                    </div>
                    <div>
                        <label for="customer_service_hours" class="block text-sm font-medium text-gray-700">Customer service hours</label>
                        <input id="customer_service_hours" name="customer_service_hours" class="{{ $field }}" value="{{ old('customer_service_hours', $settings['customer_service_hours']) }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="company_address" class="block text-sm font-medium text-gray-700">Business address (permanent establishment)</label>
                        <input id="company_address" name="company_address" class="{{ $field }}" placeholder="House / building, street, island, postcode" value="{{ old('company_address', $settings['company_address']) }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="company_postal_address" class="block text-sm font-medium text-gray-700">Postal address <span class="text-gray-500 font-normal">(if different)</span></label>
                        <input id="company_postal_address" name="company_postal_address" class="{{ $field }}" value="{{ old('company_postal_address', $settings['company_postal_address']) }}">
                    </div>
                    <div>
                        <label for="return_window_days" class="block text-sm font-medium text-gray-700">Days to report a problem / return</label>
                        <input id="return_window_days" name="return_window_days" type="number" min="0" max="90" class="{{ $field }}" value="{{ old('return_window_days', $settings['return_window_days']) }}">
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">Customer service email and phone are set under Contact above. Write the phone with the country code, e.g. +960 777 1234.</p>
            </section>

            @php $bml = app(\App\Services\BmlConnect::class); @endphp
            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Payments</h2>
                <div class="mt-3 rounded-lg border px-4 py-3 text-sm {{ $bml->enabled() ? 'border-green-200 bg-green-50 text-green-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
                    @if($bml->enabled())
                        <p class="font-semibold">BML card payments are on{{ $bml->isSandbox() ? ' (sandbox: test cards only, no real money)' : '' }}.</p>
                        <p class="mt-1">Card payment is the only payment method. Customers pay on Bank of Maldives' secure page. Refunds are made in the BML merchant portal.</p>
                    @else
                        <p class="font-semibold">BML card payments are off, so checkout is closed.</p>
                        <p class="mt-1">Add <code>BML_API_KEY</code> (and <code>BML_ENVIRONMENT=production</code> when BML approves you) to the server's <code>.env</code>. See docs/PAYMENTS_BML.md.</p>
                    @endif
                </div>
                <div class="mt-4 max-w-xs">
                    <label for="default_commission_rate" class="block text-sm font-medium text-gray-700">Default shop commission (%)</label>
                    <input id="default_commission_rate" name="default_commission_rate" type="number" step="0.01" min="0" max="100" class="{{ $field }}" value="{{ old('default_commission_rate', $settings['default_commission_rate']) }}">
                    <p class="mt-1 text-xs text-gray-500">What iruali keeps from each shop's item sales. Set a different rate per shop under Shop payouts. Changes apply to new orders.</p>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('Marketplace / seller terms') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ __('Shown to shops on the Seller Terms page and in the Seller Centre, and used to flag late shipments. The commission rate is under Payments above.') }}</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="payout_schedule" class="block text-sm font-medium text-gray-700">{{ __('Payout schedule') }}</label>
                        <select id="payout_schedule" name="payout_schedule" class="{{ $field }}">
                            @foreach(\App\Support\SellerTerms::SCHEDULES as $schedule)
                                <option value="{{ $schedule }}" @selected(old('payout_schedule', $settings['payout_schedule'] ?? 'weekly') === $schedule)>{{ ucfirst($schedule) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="payout_day" class="block text-sm font-medium text-gray-700">{{ __('Payout day') }}</label>
                        <input id="payout_day" name="payout_day" maxlength="30" class="{{ $field }}" placeholder="Sunday" value="{{ old('payout_day', $settings['payout_day'] ?? '') }}">
                        <p class="mt-1 text-xs text-gray-500">{{ __('A weekday for weekly or fortnightly payouts, or a date for monthly ones (e.g. "the 5th").') }}</p>
                    </div>
                    <div>
                        <label for="late_shipment_days" class="block text-sm font-medium text-gray-700">{{ __('Days to ship after payment') }}</label>
                        <input id="late_shipment_days" name="late_shipment_days" type="number" min="0" max="60" class="{{ $field }}" value="{{ old('late_shipment_days', $settings['late_shipment_days'] ?? 3) }}">
                        <p class="mt-1 text-xs text-gray-500">{{ __('Parts shipped later than this count as late on performance pages.') }}</p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">{{ __('The return window is under Business details above. The Seller Terms page fills in these values through placeholders; edit its wording under Legal pages.') }}</p>
            </section>

            @php $analyticsProvider = old('analytics_provider', $settings['analytics_provider'] ?? 'none'); @endphp
            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Site analytics</h2>
                <p class="mt-1 text-sm text-gray-500">Visitor statistics. The tag is added to storefront pages only (never admin or Seller Centre), and never for browsers that send Do Not Track. The shopping funnel under Analytics works without any provider.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="analytics_provider" class="block text-sm font-medium text-gray-700">Provider</label>
                        <select id="analytics_provider" name="analytics_provider" class="{{ $field }}">
                            <option value="none" @selected($analyticsProvider === 'none')>None</option>
                            <option value="plausible" @selected($analyticsProvider === 'plausible')>Plausible (privacy-friendly)</option>
                            <option value="ga4" @selected($analyticsProvider === 'ga4')>Google Analytics 4</option>
                        </select>
                    </div>
                    <div>
                        <label for="analytics_domain" class="block text-sm font-medium text-gray-700">Plausible domain</label>
                        <input id="analytics_domain" name="analytics_domain" class="{{ $field }}" placeholder="iruali.mv" value="{{ old('analytics_domain', $settings['analytics_domain'] ?? '') }}">
                        @error('analytics_domain')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="analytics_id" class="block text-sm font-medium text-gray-700">GA4 measurement ID</label>
                        <input id="analytics_id" name="analytics_id" class="{{ $field }}" placeholder="G-XXXXXXXXXX" value="{{ old('analytics_id', $settings['analytics_id'] ?? '') }}">
                        @error('analytics_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Social profiles</h2>
                <p class="mt-1 text-sm text-gray-500">Linked from the site's structured data (Organization "sameAs") so search engines connect the profiles to iruali. Full URLs.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach(['social_facebook' => 'Facebook', 'social_instagram' => 'Instagram', 'social_tiktok' => 'TikTok', 'social_x' => 'X (Twitter)'] as $key => $label)
                        <div>
                            <label for="{{ $key }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
                            <input id="{{ $key }}" name="{{ $key }}" type="url" class="{{ $field }}" placeholder="https://" value="{{ old($key, $settings[$key] ?? '') }}">
                            @error($key)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Product feeds</h2>
                <p class="mt-1 text-sm text-gray-500">Give these URLs to Google Merchant Center and Facebook Commerce Manager (catalogue → data feed, scheduled fetch). They list every active product (one item per variant) and refresh hourly. The token keeps scrapers out; don't share it publicly.</p>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="font-medium text-gray-700">Google Merchant (RSS)</dt>
                        <dd><code class="block break-all rounded bg-gray-50 px-3 py-2 text-xs text-gray-800" dir="ltr">{{ \App\Support\FeedToken::url('feeds.google-merchant') }}</code></dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-700">Facebook / Instagram catalogue (CSV)</dt>
                        <dd><code class="block break-all rounded bg-gray-50 px-3 py-2 text-xs text-gray-800" dir="ltr">{{ \App\Support\FeedToken::url('feeds.facebook-catalog') }}</code></dd>
                    </div>
                </dl>
            </section>

            <div class="flex justify-end">
                <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Save settings</button>
            </div>
        </form>
    </div>
</div>
@endsection
