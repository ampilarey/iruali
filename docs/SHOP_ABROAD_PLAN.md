# Shop abroad, pay in MVR: implementation plan

Status: **planned, not started** (written 2026-11-05). Pick it up by asking for "Shop abroad" and the phase.

## The goal

A customer in the Maldives finds an item on an overseas store (Amazon.in, Flipkart, Nykaa, Shein,
AliExpress...), sends it to iruali, and sees the price in MVR, including shipping and fees. They pay with
a BML card, and iruali buys the item and delivers it. The model for this is the Global Ducan app:

- store tiles open the store;
- "Shop via ..." sends the item to their cart;
- an instant price shows when the page can be read;
- otherwise the cart says "Awaiting price — usually within an hour" and a person sets the price.

## What already exists (build on it, don't duplicate)

| Piece | Where | What it does today |
|---|---|---|
| Item requests | `App\Services\ItemRequestService`, `App\Models\ItemRequest`, `routes/web/item-requests.php`, `Customer\ItemRequestController`, `Admin\ItemRequestController` | The customer sends a link, title, options and quantity. The admin quotes, which creates a private pre-order product. The customer accepts, the item goes into the cart, and they pay. Statuses: new → quoted → accepted → ordered (or declined/cancelled). Limit `MAX_OPEN = 5`. |
| Link reader | `App\Services\ProductImportService::parse()` (with `App\Support\SafeHttp`) | Reads the name, photos, price, currency, weight and variants from schema.org/og tags. It has a Shopify fast path. Used by Seller Centre "Import from a link". |
| MVR price | `ProductImportService::rates()` / `suggestPrice()` | Price × rate (Settings → Pre-order imports, `import_exchange_rates`), plus `import_shipping_per_kg` × weight, then plus `import_margin_percent`. |
| Share to iruali | `public/site.webmanifest` `share_target` → `GET /request-an-item?title&text&url` | Android only, once iruali is installed from the browser. iPhone web apps cannot be share targets. |
| Pre-order money rules | `BookingFeeService`, "pay once the shop confirms" | Deposit or full payment, balance requests, refunds. |
| Admin alerts | Admin → Team alerts ("Item request"), admin inbox row "Item requests waiting for a price" | Telegram ping and inbox count. |

## What a website cannot do

- **Show Amazon inside iruali with our own button underneath.** Stores send `X-Frame-Options`/CSP headers
  that block embedding, and a web page cannot read another site's page.
- **Order on the store automatically.** There is no public buying API (see `LATER.md`, "Amazon's own data").
  Amazon, Temu and others also block server-side reading, so for them the price is usually set by hand.

The full Global Ducan experience (an in-app browser plus a "Shop via iruali" button that reads the page
on the customer's phone) needs a **native app**. That is Phase 6, and it is optional.

## Decisions the owner makes before Phase 2 (business rules)

1. **Stores to list:** names, store addresses, and which country each one ships from. Start with 6–10 stores.
2. **Rates:** exchange rate per currency (INR, USD, CNY, AED...). Who updates the rates, and how often.
3. **Costs:**
   - shipping per kg, by route if it differs (India vs China vs USA);
   - default weight when the page gives none (per category, e.g. clothing 0.5 kg, shoes 1.2 kg);
   - handling fee (fixed MVR, a % of the price, or both);
   - minimum fee;
   - customs duty % (or "included in margin").
4. **Is GST shown separately or included?**
5. **Payment:** full price up front, or a deposit with the balance on arrival. If the real weight is
   higher, is there a top-up?
6. **Banned items:** the airline list (batteries, liquids, powders, perishables, fragile items...) and
   anything customs won't allow.
7. **Returns:** usually "no returns unless damaged or wrong item". This goes on the page and in the terms.
8. **Who fulfils:** which shop buys the items, e.g. iruali Direct. It must be allowed pre-orders.
9. **Price promise:** e.g. "price within 1 hour, 9am–10pm". Outside those hours: "first thing tomorrow".

## Phases (each one ships on its own and is useful alone)

### Phase 1: "Read the link" for admins (small)

Faster quoting, with no change for customers.

- Admin → Item request page gets a **Read the link** button. It calls `ProductImportService` (fetch and
  parse only, no image storage), then fills the quote form:
  - title;
  - photo (first image, downloaded and stored like an upload);
  - the store price and currency;
  - weight;
  - the suggested MVR unit price from `suggestPrice()`, with the working shown
    (price × rate + shipping, + margin).
- If reading fails: "Couldn't read this store's page. Open it and type the price." Include the link.
- New nullable columns on `item_requests` (migration): `source_price` decimal, `source_currency` char(3),
  `source_weight_kg` decimal, `read_at` timestamp, `read_status` string(20) (`ok`, `blocked`, `no_price`).
- Route: `POST /admin/item-requests/{itemRequest}/read`, staff-only like the others,
  `throttle:30,1,admin.item-requests.read`.
- Tests:
  - the parse result fills the form (fake HTTP);
  - a blocked page shows the message;
  - non-staff are refused.

### Phase 2: instant MVR estimate for customers (medium)

This is the "price shows in MVR when I tap the button" part.

- On `GET /request-an-item?url=...`, and when a link is pasted or shared, the page calls
  `POST /request-an-item/estimate` (JSON).
  - Throttle `throttle:20,10,item-requests.estimate`.
  - Signed-in users and guests are both allowed, with a cap on guests.
- The server:
  - uses `SafeHttp` and the reader;
  - caches the result per normalised URL for 6 hours (`Cache::remember`, key from a hash of the URL
    with tracking parameters removed);
  - returns the name, first photo, store price, MVR estimate and breakdown, or `{"status":"manual"}`.
- The form fills itself: name, photo and the estimate card. The card shows:
  - "Estimated **MVR 1,240** incl. shipping (0.6 kg) and fees";
  - a "Final price confirmed within an hour" line;
  - a "See how this is worked out" fold.
- When it can't be read: an "Awaiting price — usually within an hour" note, and the request goes in as now.
- Store the estimate on the request: `estimate_price`, plus the source columns from Phase 1. The admin
  quote form opens pre-filled with it.
- **Pricing becomes a service:** move the price formula out of `ProductImportService` into a new
  `App\Services\AbroadPricing`, so the seller import, the admin quote and the customer estimate share one
  formula. It covers:
  - rate;
  - shipping per kg by route;
  - default weight per category;
  - handling (fixed and %);
  - minimum fee;
  - duty %;
  - margin;
  - rounding (to MVR 5).

  Add the new settings to Admin → Settings → Pre-order imports, renamed to "Shop abroad pricing".
- Never let the estimate become the charge on its own. The admin's quote stays the price the customer
  pays, unless Phase 5's auto-quote is switched on.
- Mobile first: the estimate card fits 390 px, and the image is lazy and fixed-size so the page doesn't jump.
- Tests:
  - estimate maths (each fee, minimum, rounding, missing weight uses the category default, unknown currency
    means manual);
  - cache hit;
  - throttle name (`RateLimitTest`);
  - SSRF-safe (private address refused);
  - estimate saved on submit.

### Phase 3: the "Shop abroad" page (medium)

The store tiles from the Global Ducan screenshot.

- **Route** `GET /shop-abroad` (name `abroad.index`), linked from:
  - the home page;
  - the header menu;
  - "Can't find it?" blocks;
  - the empty search results page.
- **Data:** a new table `abroad_stores`, managed in Admin → Shop abroad → Stores. Columns:
  - `id`;
  - `name`;
  - `url` (https only);
  - `country` (IN/CN/US/AE...);
  - `currency`;
  - `category` (fashion, beauty, electronics, home...);
  - `logo_path` (optional);
  - `is_preferred`;
  - `readable` (bool: last reads worked, for "instant price" badges);
  - `sort`;
  - `is_active`.

  The seeder only adds stores to the dev/demo data (never `db:seed` production). The admin adds the real ones.
- **Page, top to bottom (phone first):**
  1. a "Shop the world, pay in MVR" title;
  2. a big **paste a link** box that goes straight to the request form with the estimate;
  3. category chips (All, Fashion, Health & Beauty, Electronics...);
  4. a 3-column tile grid on phones: name and logo, plus "Preferred" and "Instant price" badges;
  5. "How it works" in 3 steps;
  6. a fold for the banned items;
  7. a fold for delivery times and returns.
- **Tile behaviour:** opens the store in a new tab (`rel="noopener"`). Phone hint: on Android, "Share →
  iruali"; on iPhone, "Copy the link, come back and tap Paste". Show the right hint by user agent, and both
  when the device is unknown.
- **Paste button:** uses `navigator.clipboard.readText()` where allowed. Otherwise focus the box.
- **Logos:** text tiles by default. A store's logo is used only if the owner is happy to (trademarks).
  Never copy store pages or product photos onto iruali beyond the customer's own request.
- Dhivehi for every string. Check the page at 390×844 with no sideways scroll.
- Tests:
  - the page lists active stores by category;
  - inactive stores are hidden;
  - admin store create/update validation (https only);
  - the paste link goes to the request form with `url`.

### Phase 4: requests in the cart, like "Awaiting price" (small)

- The cart page shows the customer's open requests at the top:
  - **new:** "Awaiting price — usually within an hour", with a photo and name;
  - **quoted:** price, Accept, and "valid until".

  Requests aren't charged until they are quoted and accepted. The existing accept → `CartItem` flow does
  the rest.
- When quoted, the customer gets an SMS (already sent by `ItemRequestService::quote` notifications;
  check it) with a link to the cart.
- The cart badge counts quoted requests too.
- Tests:
  - new and quoted requests show in the cart;
  - an expired quote shows "ask again";
  - accept adds it to the cart once.

### Phase 5: faster answers (small, optional)

- **Working-hours promise:** the request page shows "within an hour" in working hours, otherwise
  "first thing tomorrow". Hours come from Settings.
- **Admin inbox reminder:** a request that has waited more than 60 minutes in working hours turns red.
  Telegram re-alerts once.
- **Optional auto-quote for trusted stores** (setting off by default). If a store is `readable`, the read
  worked, a weight is known and the item isn't in a banned category, the estimate becomes the quote at once.
  The customer can pay straight away. A full refund applies if iruali cannot buy it at that price.
  Admins see these as "auto-quoted" to check.

### Phase 6: phone app with a built-in browser (large, separate project)

Only if the website version proves popular.

- A Capacitor (or React Native) shell around iruali.mv, published on the App Store and Play Store.
- Store tiles open an in-app browser. A floating **"Shop via iruali"** button runs a small script on the
  customer's own phone to read the item page's schema.org/og data, which is not blocked because it is the
  customer's browser. It sends the link and the read details to `/request-an-item/estimate`.
- Needs:
  - Apple and Google developer accounts;
  - app review (Apple is strict about in-app browsers of other stores);
  - push notifications;
  - a version update process.

## Risks and how we handle them

- **Stores blocking reading:** expected for Amazon, Temu and others. The manual "awaiting price" path
  always works. Read only the one link a customer sends, never in bulk.
- **Price changes before buying:** quotes have `valid_until`. The admin re-checks before buying.
  Phase 5 auto-quotes carry the refund promise.
- **Weight unknown:** use the category default weight, with the top-up or deposit rule from the owner's
  decisions. Show "estimated" until it is quoted.
- **Banned and dangerous items:** keyword and category check on the name before quoting. The admin
  makes the final call.
- **Store terms and trademarks:** link out only. No copied pages, no logos without consent.
- **Card limits on foreign stores:** iruali buys with its own card or account. The customer pays iruali
  in MVR through BML.

## Rough size

| Phase | Size | Notes |
|---|---|---|
| 1 Read the link (admin) | ~1 day | Reuses the reader |
| 2 Instant estimate | ~2–3 days | New pricing service and settings |
| 3 Shop abroad page and stores admin | ~2 days | New table and admin page |
| 4 Requests in the cart | ~1 day | |
| 5 Faster answers | ~1 day | Auto-quote optional |
| 6 Phone app | weeks | Separate project |

Each phase follows the usual routine: phone-first check at 390×844, Dhivehi strings, Pint, Larastan,
tests, `npm run build` when classes change, PR, then deploy with `bash ~/deploy-live.sh`.
