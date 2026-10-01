@extends('policies.layout')

@php
    use App\Support\SellerTerms;
    // Every paragraph is translated, then its {placeholders} are filled from Settings.
    $t = fn (string $key, array $replace = []) => SellerTerms::replace(__($key, $replace));
    $subtitle = $t('What every shop agrees to when it sells on {trading_name}.');
@endphp

@section('policy_key', 'seller_terms')
@section('policy_title', __('Seller Terms'))
@section('policy_subtitle', $subtitle)

@section('policy')
<p>{{ $t('These terms apply to every shop ("seller") that lists products on {trading_name}. You accept them when you apply to sell, and they apply alongside the Terms & Conditions customers accept.') }}</p>

<h2>1. {{ __('Your shop') }}</h2>
<ul>
    <li>{{ $t('{trading_name} reviews each shop before it can sell and each product before it goes live. A shop must complete its checklist (logo, banner, about text, phone number, delivery options, bank account and a first product) before its products can be approved.') }}</li>
    <li>{{ $t('Listings must be honest: real photos, accurate names in English and Dhivehi, correct prices in MVR and true stock. Prohibited or restricted goods under Maldivian law may not be listed.') }}</li>
    <li>{{ $t('You are responsible for the products you sell, for their quality and for complying with Maldivian law, including any permits your goods need.') }}</li>
</ul>

<h2>2. {{ __('Orders and shipping') }}</h2>
<ul>
    <li>{{ $t('Customers pay {trading_name} by card when they order. You pack and send your part of each order and keep its status up to date in the Seller Centre (processing, sent with a tracking note, delivered).') }}</li>
    <li>{{ $t('You must ship within {late_shipment_days} days of the customer\'s payment. Later shipments count as late on your performance record; repeated late shipments can lead to suspension.') }}</li>
    <li>{{ $t('Delivery fees are set and charged by {trading_name}. You do not charge customers for delivery separately.') }}</li>
    <li>{{ $t('Once any shop has sent its part, the order can no longer be cancelled; problems are then handled as returns.') }}</li>
</ul>

<h2>3. {{ __('Returns') }}</h2>
<ul>
    <li>{{ $t('Customers may ask to return items within {return_window_days} days of delivery. {trading_name} reviews each request and decides whether to approve it; you must not refund customers directly.') }}</li>
    <li>{{ $t('When a return is approved, your share of the returned items (their price less the commission on them) is deducted from your next payout. Delivery fees are not deducted from you.') }}</li>
</ul>

<h2>4. {{ __('Commission') }}</h2>
<ul>
    <li>{{ $t('{trading_name} keeps a commission of {commission_rate} of the item price of everything you sell, unless a different rate has been agreed for your shop in writing. The rate in force when an order is placed applies to that order.') }}</li>
    <li>{{ $t('Your earnings on an order are its item subtotal less commission. Vouchers, loyalty points and delivery fees are {trading_name}\'s and do not change your earnings.') }}</li>
</ul>

<h2>5. {{ __('Payouts') }}</h2>
<ul>
    <li>{{ $t('Earnings become payable once your part is delivered and the customer\'s payment is confirmed. {trading_name} pays payable earnings {payout_schedule}, on {payout_day}, by bank transfer to the account you registered in the Seller Centre, less any open deductions.') }}</li>
    <li>{{ $t('You must register a bank account in the Maldives in your own or your business\'s name. No payout is made to a shop without a registered account, and a new or changed account may be checked before the first transfer to it.') }}</li>
    <li>{{ $t('Each payout comes with a statement in the Seller Centre and a reference you can match against your bank statement. Tell us within 14 days if a payout looks wrong.') }}</li>
</ul>

<h2>6. {{ __('Suspension and ending') }}</h2>
<ul>
    <li>{{ $t('{trading_name} may suspend or close a shop that breaks these terms, lists prohibited goods, repeatedly ships late or sends wrong or damaged items. A suspended shop\'s products are removed from the storefront.') }}</li>
    <li>{{ $t('You may close your shop at any time. Orders already placed must still be fulfilled, and payable earnings are paid on the next payout after any open returns are settled.') }}</li>
</ul>

<h2>7. {{ __('Changes') }}</h2>
<p>{{ $t('{trading_name} may update these terms and the commission, payout and shipping settings. Changes apply to orders placed after they are published; the Seller Centre shows the current values.') }}</p>
<p>{{ $t('These terms are governed by the laws of the Republic of Maldives.') }}</p>
@endsection
