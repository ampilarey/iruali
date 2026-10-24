# Features, by area

One paragraph per feature area with the routes and pages involved. Route names are the ones in
`php artisan route:list`; web routes live in `routes/web.php` and `routes/web/*.php`, API routes
in `routes/api.php`. Every customer-facing page exists in English and Dhivehi (RTL).

## Customer

**Storefront and catalogue.** The home page (`/`, `home`) shows banners, departments, deals
and featured products; `/shop` lists everything with sorting and filters, `/products` and
`/products/{slug}` (`products.show`) are the catalogue and product page (photos with WebP
variants, variants with their own price and stock, Q&A, reviews with photos, "notify me" stock
alerts, related products), `/categories` and `/categories/{slug}` the departments,
`/shops/{seller}` (`sellers.show`) the shop pages, `/deals` the discounted items. Search is
`/search?q=` with live suggestions from `/search/suggest` (products, departments and brands); the
department picker in the header narrows it. `/compare` holds up to a few products side by side.
`sitemap.xml` and `robots.txt` are generated from the database (`SitemapController`).

**Delivering to an island.** Customers choose the island they shop for; nothing is ever gated.
With no island chosen (first visit, search engines) every listing shows everything, with a small
"Choose your island" line in the header; a first visit also opens "Where should we deliver?" (an
island list loaded from `GET /delivery/island`, searchable in English and Dhivehi, grouped by atoll
north to south, plus "Use my location": the browser's position goes to `delivery.island.locate`,
which picks the nearest switched-on island by haversine distance, within 120 km). Closing it
(`delivery.island.dismiss`) keeps everything shown and stops asking. Once chosen, the header reads
"Delivering to: <island> (change)" on every storefront page, next to a three-way view switch
(`delivery.island.view`): **Delivers to <island>** (default: products whose shop delivers there,
from Malé shops that deliver everywhere to local shops), **Only shops on <island>** (shops whose
home island it is) and **Show everything** (all listings; items that can't be delivered carry
"Doesn't deliver to <island>"). The island and view are kept in the session, a one-year cookie
and, for signed-in customers, on the account (`users.shopping_island_id`, `shopping_view`); with
none of those the default address's island counts. `App\Services\ShoppingIsland` holds the choice,
`App\Services\ShopCoverage::apply()` narrows every listing (home page rows and shops, `/products`,
`/shop`, departments, deals, search and suggestions, brand pages, campaign pages, "related" and
"bought together"); a shop's own page always lists all its products, and the wishlist, compare,
recently viewed and the shop page label rather than hide. The home page adds "Shops on <island>"
when shops are based there; an island without shops shows "No shops on <island> yet. These shops
deliver to you" (and, in "Only shops on", one click back to "Delivers to"). The product page's
delivery box quotes the same island and says "Delivers to" / "Doesn't deliver to"; checkout's new
address starts at it. Checkout shows a notice on each shop that can't reach the delivery island
(collect from the shop if it offers pickup, or remove its items), and `DeliveryService::
prepareOrder()` refuses the order on the server; a gift goes to the gift's address, so its island
decides. Sitemap, merchant feeds, JSON-LD and robots are unaffected. JSON for the phone app:
`GET /api/v1/islands`, `GET /api/v1/islands/nearest?lat=&lng=`, `GET|PUT /api/v1/user/shopping-island`
(`island_id`, `view`), and `island_id` / `view` on `GET /api/v1/products`. The islands themselves
(207 inhabited islands from `database/data/islands.json`: names and atolls in English and
Dhivehi, map positions, the prayer-times dataset's id) are loaded by a migration
(`App\Support\IslandList::load()`, also run by `IslandSeeder`; safe to run again; existing rows
are matched by English name so addresses keep their ids, and the old "Addu City" row is switched
off unless something points at it). Delivery fees per shop come in a later change; fees, earnings,
GST and refunds are as before.

**Brands.** `/brands` (`brands.index`) lists every brand with something on sale, A to Z with a
filter box, plus the most popular brands once the list is long; the menu, the footer and a "Shop by
brand" row on the home page link to it. `/brands/{slug}` (`brands.show`) shows one brand: its logo
and description (English and Dhivehi), a wide banner across the top when it has one (uploaded
under Admin → Brands → Edit, at least 1000 px wide, 1600 × 400 ideal; phones load a smaller WebP
copy and show its middle; it is also the picture when the page is shared), the shops that sell it,
its departments as quick filters,
then its products with the usual filters. Each brand has one address: old name-based links,
other capitalisations and addresses from before a rename or merge redirect there (301). Brands
with fewer than three products on sale are kept out of search engines (`noindex, follow`) and out
of the sitemap until they grow. The brand list itself is shared: shops pick from it on the product
form, and whatever they type is matched to an existing brand ignoring capitals, spaces and
punctuation ("SAMSUNG", "Samsung" and "samsung." are one brand), while "N/A", "Generic" and
similar mean no brand. Products keep the brand name in `products.brand` and point at the brand
through `products.brand_id`; `BrandService` keeps the two in step on every save, and
`php artisan brands:sync` links anything imported straight into the database.

**Authorised sellers, brand campaigns and sales by brand.** On a brand's admin page
(`/admin/brands/{id}/edit`, "Authorised sellers") an admin marks shops as authorised sellers of the
brand, picking from the shops that sell it or searching any approved shop by name or email
(`admin.brands.sellers.store` / `admin.brands.sellers.destroy`, table `brand_authorised_sellers`;
both are in the audit log as `brand.seller_authorised` / `brand.seller_unauthorised`). Their
products of that brand show an "Authorised seller" badge next to the shop name on the product
page, and they come first, badged, in the brand page's "Sold by" strip. A campaign can be for one
brand ("Only for brand" on the campaign form, `campaigns.brand_id`): shops can then put in only that
brand's products (the Seller Centre lists only those and the server refuses others), the campaign
page shows the brand's logo and links to its page, and the brand page shows a strip for the brand's
running campaigns. A product whose brand changes after it went in stops getting the campaign price,
and a campaign cannot be given a brand while it holds other brands' products (the admin removes
them first). Merging two brands carries the authorised sellers and brand campaigns over to the
brand that stays. `/admin/analytics` has a "Top brands" table: units, revenue (MVR) and share of
branded revenue per brand over the last 30 days, counted from the same order lines as the top
products and sellers (orders that are not cancelled), with unbranded products as their own row.

**Following brands.** Each brand page has a Follow / Following button (`POST` / `DELETE`
`/brands/{slug}/follow`, `brands.follow` / `brands.unfollow`, also under `/dv`; a guest is sent to
sign in and comes back to the brand page), and shows the number of followers once there are five.
`/account/brands` (`account.brands`, linked from the account menu) lists the brands a customer
follows as tiles with an Unfollow button. `php artisan brands:notify-followers` runs daily at 09:00
Maldives time and sends each follower at most one email (`BrandFollowDigest`, queued) with the
products of their brands that went on sale (`products.sale_started_at`: when shoppers could first
see the markdown) or were approved into a campaign that is running now, since the last email
(`brand_follows.notified_at`, else since they followed): up to twelve, taken in turns from each
brand, with a link to each brand page for the rest. Nothing is sent when there is nothing new and
the same news is never sent twice. The email is in the customer's language and follows "Brands you
follow" (email or off) under `/account/notifications`; customers who switched marketing emails off
get none. Admin → Brands shows each brand's followers, and a merge moves them to the brand kept.

**Dhivehi brand names.** A brand can have a name in Thaana ("Name in Dhivehi", `brands.name_dv`,
set under Admin → Brands → edit). Dhivehi pages show it wherever shoppers see the brand: the
directory (tiles and A–Z list, with the English name small underneath and still filed under its
English letter), the brand page's title, heading, breadcrumbs and SEO tags, the product page's brand
link, "Shop by brand" on the home page, search suggestions, the catalogue's brand filter and the
chip of a picked brand, product cards and the compare table (`Product::brandLabel()`; the listings
eager-load `brandModel`, so cards add no query per product).
`products.brand` keeps the English name. The Dhivehi name is matched like an old name (a
`brand_aliases` key, refused when another brand has it): shops typing it get the brand,
`/brands/<the name>` redirects to the brand page, and earlier Dhivehi names keep matching. It is
part of the products' search text, rewritten when it changes, so Thaana searches find them.

**Accounts.** Register and sign in at `/register` and `/login` (email/phone OTP verification at
`auth/send/*/otp` and `auth/verify/*/otp`, password reset at `/forgot-password`), optional
two-step sign-in with an authenticator app (`/profile/2fa/setup`, `/2fa`), and the account area
under `/account` (profile, password, notification preferences at `/account/notifications`, the
address book at `/account/addresses` with the island picker and a default address). Referral
codes and loyalty points live on the account; `locale/switch` changes the language.

**Sign in with Google, Facebook or Apple.** "Continue with …" on `/login` and `/register`
(`social.redirect`, `social.callback`; Apple posts to `social.apple.post`), shown per provider once
its keys are set (`docs/SOCIAL_LOGIN.md`). New customers get an account without a password;
existing accounts are joined only when the provider verified the email; staff must use their
password; two-step sign-in, bans and the guest-cart merge apply as with a password. My Account →
Security lists the connected accounts (`account.social.unlink`) and emails a link to set a
password (`account.password.link`).

**Cart, wishlist, saved items.** `/cart` (`cart`, `cart.add`, `cart.addMany` for bundles,
`cart.update`, `cart.remove`, `cart.clear`, `cart.saveForLater` → `/saved/*`), vouchers applied
on the cart (`cart.applyVoucher` / `cart.removeVoucher`), `/wishlist` with add/remove/clear. Guest
carts are kept for 30 days.

**Wishlist price drops.** A wishlisted item remembers its final price (campaign and markdown
included). Every day at 10:00 Maldives time `wishlist:price-drops` sends each customer at most one
email (and a push on devices that allowed it) listing items now at least 5% and MVR 10 under the
last price they were told about; that price then becomes the reference, so a drop is never
repeated. "Price drops on your wishlist" on `/account/notifications` (or the marketing opt-out)
turns it off; hidden, sold-out and deleted products are skipped. `/wishlist` shows "MVR X less than
when you saved it".

**Product videos.** A shop can add a YouTube (or Shorts), TikTok, Instagram or Facebook video link
to a product (its own fieldset in the product form, stored as `products.video_provider` and
`video_id`). The product page shows a play button; the provider's player is only loaded when the
shopper presses it, and the Content-Security-Policy allows frames from those four players only.

**Checkout and payment.** `/checkout` collects the delivery address (saved address or island
picker, which sets the delivery zone and fee), lets points be redeemed (`checkout.redeemPoints`),
and places the order at `POST /orders` (`orders.store`). Only signed-in customers check out (a
password, or a code by SMS or email); guest checkout was removed, and orders placed as a guest
before keep their signed links (`/orders/guest/{order}/{token}`). The only payment method is a card
through BML Connect: the customer is sent to BML's page and comes back to
`/payments/bml/return/{order}/{token?}` (the token is the guest order's code; signed-in customers have none); BML's webhook (`POST /api/payments/bml/webhook`) and a
re-fetch of the transaction confirm the payment, "Pay now" (`payments.bml.pay`) retries an unpaid
order, and unpaid card orders are cancelled after 24 hours with their stock released. See
`PAYMENTS_BML.md`. `BML_FAKE=1` (never in production) walks the whole flow without BML.

**Orders after purchase.** `/orders` and `/orders/{order}` (`orders.show`) show the order with
one progress bar per shop part, tracking details, messages to the shop
(`orders.messages.store`), a return request per part (`orders.returns.store`, photo upload), a
dispute per part (`orders.disputes.store`), cancel while still pending (`orders.cancel`), buy
again (`orders.buyAgain`) and a printable receipt (`orders.receipt`). `/track` lets anyone follow
an order by number and email/phone (`order.track.*`). Emails and SMS go out on placement,
payment, status changes, shipping and returns, according to the customer's preferences.

**Help and policies.** The public policy pages are written as one store ("iruali", "we"; partner
businesses only where they matter: tax invoices, data sharing), with no mention of shops, sellers or a
marketplace (`CustomerPoliciesTest` guards it); the private Seller Terms speak to shops. `/help` and the policy pages (`/terms`, `/privacy-policy`,
`/refund-policy`, `/delivery-policy`, `/payment-security`, `/about`, `/seller-terms`,
`/prohibited-items`), editable by admins at `/admin/legal`. **Prohibited & Restricted Items**
(`policies.prohibited`) lists what may not be sold (alcohol, pork, tobacco and vapes, drugs and
prescription medicines, weapons, protected wildlife, fakes, and more), what needs a permit or care
(MFDA-registered medicines and supplements, food, cosmetics, drones and radio equipment, chemicals,
plants, dangerous goods, used items, age limits) and that a shop importing for pre-orders is the
importer; it is public and linked from the footer, the policy menu, the customer terms, the Seller Terms, the
shop application and the product form. **The Seller Terms are not public** (`SellerPoliciesAccess`,
`SellerTerms::visibleTo()`): they open for shops (owners and staff), iruali's team and, while shop
applications are open, any signed-in visitor (a visitor not signed in is asked to sign in). While
applications are paused or hidden everyone else gets a 404, and the policy menu lists them only for
those who may open them. Newsletter sign-up at `POST /newsletter` with an unsubscribe link.

## Seller

**Becoming a shop.** `/seller/apply` (`seller.apply`) takes the application; an admin approves,
rejects or suspends it. Onboarding steps, `/seller/profile` (logo, banner, description, delivery
notes, "ships to islands"), `/seller/settings/bank` (payout account, verified by an admin) and
`/seller/settings/notifications`. Seller help guides at `/seller/help/{guide}`.

**Seller Terms, with versions.** `/seller-terms` is the agreement every shop signs (who does what,
listings and rights to photos/text, pre-orders and "coming soon", payments incl. bank transfer and
cash on delivery, returns, disputes and chargebacks, commission, payouts and deductions, GST, customer
details only for their order and no selling outside iruali, fair dealing, content licence,
responsibility, suspension, new versions, Maldivian law, English governs). It is dated
(`App\Support\SellerTerms::version()`: the built-in `BUILT_IN_VERSION`, or a later one an admin
publishes). A shop agrees when it applies, and **must agree again to every new version before it can
use the Seller Centre**: `ShopAccess` sends it to `/seller/terms` (`seller.terms.show`, a 409 for
JSON) showing what changed; only the owner agrees (`seller.terms.agree`, owner-only), staff see the
page and wait. Customers can still order meanwhile. Each agreement is kept (`users.seller_terms_version`
/ `seller_terms_agreed_at`, and a `seller_terms_agreements` row with who, IP and browser; audit
`seller_terms.agreed`). Admin → Legal pages shows the version and how many shops agreed, and
"Publish as a new version" (with a note of what changed) asks every shop again and emails active
shops (`SellerTermsUpdated`); a save without it (a typo fix) asks no one. Admin → Sellers marks shops
that have not agreed ("Has not agreed to the current Seller Terms"); a shop's performance page shows
the version and date it agreed. Changing commission or payout settings is not a new version. When the
built-in text changes in substance, raise `BUILT_IN_VERSION` and `BUILT_IN_CHANGES`.

**iruali's team never sells; iruali sells through a shop.** The admin area is for running the website,
the shops and the money: it has no product form of its own. iruali sells like any shop, through a
registered shop (e.g. "iruali Direct"): applied for with "Sell on iruali", approved in Admin → Sellers,
its commission can be 0% there, and pre-orders or "coming soon" are allowed to it like to any shop.
**An admin account may itself be iruali's own shop** (`User::maySell()`): the owner opens the shop while
signed in as admin, so one email, mobile and password open both, and each side links to the other
("Switch to Seller Centre" at the bottom of the admin menu, "Switch to Admin" at the top of the Seller
Centre; Admin → Sellers marks the shop "iruali's own shop (admin account)"). Support, finance and
catalogue accounts never sell: they can't apply for a shop (`seller.apply` sends them away, and their
account menu offers neither "Sell on iruali" nor the Seller Centre), be approved as one (Admin → Sellers
refuses; one that became a shop before is marked "iruali team account: cannot sell"), open the Seller
Centre (`ShopAccess`) or join a shop's staff. A shop account (owner or staff) is never made team
(`StaffRoleController`, `staff:create`), so the admin account comes first and the shop second.

**Home island and delivery area.** Every shop has a home island (picked from the islands list on
`/seller/profile`, required on the shop application `seller.apply`, a step on the onboarding
checklist, and asked on the dashboard of shops from before) and one of three delivery types:
**All islands** (the usual Malé shop sending by boat, ferry or cargo; the default, so existing
shops keep today's behaviour), **My island and Greater Malé** (an island shop sending to Malé,
Hulhumalé and Villimalé) or **My island only**; the last two can tick their whole atoll and extra
islands (`shop_delivery_islands`). Existing shops were given a home island from their typed island
and atoll where it matched the list; those that had unticked "I ship to other islands" became
"My island and Greater Malé". `App\Services\ShopCoverage::canDeliverTo()` is the one rule used by
listings, labels and checkout; pickup from the shop is open to everyone whatever the coverage. The
shop page shows "Based in <island>, <atoll> · Delivers to: …". The fields are not mass-assignable
(saved with `ShopCoverage::save()`).

**Pausing or hiding new shop applications.** **Admin → Sellers → New shop applications**
(`admin.seller-applications`, admins only; `App\Support\SellerApplications`) has three choices.
**Open** is the default. **Paused** keeps "Sell on iruali" on the site, but `/seller/apply` shows the
admins' message (English and Dhivehi, up to 300 characters) instead of the form, with "Tell me when
you reopen". **Hidden** also takes the "Sell on iruali" links off the top bar, the account and mobile
menus, the footer, the home page's tile and call to action, and the help page's selling section; the
address still shows the message. The page answers guests too, so nobody signs up only to find
applications closed, and while paused or hidden the form is refused on the server. An optional
reopening day opens applications at the start of that day (Maldives time); the hourly
`sellers:applications-reopen` then stores "open" again. The waiting list (`seller_waitlist`: one row
per email, in the visitor's language) gets one email each (`SellerApplicationsReopened`) when
applications open, by that run or when an admin opens them. Shops already on iruali and applications
already sent are never affected. Saving is in the audit log (`seller_applications.saved`), and
`iruali:anonymise` replaces the waiting list's emails.

**Staff.** An owner lets employees into the Seller Centre with their own sign-in under
**Seller Centre → Staff** (`/seller/staff`, `seller.staff*`, the owner only): invite by email and role
(a signed link that works for 7 days; with an account they sign in and accept, without one they make
one and the link confirms the email), change a role, remove someone (they lose access on their next
request), withdraw or send an invitation again, and optionally require two-step sign-in for staff.
Roles and the seller route names each may open are in `config/shop_staff.php`: **manager** (everything
except bank details, earnings and payouts, tax registration, business verification, holiday mode and
staff) and **packer** (orders: view, status, delivery details, packing slips, ready for pickup and the
pickup code; the stock page; product questions). Pages on no list stay owner-only; the `owner_only`
list can never be opened by staff. The role:seller middleware (`App\Http\Middleware\ShopAccess`)
enforces it on every seller route, a suspended or rejected shop is closed to its staff too, and the
nav hides what a role cannot open (earnings, commission and bank details are hidden in pages too).
In code, "the shop" is `App\Support\CurrentShop::get()` and "who did it" stays `auth()->user()`:
messages, review replies and answers record the staff member but show the shop's name, and every
change staff make is in the audit log (`shop.staff_action`, the staff member as actor, the shop as
subject), as are invitations and staff changes (`shop_staff.*`). One shop per staff account: shop
owners, another shop's staff and iruali's team cannot be invited, and staff accounts cannot apply to
sell; otherwise they are customers with their own cart and orders. Invitations are limited to 10 an
hour and 20 staff plus open invitations per shop.

**Products and stock.** `/seller/products` with create/edit/duplicate/delete, photos with
automatic WebP variants, variants (options, SKU, price, stock), bulk edit
(`seller.products.bulk*`), CSV import with preview and a sample file
(`seller.products.import*`), CSV export, and `/seller/stock` for quick stock edits. New products
wait for admin approval before they are public. A daily low-stock email goes to each shop
(`seller:low-stock-digest`).

**Product approval and edits.** Approval is `products.approved_at`. Every change a shop makes to an
approved product waits for an admin too (`App\Services\ProductReviewService`): the live listing
keeps the approved version (it is never hidden for it) and the edit is a `product_change_requests`
row holding the whole reviewed state asked for (`payload`: product columns, variant rows, multi-buy
fieldset, main image, plus `staged`, the images uploaded with it). Not reviewed, and applied at
once: the product's stock and low-stock alert, existing variants' stock and alert level, and the
shop hiding or showing its own approved product. Everything else on the form is reviewed (names
and descriptions in both languages, price, compare-at and sale price, category, brand, SKU, weight,
delivery charge, main image, variants added, removed, renamed, re-priced or switched on/off,
variant images, video link, pre-order settings, quote minimum, multi-buy offer). A product that was
never approved is not public, so edits to it apply directly. One pending request per product: a
new edit replaces it, and the form, bulk edit (price %) and CSV import (names, descriptions, brand,
prices, variant prices and active, new variants) all build on the waiting one; files the
replacement drops, and every file of a rejected or withdrawn edit, are deleted. Nothing but stock
changed: no request (and a waiting edit the form now matches the live product again is withdrawn).
The Seller Centre shows "Edit waiting for review" in the product list and, on the form, the
submitted date with Withdraw (`seller.products.changes.withdraw`), with the form prefilled from the
waiting edit; the last rejection's reason shows there too. Admin → Products → "Changes waiting"
(`admin.product-changes*`, admin and catalogue staff; an inbox row "Product changes waiting for
review") lists them oldest first, each with a before → after page (fields, image thumbnails,
variants table) and Approve or Reject with a reason. Approving applies the payload through
`ProductEditService`, the code the product form saves with (so `VariantService::sync` and
`MultiBuyService::saveFromForm`), keeping the live stock; if the product changed since in a way that
clashes (a variant in the edit deleted, a SKU taken, the category or group gone) nothing is applied
and the page says why. The shop is emailed either way (`ProductChangeReviewed`, with the reason),
and both are in the audit log (`product.change_approved` / `product.change_rejected`). A deleted
product's waiting edit is withdrawn. Admins' own product edits are unaffected.

**Trusted shops.** Off for every shop. An admin (not catalogue, support or finance staff) turns
"Trusted shop: publish without review" on or off on the shop's page (Admin → Sellers → Performance,
`admin.sellers.auto-approve`; `users.auto_approve_listings`, `_at`, `_by`, not mass-assignable;
audited as `seller.auto_approve_on/off`). It counts only while the shop is an approved, active
seller (`User::autoApprovesListings()`); it belongs to the owner's account, so shop staff act under
it. A trusted shop's new products (product form, CSV import, API) go live at once with `approved_at`
set and an audit entry `product.auto_approved` by the shop (still not while the shop's checklist is
incomplete), saving a product it never had approved publishes it, and its edits apply directly with
no request.

**Pre-orders and "coming soon" are for the shops an admin allows, each on its own.** Both are off
for every shop until an admin turns them on (Admin → Sellers → the shop's page → "Pre-orders and
coming soon": **Allow pre-orders** `admin.sellers.preorders`, **Allow "coming soon"**
`admin.sellers.coming-soon`; admin role only; `users.preorders_allowed` / `coming_soon_allowed`, not
mass-assignable, audited as `seller.preorders_on/off` and `seller.coming_soon_on/off`; badges in the
sellers list). **A shop sees only what it is allowed** (`User::allowsPreorders()`,
`allowsComingSoon()`): the product form has only those fieldsets (no hint of the others), the request
ignores any other, and the Seller Centre's **Pre-orders** tab and page are there only for a shop allowed
pre-orders, or with pre-orders still to finish (`PreorderService::shopSees()`). Turning pre-orders off
switches them off on the shop's products (their settings are kept for if it is allowed again);
turning "coming soon" off makes those products ordinary listings (whoever asked is emailed if they
can be bought). Orders already placed carry on, and "Stock arrived" still works for them. A shop that
is not allowed takes no new pre-orders at all (`PreorderService::accepting()`,
`Product::scopePreorderable()`), and an edit approved after a shop lost one keeps it off
(`ProductEditService::apply()`). For iruali's own imports use a shop account of its own (e.g.
"iruali Direct") and allow it. The switches came in on
2026-10-21; that day's migrations switched off what shops not allowed already had.

**Pre-orders.** Stock reaches the islands by ship, so a shop can take orders for what is coming:
the product form's "Pre-order" fieldset switches it on with the expected ship date (after today,
within a year), the most units it takes (counted across the product's options) and an optional note
(`products.preorder_*`). While the product, or the chosen option, has no stock and units are left,
the product page says "Pre-order: ships around 3 Nov" with the note, the button says "Pre-order"
and the delivery box says "Arrives around …" (the date plus the shop's dispatch and transit days);
the cart and checkout mark the line, and the customer pays in full by card as usual. A shop on
holiday takes none. No stock is taken for a pre-order line: `OrderService` has
`PreorderService::reserveForCheckout()` lock the cart's stock rows first in its transaction and
hold the units against the limit, so two checkouts for the last unit cannot both get it; the order
line records `is_preorder` and its date, and the shop's part shows "Awaiting stock" (to the shop,
the customer and admins) and cannot be sent until its stock is in. **Seller Centre → Pre-orders**
(`seller.preorders`) lists the open pre-orders per product: "Stock arrived" takes the units received,
gives them (with any already on the shelf) to the paid pre-orders oldest first, partly when not
enough came, emails those customers and puts the rest on sale (back-in-stock alerts go out for it);
moving the expected date (there or on the product form) emails the waiting customers with a link to
cancel. Customers cancel until anything in the order is sent (`orders.preorder.cancel`, a signed
link for guests): it is the normal cancellation, so a card payment is flagged for refund. Every
morning `preorders:flag-late` flags pre-orders more than 7 days past their date: "Late pre-orders"
in Admin → Inbox, listed at `/admin/preorders`. Feeds say `preorder` with `availability_date`
(Google) and `in stock` (Meta only takes in/out of stock), the JSON-LD `https://schema.org/PreOrder`.
A part's days to ship (late shipments, "not delivered" disputes) count from when its stock arrived.
An optional **last day to pre-order** (`preorder_closes_on`, on or before the ship date) stops new
pre-orders after it, for example when the shop places its own order with its supplier. Cards and
listings say "Pre-order · ships around 10 Nov" (`<x-stock :product>`) with a "Pre-order" button to
the product page; the shop filter has **Pre-order** (`?preorder=1`, `Product::scopePreorderable()`:
open now with units left) and the home page a "Pre-order now" row.

**Pre-orders with a booking fee** (`App\Services\BookingFeeService`), for goods a shop buys only once
a customer orders them (e.g. from a shop abroad; the customer only sees the shop). The "Pre-order"
fieldset also takes **when it ships**: on a date, or **about N days after each order**
(`products.preorder_lead_days`; each order line gets its own date, and the listing can stay open all
year) — and **what the customer pays at checkout**: the full price, or a **booking fee** as a
percentage of the price (up to 90%) or an amount per item (`preorder_deposit_type`/`_value`; changes
on a live product of a reviewed shop wait for review). The product page says "Booking fee now: MVR
300.00 per item (30% of the price)" and the cancellation rule. Booking-fee items are **ordered on
their own** (nothing else in the cart, one shop; the cart says so and checkout sends the customer back
there), paid by card or bank transfer (no cash on delivery or pay-later; the wallet pays towards the
fee), and the customer ticks the booking-fee terms. The order keeps the fee (`orders.deposit_amount`)
and asks only for it: BML transactions carry a `purpose` (deposit or balance). Paying the fee makes
the order **"Booking fee paid"** (`payment_status` `deposit_paid`): the customer and the shop are told
(the shop: order the item now); points, the GST invoice and the shop's earnings wait for the balance.
When **Stock arrived** covers everything in the order, the customer is sent the **balance** with a
payment link by SMS and email (`BookingBalanceDue`, due in the product's **days to pay the rest**,
`preorder_balance_days`, 7 by default; the link works until a week after),
and the order page, the pay page and the bank-transfer box ask for it; the order **cannot be sent or
made ready for pickup until the balance is paid**. Every morning `preorders:balance-reminders` sends
one reminder two days before the due date and, once it has passed, tells the team
(`booking_balance_overdue` alert) and lists it in Admin → Inbox ("Pre-order balances overdue") and on
`/admin/preorders` ("Balances due"); the admin order page can give more time (`admin.orders.balance-due`,
a new link goes out). **Cancelling:** the customer cancelling (the cancel page explains it) keeps the
fee for the shop — it is credited to the shop's earnings less commission (`SellerAdjustment`) and only
the rest is refunded — unless the fee became refundable because the shop moved the date later or the
order is late (`orders.deposit_refundable_at`): more than the product's **late after** days
(`preorder_late_days`, 7 by default; also when any pre-order shows as late in Admin → Inbox). Each order
keeps the days it was placed with (`orders.balance_days`, `order_items.preorder_late_days`; a booking-fee
order takes the fewest late days of its lines), and the checkout says them; fewer days to pay or more
days before a refund since the checkout page refuse the order.
**GST on the booking fee:** prices include GST, so the fee does too. When it is paid the shop's part gets
its own **tax invoice (or receipt) for the booking fee** (`seller_orders.deposit_invoice_*`, the next
number in the shop's series, the GST at the rate frozen when the order was placed); the customer, the
shop and admins open it from the order page (`…/invoices/{part}?booking=1`). When the balance is paid the
usual invoice shows the whole sale **less the booking fee invoiced**, and its GST is only the rest. The
monthly GST report and ledger count the fee in the month it was paid and the rest when the balance is
paid; a fee refunded on cancelling is a refund then (`deposit_reversed_at`); a fee kept stays a sale,
and iruali's commission on it (with GST when iruali was registered) counts when it is kept
(`deposit_kept_at`) and is a line of its own on iruali's commission invoice with the shop's next payout. An admin cancelling refunds everything
unless they tick "Keep the booking fee" (e.g. the balance was never paid). A transfer slip for the fee
is kept (`deposit_slip_path`) when the balance's slip comes, and "Confirm payment" says which part it
records. The unpaid-order sweeps never cancel an order whose booking fee is paid.

**Pre-orders paid once the shop confirms** (`App\Services\PreorderConfirmService`), for goods a shop
can't promise before it asks its supplier. "What the customer pays at checkout" has **Nothing yet: the
full price once I confirm I can get it** (`products.preorder_confirm_first`, never with a booking fee),
with its **days to pay once confirmed** (the same field as the booking fee's days to pay the rest,
`preorder_balance_days`; 3 by default). The product page and checkout say "Nothing to pay now".
These items are **ordered on their own** (one shop; the cart and checkout say so) by card or bank
transfer, without the wallet; the order (`orders.confirm_first`, `pay_days`) is placed **unpaid and
not payable**: no card page, no pay button, no transfer slip, no payment link, and the unpaid-order
sweeps leave it alone. The shop is told to check with its supplier; **Seller Centre → Pre-orders**
lists it under "To confirm" (also on the shop's order page; owners and managers), and Admin → Inbox
counts it ("Pre-orders to confirm", listed on `/admin/preorders`, where an admin or support can act
for the shop). **"I can get it"** (`seller.preorders.confirm`, `admin.orders.preorder-confirm`) sets
`availability_confirmed_at` and `pay_due_on` and texts and emails the customer a payment link for the
full amount (`PreorderAvailable`; the link works until the day after it is due); from then on it is an
unpaid order like any other (order page, pay page, transfer box, "Send payment link"). Every morning
`preorders:balance-reminders` sends one reminder the day before it is due (not on the day it was
confirmed, nor once a slip is in); still unpaid after its due date (and with no payment link sent in
the last 48 hours), `orders:cancel-unpaid-card` / `orders:cancel-unpaid-transfer` cancel it. Paid, the
shop is told to order the item (`ShopOrderPaid`, part `confirmed`), and it is a paid pre-order like
any other. **"I can't get it"** (`seller.preorders.decline`, `admin.orders.preorder-decline`) cancels it
(`availability_declined_at`; points and voucher go back, nothing to refund) and the cancellation
message says the shop can't get it. Placing the order checks the checkout page showed it the same way
(and not fewer days to pay).

**Import from a link** (`App\Services\ProductImportService`, shops allowed pre-orders): Seller Centre →
Products → "Import from a link" (`seller.products.from-link`, throttled) takes the address of a product on
an online shop abroad and opens the product form filled from it, with no outside service: the page's
schema.org Product / ProductGroup data (JSON-LD: name, description, brand, price and currency, weight,
photos, options), then its share tags (og:title, og:image, product:price...), then its title; a Shopify
shop's `/products/{handle}.js` gives every option and photo. Pages are fetched by `App\Support\SafeHttp`:
http(s) on ports 80/443 only, every address the host resolves to must be public (no localhost, private,
link-local or hosting-network ranges; the connection is pinned to the checked address; redirects are
followed by hand and checked again), with size and time limits. Up to six photos are downloaded, checked to
be real pictures and stored like uploads; the shop unticks any it doesn't want (the first kept is the main
image unless it uploads one; the rest join the product's photos; the others are deleted). The MVR price is
suggested from **Admin → Settings → Pre-order imports**: the page's price × the exchange rate (one per line,
"USD 15.42"), plus shipping per kg when the page gives the weight, plus the margin; with no rate for the
currency the shop enters it. The form opens with stock 0 and pre-orders ticked, and the page's address in
the Pre-order fieldset's **Where you buy it (link)** (`products.source_url`, reviewed like other fields),
which admins see on Admin → Products ("Bought from: …"); shoppers never do. Big shops that block robots
(Amazon, Shein, Temu) give "We couldn't read that page", and the shop fills the product in by hand.

**Coming soon.** A shop can list a product before it goes on sale: the product form's "Coming soon"
fieldset (`products.coming_soon`, optional launch day `available_on`). It shows with a "Coming soon"
label ("Coming 1 Nov" on cards, "on sale from …" on its page) but can't be added to a cart or
ordered (`ProductComingSoonException`, `OrderService`), takes no pre-orders, and gives no back-in-stock
emails; shoppers leave their email for "Email me when it goes on sale" (`stock_alerts.for_launch`).
It goes on sale when the shop unticks it, or by itself on its launch day (`Product::isComingSoon()`
turns false that day; `products:launch` runs at 00:05 Maldives time and clears the flag). Going on
sale emails those who asked (`NowOnSale`), or, if it has no stock then, when its stock arrives.
Switching it applies at once, like stock, without a product review. The shop filter has **Coming
soon** (`?coming_soon=1`), the home page a "Coming soon" row; other home rows leave these products
out. Feeds say `out_of_stock`, the JSON-LD `OutOfStock` with `availabilityStarts` set to the launch day.

**Orders and fulfilment.** `/seller/orders` lists the shop's parts of orders; `/seller/orders/{order}`
moves a part through pending → processing → shipped → out for delivery → delivered
(`seller.orders.status`), records courier, tracking number/URL, vessel or flight and expected
date (`seller.orders.tracking`), and messages the customer (`seller.orders.messages`). Returns the
customer opened show at `/seller/returns`, questions at `/seller/questions`, reviews with a reply
at `/seller/reviews` (`seller.reviews.reply`).

**Money and performance.** `/seller/earnings` shows each part's subtotal, commission and
earnings, what is payable, pending and paid; `/seller/analytics` the shop's sales;
`/seller/performance` late-shipping rate and deadlines. Earnings become payable once a part is
delivered and the order paid; see `MARKETPLACE.md`.

## Admin

Staff roles are **admin**, **support**, **finance** and **catalogue** (`config/staff.php`); two-step
sign-in is required for all of them. Staff accounts never sell, except that an admin account may also be
iruali's own shop (Seller → "iruali's team never sells; iruali sells through a shop"). Catalogue staff look after brand pages (Admin → Brands: names,
logo, banner, descriptions, merging, authorised sellers), approve new products and shops' changes
to live products, and run campaigns,
and open nothing about orders, money, people or settings. A shop's owner or someone on a shop's staff
cannot be given a staff role (they would be approving their own shop's products). `/admin/dashboard` has the key numbers and the system-status panel;
`/admin/inbox` lists everything waiting for a person (shops to approve, products to review,
product changes to review, refunds due, open returns, failed payments, low stock on best sellers, unresolved errors, new
brands to check).

**Marketplace management.** `/admin/sellers` (approve, reject, suspend, commission rate, bank
verification, performance page with the trusted-shop switch), `/admin/products` (approve; "Changes
waiting" for edits to live products), `/admin/brands` (check new
brands, fix names and web addresses, add a logo and an English/Dhivehi description, merge
duplicates, delete unused ones; old names and addresses keep working), `/admin/users` (roles),
`/admin/reviews` and `/admin/questions` (moderation), `/admin/vouchers`, `/admin/newsletter`,
`/admin/settings` (site settings, delivery fees, commission default) and `/admin/legal`.
`/admin/islands` (`admin.islands`, admins) lists every island with its atoll in both languages, the
shops based there and the shops delivering there, and switches islands on and off
(`admin.islands.toggle`, audited as `island.activated` / `island.deactivated`); switched-off islands
leave every picker, while addresses and orders on them are untouched. Admin → Sellers → a shop's
page edits its home island and delivery area (`admin.sellers.coverage`, audited as
`seller.coverage`); the sellers list shows each shop's home island and type.

**Newsletter.** `/admin/newsletter` lists the footer subscribers (who confirm by email first) and
the newsletters. "Write a newsletter" (`admin.newsletter-issues.*`, admins only): subject and text
in English and Dhivehi, plus sections picked when it is sent (new arrivals from the last N days,
current deals, a running campaign, featured brands), a preview in both languages and "Send me a
test". "Send" queues it in spaced batches (`config/newsletter.php`) to confirmed subscribers and
customers who accept marketing emails, one email per address in its own language with the signed
unsubscribe link; an address never gets an issue twice. Each issue keeps its status and counts,
and the send is in the audit log.

**Orders, payments, returns, disputes.** `/admin/orders` and `/admin/orders/{order}`: change the
order or a part's status, add tracking, message the shop or customer, re-check a payment with
BML (`admin.orders.bml-sync`), record a manual payment, flag and record refunds.
`/admin/returns` approve/reject/mark refunded; `/admin/disputes` ask for information and resolve
(full refund, partial, not upheld); `/admin/messages` all conversations.

**Payouts.** `/admin/payouts` per-seller balances and single payouts; `/admin/payout-batches`
groups payable earnings into a batch, exports the bank file (`admin.payout-batches.file`), marks
it paid with the bank reference or cancels it.

**Sample data.** When the real shops are ready, **Admin → Settings → Sample data**
(`/admin/sample-data`, `admin.sample-data`, full admins only) takes the six demo shops and the
sample products off the site: the products go to the bin switched off, the shops are suspended
(accounts kept), customers' carts, saved items, wishlists and stock alerts lose them, their
campaign entries come out, and the cached sitemap and product feeds are rebuilt. Type REMOVE to
confirm. **Restore sample data** puts back exactly what was removed (products, each shop's
previous status, anything switched off with the shops, campaign entries). Orders that contain
sample products keep working (order pages, receipts, payouts). Only the rows listed in
`App\Support\DemoData`, which the demo seeders also read, are touched (the generic demo products
under the admin@example.com admin, or under that account after its email was changed: its id is
remembered in the `sample_data_admin_id` setting; never another admin's, nor those of someone who
later signs up with the freed address); where the seeders never ran
(production) the page says there is nothing to remove. On the server: `php artisan demo:remove`
and `php artisan demo:restore` (`--force` skips the question). Both show in the audit log. Bring
removed sample data back with Restore, not by re-running the demo seeder: it cannot re-create
products that are in the bin.

**Observability.** `/admin/analytics` (revenue, orders by status, top products and shops,
signups; the smoke-test customer is excluded), `/admin/errors` (every reported exception
counted per place in the code, with "mark resolved"), `/admin/audit` (who changed what, with
IP and diff), `/admin/sms` (sent messages and a test send).

## Tax (GST-ready)

iruali is **GST-ready**: everything is configurable and, until someone is marked GST-registered,
nothing a shopper sees changes. Prices on iruali **include GST**, so the GST in an amount is
`amount × rate ÷ (100 + rate)`, rounded to the laari (`App\Services\GstService::gstIncluded`).
This is a tool for keeping records, not tax advice: **confirm the rate and what your invoices
must show with your accountant or MIRA before you switch registration on.**

- **iruali's settings** at **Admin → Tax** (`/admin/tax`, `admin.tax`; admins and finance staff):
  GST-registered yes/no (default no), iruali's TIN (MIRA format, 7 digits + `GST` + 3 digits, e.g.
  `1012345GST501`), the GST rate (default 8.00 %) and the invoice number prefix (default `INV`).
  iruali's name and address on invoices come from **Admin → Settings → Business details**. Every
  change is in the audit log (`tax.settings_saved`).
- **Shops** say whether they are GST-registered under **Seller Centre → Settings → Tax**
  (`seller.settings.tax`): TIN (spaces and case forgiven, stored normalised), registered name and
  business address (`shop_tax_profiles`, audited as `shop.tax_details_saved`). Admins see it on
  **Admin → Sellers** and in the list on Admin → Tax.
- **Frozen when the order is placed.** Each shop part (`seller_orders`) keeps whether the shop was
  registered, its TIN, name and address, the rate, the goods total after any shop discount
  (`SellerOrder::taxableGoodsTotal()`), the GST in it and the GST in iruali's commission; each
  order keeps iruali's registration, TIN, rate and the GST in the delivery fee (delivery is
  iruali's, not the shops'). Later changes never alter an order already placed
  (`FulfilmentService::refreshPart` → `GstService::capturePart`).
- **Invoices.** When an order is paid each shop part gets a number from that shop's own series,
  e.g. `INV-12-000034` (`IR` for iruali's own sales), taken with the series row locked so payments
  landing together never share or skip a number; unpaid orders get none. A part is a **tax
  invoice** when the shop was GST-registered at order time, otherwise a **receipt**: date, shop
  name/address/TIN, buyer (and business details when given), lines with GST-inclusive prices, GST
  breakdown and totals; English, with the Dhivehi under each label on Dhivehi pages. Customers
  open them from the order page (`orders.invoice`; guests through the signed link,
  `guest.orders.invoice`), shops from their order page (`seller.orders.invoice`), staff from the
  admin order page (`admin.orders.invoice`). iruali's own receipt (`orders.receipt`) shows the GST
  in the delivery fee when iruali was registered.
- **Commission invoices.** Each paid payout gets iruali's invoice to the shop for the commission
  in it (`INV-C-000001`, numbered when the payout or its batch is paid), with GST for orders placed
  while iruali was registered. The commission is treated as GST-inclusive, so shops' earnings do
  not change. From Seller Centre → Earnings (`seller.payouts.invoice`) and Admin → Payouts
  (`admin.payouts.invoice`).
- **Monthly GST report** at `/admin/tax/report?month=2026-10` (`admin.tax.report`, CSV with
  `&export=csv`): paid orders only, a sale in the month it was paid, refunds in the month a return
  or dispute was approved or a paid order was cancelled. Per GST-registered shop: sales, GST,
  refunds and the GST in them; for iruali: commission and delivery fees with their GST, less what
  was given back. Gift-card orders are left out (GST applies when a card is spent).
- **Business buyers.** Customers can save business details (company name, optional TIN, address)
  under **My account → Edit profile** (`account.business.update`). At checkout an optional
  "Buying for a business?" block, prefilled from the profile and open to guests, stores them on
  the order and every shop's invoice prints them.

## Bulk quotes (businesses)

Resorts, offices and cafés buying in quantity ask a shop for a price instead of paying the listed
one (`App\Services\QuoteService`; routes in `routes/web/quotes.php`, tables `quote_requests` and
`quote_messages`).

- **Asking.** Signed-in customers see "Request a bulk quote" in the product page's buy box (guests
  are sent to sign in first) and, on a shop's page, a box to ask for a price on any of its products
  (they pick the product first). The form (`quotes.create`, `quotes.store`) takes the quantity (at
  least 10, or the shop's own minimum for the product: "Bulk quotes" on the product form,
  `products.quote_min_quantity`), the delivery island (island picker, prefilled from the default
  address), a needed-by date and notes, plus the business details for the invoice, prefilled from
  **My account → Business details** and saved back there unless unticked. One open request (new,
  quoted or accepted) per customer and product; at most 10 requests an hour per customer (rate
  limiter `quote-requests`). Not on a shop's own products or while it is on holiday. Customers
  follow their requests under **Quote requests** in the account menu (`quotes.index`, `quotes.show`).
- **The shop's reply.** **Seller Centre → Quote requests** (`seller.quotes`, tabs new, quoted,
  accepted, ordered, declined, expired, all; a shop sees only its own) shows each request with the
  business details. The shop sends a unit price in MVR (it may be under the listed price), the
  quantity it can supply, the last day the price holds (7 days unless it picks another, at most 60)
  and a message (`seller.quotes.quote`; it can change the quote until the customer accepts), or
  declines with a reason (`seller.quotes.decline`). Price × quantity is capped at MVR 9,999,999.99.
  Both sides (and staff, as iruali support) write short messages in the request's thread, with an
  unread count; the other side gets at most one email per request every 10 minutes.
- **Emails.** New request (to the shop), quote sent or changed and request declined or closed (to
  the customer, by email and/or SMS as they chose for order updates), quote accepted and quote
  declined or request withdrawn (to the shop). Shops can turn all quote emails off under Settings →
  Notifications ("Bulk quote requests").
- **Accepting and buying.** The customer accepts the quote before its last day ends (`quotes.accept`;
  stock is checked then): it goes into the cart as a quoted line, "Quote #123", whose quantity is
  locked and whose price is the quoted one even if the shop's price changes (`cart_items.quote_request_id`;
  `CartItem::unit_price`). It can only be removed, and goes back in from the quote page while the
  quote holds (`quotes.cart`). The customer can decline a quote or withdraw a request
  (`quotes.decline`). Checkout is the normal one (BML card); stock is checked again, counting the
  quote with any other units of the same product, and a quote never holds stock. The order item
  keeps the quote (`order_items.quote_request_id`) and the quote is marked ordered; commission and
  earnings are on the quoted price. When the buyer leaves checkout's business box off, the quote's
  business details go on the order, so every shop's invoice carries them. An unpaid order that is
  cancelled gives its quotes back (accepted again while they hold).
- **Discounts on quoted lines.** None of the shop's: no multi-buy saving and no shop code (their
  units do not count towards a multi-buy tier either). **No iruali voucher either:** the voucher and
  its minimum order work on the rest of the cart only, and a cart of quoted lines alone refuses one
  ("iruali vouchers do not apply to items bought on a quote"), because a quote is already the shop's
  negotiated price and a percent voucher on a large business order would cost iruali a share of it.
  **Loyalty points and wallet credit do pay for quoted lines** (they are the customer's own balance)
  and the order earns points as usual. All amounts are worked in laari, so lines less shop discounts,
  voucher and points, plus delivery, add up to the order total exactly.
- **Expiry.** A quote holds to the end of its last day (Maldives time). After that it can't be
  accepted; `php artisan quotes:expire` (hourly) marks quoted and accepted quotes past their day
  expired, and a quoted line whose quote expired or was closed comes out of the cart with a notice
  the next time the cart or checkout is opened (placing the order refuses it the same way).
- **Admin.** **Admin → Quotes** (`/admin/quotes`, `admin.quotes`; admins and support staff) lists
  every request with status filters, including "waiting": new requests no shop has answered for more
  than 2 days, which is also an **Admin → Inbox** row. Staff can write in a request as iruali support
  (`admin.quotes.messages`) and close a request with a reason both sides are emailed
  (`admin.quotes.close`; a quote in the cart comes out). Only these staff actions are in the audit
  log (`quote.message`, `quote.closed`); what customers and shops do is not.

## Operations

**Deploy and release.** `main` auto-deploys to test.iruali.mv (`TEST_AUTO_DEPLOY.md`);
production follows `main` for now: `bash ~/deploy-live.sh` on the server deploys the newest
`main`, with a health check, smoke test and automatic rollback (`SERVERS.md`, `PRODUCTION_DEPLOY.md`).
Release tags still work (`scripts/deploy-production.sh <tag>`, `scripts/rollback-production.sh`).
`/api/health` answers `status`, `timestamp` and `version` to everyone, and adds the running commit
and tag only with the `X-Health-Token` header (`HEALTH_DETAIL_TOKEN`); `/api/health/ready` answers 503
when the database, cache or storage is not working; `/up` is Laravel's liveness check.

**Checks.** `php artisan iruali:ready` (scheduler heartbeat, cache, sessions, queue, mail, BML,
backups, uploads, settings) and `php artisan iruali:smoke [--place-order]` (key pages, health,
and a real order placed, taken to the pay page, cancelled and restocked as the flagged smoke
customer). Both run at the end of a deploy. `tests/load/` has a k6 load test.

**Scheduled work** (`routes/console.php`, needs the cron): unpaid card orders cancelled hourly,
nightly database backups to local and optional S3/B2 with a monthly restore drill
(`backup:restore-drill`), queued mail sent every minute by a short-lived worker, expired tokens
and failed jobs pruned, guest carts cleaned, the daily errors digest (`errors:digest`) and the
seller low-stock digest, the brand followers' digest and the wishlist price-drop alerts, the
late pre-orders check (`preorders:flag-late`) and the booking-fee balance reminders
(`preorders:balance-reminders`), and hourly the reopening of paused shop applications on their day
(`sellers:applications-reopen`).

**Staging data.** `php artisan iruali:anonymise --force` rewrites personal data on a copy of
the production database before it is used on the test site.

**API.** A Sanctum-token REST API under `/api/v1` (login, register, products, categories,
search, cart, wishlist, orders, checkout points, profile) for a future app; `routes/api.php`
and `App\Http\Controllers\Api` are the reference, the README has the authentication examples.
