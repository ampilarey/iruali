@php $about = \App\Services\OnboardingService::ABOUT_MIN_LENGTH; @endphp
<h2>Two approvals: your shop, then each product</h2>
<p>When you apply to sell, iruali reviews your shop (name, what you sell, island and phone) and approves it. Separately, every product you add is checked before it goes live. Products can only be approved once your shop has completed the checklist below. Until then they stay hidden from the storefront, but you can keep adding and editing them.</p>

<h2>The checklist</h2>
<p>The <a href="{{ route('seller.dashboard') }}">Dashboard</a> shows your progress and links to each step. The moment all seven are done your shop is marked ready and iruali is told.</p>
<ol>
    <li><strong>Shop logo</strong> – a square image (JPEG, PNG or WebP, up to 2 MB) shown next to your shop name. <a href="{{ route('seller.profile') }}#branding">Profile → Shop logo and banner</a>.</li>
    <li><strong>Shop banner</strong> – a wide image (up to 4 MB) across the top of your shop page.</li>
    <li><strong>About your shop</strong> – at least {{ $about }} characters about what you sell and where you are. Customers see it on your shop page.</li>
    <li><strong>Phone number</strong> – so iruali and boat crews can reach you about orders.</li>
    <li><strong>Delivery options</strong> – how you send orders and whether you ship to other islands. <a href="{{ route('seller.profile') }}#delivery">Profile → Delivery options</a>.</li>
    <li><strong>Bank account</strong> – where iruali pays you. <a href="{{ route('seller.settings.bank') }}">Settings → Bank account</a>.</li>
    <li><strong>First product</strong> – add at least one product. <a href="{{ route('seller.products.create') }}">Add product</a>.</li>
</ol>

<h2>After approval</h2>
<ul>
    <li>Each new product is approved by iruali, usually within a working day. You can see which are still waiting under <a href="{{ route('seller.products.index') }}">Products</a>.</li>
    <li>Keep your profile and bank details current. A changed bank account is checked again before the next payout.</li>
    <li>iruali can suspend a shop that breaks the <a href="{{ \Illuminate\Support\Facades\Route::has('policies.seller_terms') ? route('policies.seller_terms') : route('policies.terms') }}">seller terms</a> (late shipping, wrong items, prohibited goods). A suspended shop's products are taken off the storefront and it cannot use the Seller Centre until iruali reinstates it.</li>
</ul>
