@php $threshold = \App\Notifications\LowStockDigest::DEFAULT_THRESHOLD; @endphp
<h2>Photos</h2>
<ul>
    <li>Upload a main photo (JPEG, PNG or GIF, up to 2 MB). Square photos look best in the product grid; iruali makes smaller copies for phones automatically.</li>
    <li>Shoot in daylight on a plain background and show the real item, not a stock picture. Customers can return items that are "not as described", so the photo must match what you send.</li>
</ul>

<h2>Names and descriptions in both languages</h2>
<ul>
    <li>Every product has a name in English and in Dhivehi (<em>Name (English)</em> and <em>Name (Dhivehi)</em> on the product form). Customers who browse in Dhivehi see the Dhivehi name; if you leave it empty the English name is shown.</li>
    <li>Write what it is, the size or weight, the material and what is included. Put the brand in the <em>Brand</em> field, not in the name, so brand filters work.</li>
    <li>Give each product its own SKU (your own code). It must be unique across the whole site and is printed on orders and in the low-stock email.</li>
</ul>

<h2>Pricing</h2>
<ul>
    <li>Prices are in MVR and include everything you charge for the item. Delivery is charged separately by iruali and is not yours to set.</li>
    <li>To show a discount, enter the old price in <em>Compare-at price</em>; it must be higher than the price. Customers then see the saving and the product appears under Deals. Set <em>Sale ends</em> to show a countdown.</li>
    <li>iruali's commission is taken from the item price (see <a href="{{ route('seller.help.show', 'commission-payouts') }}">Commission and payouts</a>). Price with that in mind.</li>
</ul>

<h2>Stock</h2>
<ul>
    <li><em>Stock</em> is how many you can send right now. When it reaches 0 the product shows as sold out and cannot be ordered; customers can ask to be told when it is back.</li>
    <li><em>Low-stock alert at</em> is when iruali warns you. Every morning you get one email listing products and variants at or below this level (default {{ $threshold }} when left blank). Turn it off under <a href="{{ route('seller.settings.notifications') }}">Settings → Notifications</a>.</li>
    <li>Stock is reduced when an order is placed and put back if the order is cancelled or a return is approved with "put back in stock".</li>
</ul>

<h2>Going live</h2>
<p>New listings are checked by iruali before they appear on the storefront (edits to a live product show straight away). They can only be approved once your shop has completed its <a href="{{ route('seller.help.show', 'getting-approved') }}">checklist</a>. You can see which products are still waiting under <a href="{{ route('seller.products.index') }}">Products</a>.</p>
