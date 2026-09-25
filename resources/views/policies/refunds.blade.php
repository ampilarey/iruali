@extends('policies.layout')

@php
    use App\Models\Setting;
    use App\Support\Company;
    $days = Company::returnWindowDays();
    $whatsapp = preg_replace('/[^0-9]/', '', (string) Setting::get('whatsapp_number'));
@endphp

@section('policy_key', 'refunds')
@section('policy_title', __('Returns, Refunds & Cancellations'))
@section('policy_subtitle', __('Please read this policy before completing your purchase.'))

@section('policy')
<div class="rounded-lg border-s-4 border-sun bg-sun-soft p-4">
    <p><strong>Key points:</strong> you can cancel free of charge while your order is <em>Pending</em>. Damaged, faulty or wrong items can be returned within {{ $days }} days of delivery. <strong>Food and perishables, opened beauty and personal care products, underwear, swimwear and custom-made items cannot be returned</strong> unless they arrive damaged, faulty or wrong.</p>
</div>

<h2>1. Cancellation</h2>
<ul>
    <li><strong>While your order is Pending:</strong> cancel it yourself from <a href="{{ route('orders') }}">My Orders</a> at no charge. You get a full refund, including delivery.</li>
    <li><strong>Once the shop is preparing it or has sent it:</strong> contact us straight away. We will cancel it if the shop can still stop it; otherwise you can return it under section 2.</li>
    <li><strong>Cancelled by us or the shop</strong> (for example, the item is out of stock or delivery is not possible): full refund, including delivery.</li>
    <li>Card orders that are not paid within 24 hours are cancelled automatically. Nothing is charged.</li>
</ul>

<h2>2. Returns and refunds</h2>
<ul>
    <li><strong>Damaged, faulty, wrong or not as described:</strong> tell us within {{ $days }} days of delivery with your order number and a photo. We arrange a replacement, an exchange or a full refund, including the delivery fee, and we pay any return delivery.</li>
    <li><strong>Missing items:</strong> tell us within 48 hours of delivery. We send the missing items or refund them.</li>
    <li><strong>Change of mind:</strong> at the shop's discretion. If the shop agrees, the item must be unused, in its original packaging and returned within {{ $days }} days of delivery; the original delivery fee and return delivery are not refunded.</li>
    <li><strong>Order not fulfilled:</strong> if we cannot supply or deliver your order, you receive a full refund.</li>
</ul>

<h2>3. Exchanges</h2>
<p>If you received the wrong size, colour or variant, or a faulty item, we exchange it for the right one when the shop has it in stock. If it does not, you get a refund.</p>

<h2>4. Items that cannot be returned</h2>
<p>For health, hygiene and freshness reasons, these items cannot be returned or exchanged unless they arrived damaged, faulty or wrong:</p>
<ul>
    <li>food, groceries and other perishable goods;</li>
    <li>opened beauty, skincare and personal care products;</li>
    <li>underwear and swimwear;</li>
    <li>items made or altered to your order (custom or personalised items).</li>
</ul>

<h2>5. Refund process</h2>
<ul>
    <li><strong>Card payments</strong> are refunded to the original card through Bank of Maldives (BML). Processing time is usually <strong>5–7 business days</strong>, depending on your bank.</li>
    <li><strong>Bank transfer and cash on delivery</strong> are refunded by bank transfer to an account in your name within 5–7 business days of approval.</li>
    <li>We do not refund card payments in cash or to a different card.</li>
    <li>You receive a confirmation when the refund has been started. Loyalty points and vouchers used on a cancelled order are returned to your account.</li>
</ul>

<h2>6. How to request a cancellation, return or refund</h2>
<p>Contact us with your order number through any of these channels:</p>
<ul>
    @if($whatsapp)<li>WhatsApp: <a href="https://wa.me/{{ $whatsapp }}" dir="ltr">+{{ $whatsapp }}</a></li>@endif
    @if(Company::phone())<li>Phone: <a href="tel:{{ preg_replace('/[^0-9+]/', '', Company::phone()) }}" dir="ltr">{{ Company::phone() }}</a></li>@endif
    @if(Company::email())<li>Email: <a href="mailto:{{ Company::email() }}">{{ Company::email() }}</a></li>@endif
    @if(Company::address())<li>In person: {{ Company::address() }}</li>@endif
    <li>Online: <a href="{{ route('orders') }}">My Orders</a> (cancel a pending order)</li>
</ul>
<p>We reply within 2 business days.</p>

<h2>7. Payment disputes</h2>
<p>If you believe a charge on your card is incorrect, please contact us within <strong>7 days of the transaction date</strong>. We will investigate and respond within 3 business days. If the issue is not resolved, you may raise a dispute with your card issuer.</p>

<h2>8. Keep your receipt</h2>
<p>Please keep your order confirmation and payment receipt until your order has arrived and you are happy with it. A printable receipt is available for every order in <a href="{{ route('orders') }}">My Orders</a>.</p>

<p class="text-sm text-gray-500">All transactions are in {{ Company::currency() }}. No import/export charges or customs duties apply, because all orders are delivered within the Maldives.</p>
@endsection
