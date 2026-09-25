@extends('policies.layout')

@php
    use App\Support\Company;
    $days = Company::returnWindowDays();
@endphp

@section('policy_title', __('Returns, Refunds & Cancellations'))

@section('policy')
<p>This policy explains when you can cancel an order, return or exchange an item, and how refunds are paid. <strong>Please read the list of items that cannot be returned (section 4) before you buy.</strong></p>

<h2>1. Cancelling an order</h2>
<ul>
    <li><strong>Before the shop starts preparing it:</strong> while your order shows <em>Pending</em>, cancel it yourself from <a href="{{ route('orders') }}">My Orders</a>. You get a full refund, including delivery.</li>
    <li><strong>After it is being prepared or has been sent:</strong> contact us. We will cancel it if the shop can still stop it; otherwise you can return it under section 2.</li>
    <li><strong>Cancelled by us or the shop</strong> (for example, the item is out of stock): you get a full refund, including delivery.</li>
    <li>Card orders that are not paid within 24 hours are cancelled automatically. Nothing is charged.</li>
</ul>

<h2>2. Returns</h2>
<p>Tell us within <strong>{{ $days }} days of delivery</strong> if an item is:</p>
<ul>
    <li>damaged or faulty,</li>
    <li>not what you ordered, or</li>
    <li>different from its description on {{ Company::tradingName() }}.</li>
</ul>
<p>Send your order number and a photo of the problem to our customer service. We will arrange with the shop for a <strong>replacement, an exchange or a full refund</strong>, including the delivery fee. We may ask you to send the item back or hand it to the delivery person; we pay the return delivery in these cases.</p>
<p><strong>Change of mind:</strong> returns because you no longer want an item are at the shop's discretion. If the shop agrees, the item must be unused, in its original packaging and returned within {{ $days }} days of delivery, and the original delivery fee and the return delivery are not refunded.</p>

<h2>3. Exchanges</h2>
<p>If you received the wrong size, colour or variant, or a faulty item, we will exchange it for the right one when the shop has it in stock. If it does not, you get a refund.</p>

<h2>4. Items that cannot be returned</h2>
<p>For health, hygiene and freshness reasons, these items cannot be returned or exchanged <strong>unless they arrived damaged, faulty or wrong</strong>:</p>
<ul>
    <li>food, groceries and other perishable goods;</li>
    <li>opened beauty, skincare and personal care products;</li>
    <li>underwear and swimwear;</li>
    <li>items made or altered to your order (custom or personalised items).</li>
</ul>

<h2>5. How refunds are paid</h2>
<ul>
    <li><strong>Card payments</strong> are refunded to the same card through Bank of Maldives. We start the refund within <strong>7 working days</strong> of approving it; your bank may take a few more days to show it on your statement.</li>
    <li><strong>Bank transfer and cash on delivery</strong> are refunded by bank transfer to an account in your name within 7 working days of approval.</li>
    <li>We never refund card payments in cash or to a different card.</li>
    <li>Loyalty points and vouchers used on a cancelled order are returned to your account.</li>
</ul>

<h2>6. How to ask for a return or refund</h2>
<p>Contact customer service (see <a href="{{ route('policies.about') }}">About &amp; Contact</a>) with your order number, the item, what is wrong, and photos where relevant. We reply within 2 working days.</p>

<h2>7. Keep your receipt</h2>
<p>Please keep your order confirmation and payment receipt until your order has arrived and you are happy with it. They help us find your payment quickly.</p>
@endsection
