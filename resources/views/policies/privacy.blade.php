@extends('policies.layout')

@php use App\Support\Company; $name = Company::tradingName(); @endphp

@section('policy_key', 'privacy')
@section('policy_title', __('Privacy Policy'))
@section('policy_subtitle', __('How we collect, use and protect your personal information.'))

@section('policy')
<h2>1. Introduction</h2>
<p>{{ Company::legalName() ?? $name }} ("we", "us", "our") operates the {{ $name }} online marketplace. This Privacy Policy explains how we collect, use and protect your personal information when you use our website and services.</p>

<h2>2. Information we collect</h2>
<ul>
    <li><strong>Name, email address and mobile number:</strong> to create your account, sign you in and contact you about your orders.</li>
    <li><strong>Password:</strong> stored only in hashed (scrambled) form; we cannot read it.</li>
    <li><strong>Delivery address and island:</strong> to deliver your orders.</li>
    <li><strong>Order history:</strong> to show your orders, give loyalty points and handle returns.</li>
    <li><strong>Payment information:</strong> the payment method, amount, and the transaction reference and status sent to us by Bank of Maldives. <strong>We never receive or store your card number, expiry date or security code.</strong> Bank transfer slips you upload are stored privately.</li>
    <li><strong>Reviews and questions</strong> you post, with your name.</li>
    <li><strong>Sellers:</strong> business name, contact person, island and the details in the seller application.</li>
    <li><strong>Technical information:</strong> IP address, browser type and pages visited, in our server logs.</li>
</ul>

<h2>3. How we use your information</h2>
<ul>
    <li>Process, deliver and support your orders, cancellations, returns and refunds</li>
    <li>Send one-time codes for secure sign-in and two-step verification</li>
    <li>Send order confirmations, delivery updates and receipts by email or SMS</li>
    <li>Run loyalty points, referrals, your wishlist, cart and saved items</li>
    <li>Provide customer support and prevent fraud</li>
    <li>Send our newsletter and offers, only if you subscribed</li>
    <li>Improve our website and meet legal and accounting obligations</li>
</ul>

<h2>4. SMS and email communications</h2>
<p>By giving us your mobile number and email address, you agree to receive:</p>
<ul>
    <li>one-time verification codes (needed to verify and secure your account);</li>
    <li>order confirmations, status updates and receipts;</li>
    <li>our newsletter and offers, only if you subscribed.</li>
</ul>
<p><strong>Opt out:</strong> to stop the newsletter or offers, contact us using the details above. Order and security messages are part of the service and continue while you have an account.</p>

<h2>5. Card payment security</h2>
<p>All card payments are processed exclusively on Bank of Maldives' (BML) secure payment page. {{ $name }} does not store, view, process or retain any card details (card number, CVV, expiry) on its servers. Card data is handled entirely by BML using SSL/TLS encryption. See <a href="{{ route('policies.security') }}">Payment Security</a>.</p>
<p>We strongly recommend you keep a copy of your order confirmation and payment receipt for your records.</p>

<h2>6. Data security</h2>
<p>We use industry-standard measures to protect your personal data against unauthorised access:</p>
<ul>
    <li>encrypted data transmission (HTTPS / TLS) on every page;</li>
    <li>hashed passwords and optional two-step sign-in;</li>
    <li>role-based access for staff; each seller sees only the orders for their own shop;</li>
    <li>payment slips stored outside the public website, shown only to you and our administrators;</li>
    <li>every payment result checked directly with Bank of Maldives, never trusted from the browser;</li>
    <li>regular reviews of access and security.</li>
</ul>

<h2>7. Data sharing</h2>
<p>We do not sell or rent your personal information. We share it only with:</p>
<ul>
    <li><strong>The shop you buy from:</strong> your name, phone, delivery address and what you ordered, to prepare and deliver it;</li>
    <li><strong>Bank of Maldives (BML):</strong> to process card payments and refunds;</li>
    <li><strong>Delivery partners:</strong> your name, phone and address for delivery;</li>
    <li><strong>Our SMS and email providers:</strong> your phone number or email address, only to deliver messages;</li>
    <li><strong>Authorities:</strong> when required by Maldivian law.</li>
</ul>

<h2>8. Cookies</h2>
<p>We use only the cookies needed for the website to work: keeping you signed in, your cart, your language, and items you recently viewed or are comparing. We do not use advertising cookies.</p>

<h2>9. Data retention</h2>
<p>Order and payment records are kept for at least 5 years to meet accounting and tax rules. Other information is kept while your account is open. If you ask us to delete your account, we delete or anonymise your information except records we must keep by law.</p>

<h2>10. Your rights</h2>
<ul>
    <li>Access your personal data (see <a href="{{ route('account') }}">My Account</a>)</li>
    <li>Request correction or deletion of your data</li>
    <li>Opt out of the newsletter and offers</li>
</ul>

<h2>11. Contact</h2>
<p>For any privacy question or request, use the contact details at the top of this page or on <a href="{{ route('policies.about') }}">About &amp; Contact</a>. We may update this policy; the latest version is always on this page.</p>
@endsection
