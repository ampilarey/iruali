@extends('policies.layout')

@php use App\Support\Company; $name = Company::tradingName(); @endphp

@section('policy_title', __('Privacy Policy'))

@section('policy')
<p>{{ Company::legalName() ?? $name }} ("we") runs {{ $name }}. This policy explains what personal information we collect, why, who we share it with, and how we protect it.</p>

<h2>1. Information we collect</h2>
<ul>
    <li><strong>Account details:</strong> your name, email address, mobile number and password (stored only in scrambled, hashed form).</li>
    <li><strong>Order details:</strong> delivery address and island, the products you buy, order history, and messages to customer service.</li>
    <li><strong>Payment information:</strong> the payment method, amount, and the transaction reference and status that Bank of Maldives sends us. <strong>We never receive or store your card number, expiry date or security code.</strong> Bank transfer slips you upload are kept privately.</li>
    <li><strong>Content you post:</strong> product reviews and questions, with your name.</li>
    <li><strong>For sellers:</strong> business name, contact person, island and the details in your seller application.</li>
    <li><strong>Technical information:</strong> IP address, browser type and pages visited, in our server logs; and the small cookies described in section 6.</li>
</ul>

<h2>2. Why we use it</h2>
<ul>
    <li>to create your account and keep it secure (including one-time codes and two-step sign-in);</li>
    <li>to take payment, deliver your order, and handle cancellations, returns and refunds;</li>
    <li>to send order confirmations, delivery updates and receipts by email or SMS;</li>
    <li>to run loyalty points, referrals and your wishlist, cart and saved items;</li>
    <li>to answer your questions and prevent fraud and abuse;</li>
    <li>to send our newsletter, only if you subscribed (you can unsubscribe any time);</li>
    <li>to meet our legal and accounting obligations.</li>
</ul>

<h2>3. Who we share it with</h2>
<p>We do not sell or rent your personal information. We share only what is needed:</p>
<ul>
    <li><strong>The shop you buy from:</strong> your name, phone number, delivery address and what you ordered, so they can prepare and deliver it.</li>
    <li><strong>Bank of Maldives (BML):</strong> to process card payments and refunds on its secure payment page.</li>
    <li><strong>Delivery partners:</strong> your name, phone and address to deliver your order.</li>
    <li><strong>Email and SMS providers:</strong> to send you messages about your account and orders.</li>
    <li><strong>Authorities:</strong> when Maldivian law requires it.</li>
</ul>

<h2>4. How we protect it</h2>
<ul>
    <li>All pages are served over encrypted HTTPS (TLS) connections.</li>
    <li>Card payments happen on Bank of Maldives' own secure page. Card details go straight to the bank and never pass through or stay on our servers. See <a href="{{ route('policies.security') }}">Payment Security</a>.</li>
    <li>Passwords are hashed, and you can turn on two-step sign-in in your account.</li>
    <li>Only authorised staff can see customer data, and each seller sees only the orders for their own shop.</li>
    <li>Payment slips are stored outside the public website and are shown only to you and our administrators.</li>
    <li>We check every payment result directly with Bank of Maldives and do not rely on information passed through your browser.</li>
</ul>

<h2>5. How long we keep it</h2>
<p>We keep order and payment records for at least 5 years to meet accounting and tax rules. Other information is kept while your account is open. You can ask us to delete your account; we will delete or anonymise your information except records we must keep by law.</p>

<h2>6. Cookies</h2>
<p>We use only the cookies needed for the website to work: keeping you signed in, your cart, your language, and items you recently viewed or are comparing. We do not use advertising cookies.</p>

<h2>7. Your choices and rights</h2>
<ul>
    <li>See and update your details in <a href="{{ route('account') }}">My Account</a>.</li>
    <li>Ask for a copy of your information, a correction, or deletion.</li>
    <li>Stop the newsletter at any time by contacting us.</li>
</ul>

<h2>8. Contact</h2>
<p>For any privacy question or request, contact us through <a href="{{ route('policies.about') }}">About &amp; Contact</a>. We may update this policy; the date at the top shows the latest version.</p>
@endsection
