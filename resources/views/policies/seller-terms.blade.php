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

{{-- A new version in substance: raise SellerTerms::BUILT_IN_VERSION and BUILT_IN_CHANGES, so every shop agrees again. --}}
@section('policy')
<p class="text-sm font-semibold text-dark" data-seller-terms-version>{{ __('Version of :date', ['date' => SellerTerms::versionLabel()]) }}</p>
<p>{{ $t('These terms are the agreement between {trading_name} and every shop ("seller", "you") that sells on it. You accept them when you apply to sell, and again each time a new version is published: the Seller Centre asks you to agree before you can use it. They apply alongside the Terms & Conditions customers accept.') }}</p>

<h2>1. {{ __('Who does what') }}</h2>
<ul>
    <li>{{ $t('{trading_name} runs the marketplace: the website, customer service, payments and payouts. You are an independent business selling your own products, and you are the seller of each item you sell.') }}</li>
    <li>{{ $t('Customers pay {trading_name}, which collects the payment on your behalf and pays you your earnings as set out below.') }}</li>
</ul>

<h2>2. {{ __('Your shop') }}</h2>
<ul>
    <li>{{ $t('{trading_name} reviews each shop before it can sell and each product before it goes live. A shop must complete its checklist (logo, banner, about text, phone number, delivery options, bank account and a first product) before its products can be approved.') }}</li>
    <li>{{ $t('Your details (business name, owner, phone number, address, home island and bank account) must be true and kept up to date. The shop\'s owner is responsible for everything done for the shop, including by staff members the owner invites. Keep your sign-in private.') }}</li>
    <li>{{ $t('The "Verified business" badge is optional. For it, {trading_name} checks your business registration and the owner\'s ID card, keeps them private and uses them only to verify the shop and meet its legal duties.') }}</li>
</ul>

<h2>3. {{ __('Listings') }}</h2>
<ul>
    <li>{{ $t('Changes to a live product (names, descriptions, prices, photos, options and offers) are also reviewed before customers see them; until then the approved version stays on sale. Stock changes apply at once. {trading_name} may let a shop it trusts publish new products and changes without review, and may withdraw that at any time.') }}</li>
    <li>{{ $t('Listings must be honest: real photos, accurate names in English and Dhivehi, correct prices in MVR and true stock. Prohibited or restricted goods under Maldivian law may not be listed.') }} <a href="{{ route('policies.prohibited') }}" data-prohibited-link>{{ __('See the Prohibited & Restricted Items list.') }}</a></li>
    <li>{{ $t('Use only photos, text, videos, logos and brand names you own or have permission to use. This includes what "Import from a link" copies from another website: check it, rewrite it where needed and remove anything you may not use. Never list fake or copied goods, or claim to be a brand\'s authorised seller when you are not.') }}</li>
    <li>{{ $t('You are responsible for the products you sell, for their quality and for complying with Maldivian law, including any permits your goods need.') }}</li>
</ul>

<h2>4. {{ __('Pre-orders and "coming soon"') }}</h2>
<ul>
    <li>{{ $t('Pre-orders and "coming soon" are only for shops {trading_name} allows, each separately. {trading_name} may withdraw either at any time; orders already placed stay valid.') }}</li>
    <li>{{ $t('A pre-order is a promise. List only items you can really get at the price shown, and ship by the date or within the days you set. Buy the item only once the customer has paid what the listing asks: the full price, the booking fee, or, for "pay once I confirm", the full price after you have confirmed.') }}</li>
    <li>{{ $t('For "pay once I confirm" items, check quickly whether you can get the item and press "I can get it" or "I can\'t get it". Confirm only if you can supply it at that price.') }}</li>
    <li>{{ $t('Record "Stock arrived" when the item reaches you. Never send a pre-order before it is fully paid.') }}</li>
    <li>{{ $t('If the customer cancels, a booking fee is kept and paid to you less commission. If you cancel, can\'t get the item, move the date later or ship later than the product allows, the customer is refunded in full and you are paid nothing for that order.') }}</li>
    <li>{{ $t('A "coming soon" product must be a real product you expect to sell from the launch day you give.') }}</li>
</ul>

<h2>5. {{ __('Orders and shipping') }}</h2>
<ul>
    <li>{{ $t('Customers pay {trading_name} by card, by bank transfer (once {trading_name} has confirmed it) or, where offered, in cash on delivery. Send an order only once the Seller Centre shows it as paid, except cash-on-delivery orders, and keep its status up to date (processing, sent with a tracking note, delivered).') }}</li>
    <li>{{ $t('For cash on delivery, collect the full amount when you hand the order over and pass it to {trading_name} as instructed. Your earnings on it become payable once {trading_name} has the cash.') }}</li>
    <li>{{ $t('You must ship within {late_shipment_days} days of the customer\'s payment. Later shipments count as late on your performance record; repeated late shipments can lead to suspension.') }}</li>
    <li>{{ $t('Delivery fees: unless you set your own, {trading_name}\'s fees apply and {trading_name} keeps them. If you set your own fees (Seller Centre → Settings → Delivery & pickup, up to {trading_name}\'s limit), you deliver your parts yourself and are paid your fee and your bulky-item charges with your earnings, with no commission on them. Never charge customers for delivery outside {trading_name}.') }}</li>
    <li>{{ $t('You choose your home island and where you deliver: all islands, your island and Greater Malé, or your island only, plus any islands you add. Keep it accurate. Customers only see your products as deliverable where you say you deliver, and you must deliver the orders placed for those islands. Pickup from your shop, if you offer it, is open to every customer.') }}</li>
    <li>{{ $t('Once any shop has sent its part, the order can no longer be cancelled; problems are then handled as returns.') }}</li>
</ul>

<h2>6. {{ __('Returns, disputes and chargebacks') }}</h2>
<ul>
    <li>{{ $t('Customers may ask to return items within {return_window_days} days of delivery. {trading_name} reviews each request and decides whether to approve it; you must not refund customers directly.') }}</li>
    <li>{{ $t('When a return is approved, your share of the returned items (their price less the commission on them) is deducted from your next payout. If you delivered the part at your own fee and the customer is refunded for delivery, that charge is deducted too. {trading_name}\'s own delivery fees are never deducted from you.') }}</li>
    <li>{{ $t('When a customer reports a problem (not delivered, damaged, wrong or not as described), {trading_name} looks at the order, the messages and any photos, may ask you for information, and decides. Reply within 2 business days.') }}</li>
    <li>{{ $t('If the customer\'s bank reverses a card payment (a chargeback) because of your part of the order, for example it was not delivered or was the wrong item, the amount is deducted from your earnings.') }}</li>
</ul>

<h2>7. {{ __('Commission') }}</h2>
<ul>
    <li>{{ $t('{trading_name} keeps a commission of {commission_rate} of the item price of everything you sell, unless a different rate has been agreed for your shop in writing. The rate in force when an order is placed applies to that order.') }}</li>
    <li>{{ $t('Your earnings on an order are its item subtotal less your discounts and commission, plus your own delivery charge when you deliver the part at your own fee. Vouchers, loyalty points and {trading_name}\'s delivery fees are {trading_name}\'s and do not change your earnings.') }}</li>
</ul>

<h2>8. {{ __('Payouts') }}</h2>
<ul>
    <li>{{ $t('Earnings become payable once your part is delivered and the customer\'s payment is confirmed. {trading_name} pays payable earnings {payout_schedule}, on {payout_day}, by bank transfer to the account you registered in the Seller Centre, less any open deductions.') }}</li>
    <li>{{ $t('You must register a bank account in the Maldives in your own or your business\'s name. No payout is made to a shop without a registered account, and a new or changed account may be checked before the first transfer to it.') }}</li>
    <li>{{ $t('Each payout comes with a statement in the Seller Centre and a reference you can match against your bank statement. Tell us within 14 days if a payout looks wrong.') }}</li>
    <li>{{ $t('What you owe {trading_name} (returns, refunds, chargebacks and adjustments) is deducted from your payouts. If your earnings do not cover it, you pay the difference when asked.') }}</li>
</ul>

<h2>9. {{ __('Tax') }}</h2>
<ul>
    <li>{{ $t('You are responsible for your own tax, including registering for GST with MIRA when the law requires it, and for the tax details you enter in Seller Centre → Settings → Tax.') }}</li>
    <li>{{ $t('If you are GST-registered, the tax invoices for your sales are issued in your name through {trading_name} from those details. {trading_name} invoices you for its commission.') }}</li>
</ul>

<h2>10. {{ __('Customer details and messages') }}</h2>
<ul>
    <li>{{ $t('Customers\' names, phone numbers and addresses are shared with you only to prepare, deliver and support their order. Use them for nothing else: no marketing, no adding customers to lists or groups, and no sharing with anyone except the people delivering the order.') }}</li>
    <li>{{ $t('Talk to customers about their order through {trading_name}\'s messages where you can. A call or WhatsApp message to arrange the delivery or a return of that order is fine.') }}</li>
    <li>{{ $t('Never ask a customer to pay you outside {trading_name}, to cancel and buy from you directly, or to buy from another website or shop. Such sales go around the checks that protect customers and break these terms.') }}</li>
    <li>{{ $t('Keep customers\' details safe, and delete them once you no longer need them for the order, a return or the law.') }}</li>
</ul>

<h2>11. {{ __('Fair dealing') }}</h2>
<ul>
    <li>{{ $t('No fake or paid reviews, of your own products or anyone else\'s, and nothing offered in return for a good review or for changing a bad one.') }}</li>
    <li>{{ $t('Do not copy other shops\' listings or photos, misuse {trading_name}\'s name, or try to get around the commission, the review of listings or any other part of the marketplace.') }}</li>
</ul>

<h2>12. {{ __('Your content') }}</h2>
<ul>
    <li>{{ $t('You keep the rights in your photos, text and logo. You let {trading_name} use them free of charge to show and promote your products on the website, in emails and on social media while you sell on {trading_name}, and in material already published.') }}</li>
    <li>{{ $t('If someone says a listing uses their rights (photos, text or a brand), {trading_name} may take it down while it checks, and you must help settle the claim.') }}</li>
</ul>

<h2>13. {{ __('Responsibility') }}</h2>
<ul>
    <li>{{ $t('You are responsible for your products and what you say about them. If a customer or an authority makes a claim against {trading_name} because of your products or because you broke these terms, you cover {trading_name}\'s reasonable costs.') }}</li>
    <li>{{ $t('{trading_name} runs the marketplace with care but cannot promise it is always available or free of errors. It is not liable to you for lost profits or indirect losses, and its liability for an order is limited to the commission it earned on that order, except where Maldivian law does not allow such a limit.') }}</li>
</ul>

<h2>14. {{ __('Suspension and ending') }}</h2>
<ul>
    <li>{{ $t('{trading_name} may suspend or close a shop that breaks these terms, lists prohibited or fake goods, misuses customers\' details, takes sales outside {trading_name}, repeatedly ships late or sends wrong or damaged items. A suspended shop\'s products are removed from the storefront.') }}</li>
    <li>{{ $t('While it checks a serious problem, such as suspected fraud, {trading_name} may hold a shop\'s payouts and tells the shop why.') }}</li>
    <li>{{ $t('You may close your shop at any time. Orders already placed must still be fulfilled, and payable earnings are paid on the next payout after any open returns are settled.') }}</li>
</ul>

<h2>15. {{ __('New versions of these terms') }}</h2>
<ul>
    <li>{{ $t('{trading_name} may update these terms. Each version is dated at the top of this page. You are told what changed and must agree to the new version before you can use the Seller Centre again; if you do not agree, you may close your shop. Orders placed before a change stay under the version that applied then.') }}</li>
    <li>{{ $t('The commission, payout and shipping settings may also change. Changes apply to orders placed after they are published; the Seller Centre shows the current values.') }}</li>
</ul>

<h2>16. {{ __('Law and language') }}</h2>
<p>{{ $t('These terms are governed by the laws of the Republic of Maldives.') }} {{ $t('Please contact {trading_name} first about any disagreement; if it cannot be settled, it goes to the courts of the Maldives.') }}</p>
<p>{{ $t('These terms are written in English and translated into Dhivehi. If the two differ, the English version applies.') }}</p>
@endsection
