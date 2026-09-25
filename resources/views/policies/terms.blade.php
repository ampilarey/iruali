@extends('policies.layout')

@php
    use App\Support\Company;
    $name = Company::tradingName();
    $operator = Company::legalName() ?? $name;
@endphp

@section('policy_title', __('Terms & Conditions'))

@section('policy')
<p>These terms apply when you use <strong>{{ $name }}</strong> ({{ request()->getHost() }}) and when you buy anything through it. Please read them before you place an order. You accept them by ticking the box at checkout.</p>

<h2>1. Who we are</h2>
<p>{{ $name }} is an online marketplace operated by <strong>{{ $operator }}</strong>{{ Company::registrationNo() ? ' (registration no. '.Company::registrationNo().')' : '' }}{{ Company::address() ? ', '.Company::address() : '' }}, Republic of Maldives. Independent shops ("sellers") from islands across the Maldives list their products on {{ $name }}. We run the website, take payment for your order, and handle customer service, returns and refunds. Each product page shows which shop sells the item.</p>

<h2>2. Your account</h2>
<p>You need an account to check out. Keep your password private; you are responsible for orders placed from your account. Tell us straight away if you think someone else has used it. We may suspend accounts used for fraud or abuse.</p>

<h2>3. Products and prices</h2>
<ul>
    <li>Product descriptions, photos and specifications are provided by the seller. We check sellers before they can sell, and we remove listings that are wrong or misleading.</li>
    <li>All prices are in <strong>{{ Company::currency() }}</strong>. The price you pay is the price shown in your cart and at checkout, plus the delivery fee shown before you place the order.</li>
    <li>If a price is clearly wrong because of a mistake, we will contact you before sending the order, and you may cancel for a full refund.</li>
</ul>

<h2>4. Placing an order</h2>
<p>Your order is placed when you click <strong>Place Order</strong> and, for card payments, when Bank of Maldives confirms the payment. You will get an order confirmation email and can follow the order under <a href="{{ route('orders') }}">My Orders</a>. If an item turns out to be unavailable, we will tell you and refund you in full for that item.</p>

<h2>5. Payment</h2>
<ul>
    <li><strong>Card payment:</strong> Visa, Mastercard, American Express and Maestro, processed by Bank of Maldives (BML) on BML's secure payment page. Your card is charged in MVR when you pay. See <a href="{{ route('policies.security') }}">Payment Security</a>.</li>
    <li><strong>Cash on delivery</strong> and <strong>bank transfer</strong>, when shown at checkout.</li>
    <li>The merchant outlet country is the <strong>{{ Company::country() }}</strong>. The transaction currency is <strong>{{ Company::currency() }}</strong>.</li>
</ul>

<h2>6. Delivery</h2>
<p>We deliver within the Maldives only. Fees, delivery times and conditions are in our <a href="{{ route('policies.delivery') }}">Delivery Policy</a>.</p>

<h2>7. Cancellations, returns and refunds</h2>
<p>You can cancel an order yourself while it is still pending. Returns, exchanges and refunds are covered in our <a href="{{ route('policies.refunds') }}">Returns, Refunds &amp; Cancellations Policy</a>, including the items that cannot be returned.</p>

<h2>8. Legal restrictions, import and export</h2>
<ul>
    <li>We sell and deliver only within the Maldives. We do not ship abroad, so no import or export customs duties are charged on orders from {{ $name }}.</li>
    <li>Sellers must follow Maldivian law. Items that are prohibited or restricted in the Maldives, including under the Maldives Customs Service's rules, may not be listed. We remove any listing that breaks these rules.</li>
    <li>If an item needs an age check or a permit, the seller may ask for proof before delivery and cancel the order with a full refund if it cannot be provided.</li>
</ul>

<h2>9. Reviews and questions</h2>
<p>Reviews and questions you post must be honest and about the product. We may remove content that is abusive, misleading, advertising, or that shares someone's personal details.</p>

<h2>10. Our responsibility</h2>
<p>We make sure your payment reaches the right seller and that you get what you ordered, or your money back under our refund policy. We are not responsible for delays caused by weather, ferry or flight cancellations, or other events outside our control. Nothing in these terms limits your rights under the laws of the Maldives.</p>

<h2>11. Governing law</h2>
<p>These terms are governed by the laws of the Republic of Maldives. Any dispute will be handled by the courts of the Maldives.</p>

<h2>12. Keep your records</h2>
<p>We recommend that you keep a copy of your order confirmation, your payment receipt and these terms and policies as they were when you placed your order. Your orders stay available under <a href="{{ route('orders') }}">My Orders</a>.</p>

<h2>13. Changes and contact</h2>
<p>We may update these terms. The version that applies to your order is the one shown when you placed it. Questions? See <a href="{{ route('policies.about') }}">About &amp; Contact</a>.</p>
@endsection
