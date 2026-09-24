@extends('layouts.app')

@php
    use App\Models\Setting;
    use App\Support\Money;
    $email = Setting::get('contact_email');
    $phone = Setting::get('contact_phone');
    $whatsapp = preg_replace('/[^0-9]/', '', (string) Setting::get('whatsapp_number'));
    $male = (float) Setting::get('delivery_fee_greater_male');
    $islands = (float) Setting::get('delivery_fee_islands');
    $freeOver = (float) Setting::get('free_delivery_over');
    $topics = [
        ['id' => 'orders', 'icon' => 'box', 'title' => __('Orders & tracking')],
        ['id' => 'delivery', 'icon' => 'truck', 'title' => __('Delivery & fees')],
        ['id' => 'payment', 'icon' => 'bank', 'title' => __('Payment')],
        ['id' => 'returns', 'icon' => 'return', 'title' => __('Returns & Exchanges')],
        ['id' => 'selling', 'icon' => 'store', 'title' => __('Selling on iruali')],
        ['id' => 'contact', 'icon' => 'help', 'title' => __('Contact Us')],
    ];
@endphp

@section('content')
<div class="bg-gray-50">
    <div class="bg-primary text-white">
        <div class="max-w-5xl mx-auto px-4 lg:px-6 py-8 lg:py-12">
            <h1 class="font-display text-3xl lg:text-4xl font-bold">{{ __('Help centre') }}</h1>
            <p class="mt-2 text-white/85">{{ __('Answers about orders, delivery, payment and returns.') }}</p>
        </div>
    </div>
    <div class="max-w-5xl mx-auto px-4 lg:px-6 py-6 lg:py-10">
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-8">
            @foreach($topics as $topic)
                <a href="#{{ $topic['id'] }}" class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 hover:border-primary">
                    <span class="w-10 h-10 shrink-0 rounded-lg bg-primary-50 text-primary flex items-center justify-center"><x-icon :name="$topic['icon']" /></span>
                    <span class="font-semibold text-sm">{{ $topic['title'] }}</span>
                </a>
            @endforeach
        </div>

        <div class="space-y-4">
            <section id="orders" class="scroll-mt-36 bg-white border border-gray-200 rounded-xl p-5 lg:p-6">
                <h2 class="font-display text-xl font-bold mb-2">{{ __('Orders & tracking') }}</h2>
                <p class="text-gray-700">{{ __('After you order, the shop confirms it, packs it and sends it to your island. You get an email at each step.') }}</p>
                <p class="text-gray-700 mt-2">{{ __('Signed in? See every order under My Orders. Checked out as a guest? Use Track Order with your order number and email.') }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('orders') }}" class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold">{{ __('My Orders') }}</a>
                    <a href="{{ route('order.track.form') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold">{{ __('Track Order') }}</a>
                </div>
            </section>

            <section id="delivery" class="scroll-mt-36 bg-white border border-gray-200 rounded-xl p-5 lg:p-6">
                <h2 class="font-display text-xl font-bold mb-2">{{ __('Delivery & fees') }}</h2>
                <table class="w-full text-sm mt-2">
                    <tbody class="divide-y divide-gray-100">
                        <tr><th scope="row" class="text-start font-medium text-gray-600 py-2">{{ __('Greater Malé (Malé, Hulhumalé, Villimalé)') }}</th><td class="text-end font-semibold">{{ Money::format($male) }}</td></tr>
                        <tr><th scope="row" class="text-start font-medium text-gray-600 py-2">{{ __('Other islands') }}</th><td class="text-end font-semibold">{{ Money::format($islands) }}</td></tr>
                        @if($freeOver > 0)
                            <tr><th scope="row" class="text-start font-medium text-gray-600 py-2">{{ __('Orders over :amount', ['amount' => Money::format($freeOver)]) }}</th><td class="text-end font-semibold text-success">{{ __('Free') }}</td></tr>
                        @endif
                    </tbody>
                </table>
                <p class="text-gray-700 mt-3">{{ __('Sellers ship by boat and air to islands across every atoll. Delivery times depend on the ferry and flight schedule to your island.') }}</p>
            </section>

            <section id="payment" class="scroll-mt-36 bg-white border border-gray-200 rounded-xl p-5 lg:p-6">
                <h2 class="font-display text-xl font-bold mb-2">{{ __('Payment') }}</h2>
                <p class="text-gray-700">{{ __('Pay cash when your order arrives, or pay by bank transfer and upload your transfer slip on the order page. We confirm the payment before the shop sends your order.') }}</p>
            </section>

            <section id="returns" class="scroll-mt-36 bg-white border border-gray-200 rounded-xl p-5 lg:p-6">
                <h2 class="font-display text-xl font-bold mb-2">{{ __('Returns & Exchanges') }}</h2>
                <p class="text-gray-700">{{ __('Something wrong with your order? Contact us within 7 days of delivery with your order number and a photo. We will work it out with the shop: a replacement, an exchange or a refund.') }}</p>
                <p class="text-gray-700 mt-2">{{ __('You can cancel an order yourself while it is still pending.') }}</p>
            </section>

            <section id="selling" class="scroll-mt-36 bg-white border border-gray-200 rounded-xl p-5 lg:p-6">
                <h2 class="font-display text-xl font-bold mb-2">{{ __('Selling on iruali') }}</h2>
                <p class="text-gray-700">{{ __('Have a shop? Reach customers on every inhabited island. Applying takes five minutes.') }}</p>
                <a href="{{ route('seller.apply') }}" class="inline-block mt-4 px-4 py-2 rounded-lg bg-sun text-sun-on text-sm font-semibold">{{ __('Open your shop') }}</a>
            </section>

            <section id="contact" class="scroll-mt-36 bg-white border border-gray-200 rounded-xl p-5 lg:p-6">
                <h2 class="font-display text-xl font-bold mb-2">{{ __('Contact Us') }}</h2>
                @if($whatsapp)
                    <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 mb-3 px-4 py-2 rounded-lg bg-[#25D366] text-white text-sm font-semibold"><x-icon name="chat" class="w-4 h-4" />{{ __('Chat on WhatsApp') }}</a>
                @endif
                @if($email || $phone)
                    <ul class="space-y-2">
                        @if($phone)<li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="inline-flex items-center gap-2 font-semibold text-primary hover:underline" dir="ltr"><x-icon name="phone" class="w-4 h-4" />{{ $phone }}</a></li>@endif
                        @if($email)<li><a href="mailto:{{ $email }}" class="inline-flex items-center gap-2 font-semibold text-primary hover:underline"><x-icon name="mail" class="w-4 h-4" />{{ $email }}</a></li>@endif
                    </ul>
                @elseif(! $whatsapp)
                    <p class="text-gray-700">{{ __('Our contact details are coming soon.') }}</p>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection
