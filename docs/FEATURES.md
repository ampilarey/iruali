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

**Brands.** `/brands` (`brands.index`) lists every brand with something on sale, A to Z with a
filter box, plus the most popular brands once the list is long; the menu, the footer and a "Shop by
brand" row on the home page link to it. `/brands/{slug}` (`brands.show`) shows one brand: its logo
and description (English and Dhivehi), the shops that sell it, its departments as quick filters,
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
link, "Shop by brand" on the home page, search suggestions and the catalogue's brand filter.
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

**Cart, wishlist, saved items.** `/cart` (`cart`, `cart.add`, `cart.addMany` for bundles,
`cart.update`, `cart.remove`, `cart.clear`, `cart.saveForLater` → `/saved/*`), vouchers applied
on the cart (`cart.applyVoucher` / `cart.removeVoucher`), `/wishlist` with add/remove/clear. Guest
carts are kept for 30 days.

**Checkout and payment.** `/checkout` collects the delivery address (saved address or island
picker, which sets the delivery zone and fee), lets points be redeemed (`checkout.redeemPoints`),
and places the order at `POST /orders` (`orders.store`). Guests check out at `/checkout/guest` and
get a signed order link (`/orders/guest/{order}/{token}`). The only payment method is a card
through BML Connect: the customer is sent to BML's page and comes back to
`/payments/bml/return/{order}`; BML's webhook (`POST /api/payments/bml/webhook`) and a
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

**Help and policies.** `/help` and the policy pages (`/terms`, `/privacy-policy`,
`/refund-policy`, `/delivery-policy`, `/payment-security`, `/about`, `/seller-terms`), editable
by admins at `/admin/legal`. Newsletter sign-up at `POST /newsletter` with an unsubscribe link.

## Seller

**Becoming a shop.** `/seller/apply` (`seller.apply`) takes the application; an admin approves,
rejects or suspends it. Onboarding steps, `/seller/profile` (logo, banner, description, delivery
notes, "ships to islands"), `/seller/settings/bank` (payout account, verified by an admin) and
`/seller/settings/notifications`. Seller help guides at `/seller/help/{guide}`.

**Products and stock.** `/seller/products` with create/edit/duplicate/delete, photos with
automatic WebP variants, variants (options, SKU, price, stock), bulk edit
(`seller.products.bulk*`), CSV import with preview and a sample file
(`seller.products.import*`), CSV export, and `/seller/stock` for quick stock edits. New products
wait for admin approval before they are public. A daily low-stock email goes to each shop
(`seller:low-stock-digest`).

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

Staff roles are **admin**, **support** and **finance** (`config/staff.php`); two-step sign-in is
required for all of them. `/admin/dashboard` has the key numbers and the system-status panel;
`/admin/inbox` lists everything waiting for a person (shops to approve, products to review,
refunds due, open returns, failed payments, low stock on best sellers, unresolved errors, new
brands to check).

**Marketplace management.** `/admin/sellers` (approve, reject, suspend, commission rate, bank
verification, performance page), `/admin/products` (approve), `/admin/brands` (check new
brands, fix names and web addresses, add a logo and an English/Dhivehi description, merge
duplicates, delete unused ones; old names and addresses keep working), `/admin/users` (roles),
`/admin/reviews` and `/admin/questions` (moderation), `/admin/vouchers`, `/admin/newsletter`,
`/admin/settings` (site settings, delivery fees, commission default) and `/admin/legal`.

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
`App\Support\DemoData`, which the demo seeders also read, are touched; where the seeders never ran
(production) the page says there is nothing to remove. On the server: `php artisan demo:remove`
and `php artisan demo:restore` (`--force` skips the question). Both show in the audit log. Bring
removed sample data back with Restore, not by re-running the demo seeder: it cannot re-create
products that are in the bin.

**Observability.** `/admin/analytics` (revenue, orders by status, top products and shops,
signups; the smoke-test customer is excluded), `/admin/errors` (every reported exception
counted per place in the code, with "mark resolved"), `/admin/audit` (who changed what, with
IP and diff), `/admin/sms` (sent messages and a test send).

## Operations

**Deploy and release.** `main` auto-deploys to test.iruali.mv (`TEST_AUTO_DEPLOY.md`);
production runs tagged releases: `scripts/release.sh` → GitHub Release →
`scripts/deploy-production.sh <tag>` on the server, `scripts/rollback-production.sh` to go back
(`PRODUCTION_DEPLOY.md`). `/api/health` reports the running commit and tag; `/up` is Laravel's
liveness check.

**Checks.** `php artisan iruali:ready` (scheduler heartbeat, cache, sessions, queue, mail, BML,
backups, uploads, settings) and `php artisan iruali:smoke [--place-order]` (key pages, health,
and a real order placed, taken to the pay page, cancelled and restocked as the flagged smoke
customer). Both run at the end of a deploy. `tests/load/` has a k6 load test.

**Scheduled work** (`routes/console.php`, needs the cron): unpaid card orders cancelled hourly,
nightly database backups to local and optional S3/B2 with a monthly restore drill
(`backup:restore-drill`), queued mail sent every minute by a short-lived worker, expired tokens
and failed jobs pruned, guest carts cleaned, the daily errors digest (`errors:digest`) and the
seller low-stock digest.

**Staging data.** `php artisan iruali:anonymise --force` rewrites personal data on a copy of
the production database before it is used on the test site.

**API.** A Sanctum-token REST API under `/api/v1` (login, register, products, categories,
search, cart, wishlist, orders, checkout points, profile) for a future app; `routes/api.php`
and `App\Http\Controllers\Api` are the reference, the README has the authentication examples.
