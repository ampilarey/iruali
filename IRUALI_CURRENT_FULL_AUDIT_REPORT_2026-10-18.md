# Iruali current full audit

Date: 2026-10-18. Mode: read, test, report. No application code was changed.

## A. Executive summary

The checkout at `main` `07f9e0fa1c1a1daaeadaec11d4a768bb4efdb00f` is in good shape for a marketplace that is already live. The automated suite, style check, and static analysis all passed: **918 tests, 10,146 assertions, 0 failures**; Pint clean on 703 PHP files; Larastan level 5 clean on 371 files under `app/`.

No critical, demonstrated compromise was found. Card payment confirmation still depends on a re-fetch of the BML transaction (amount, currency, and local id), not on the return URL or the webhook body. Wallet debits lock the customer row and refuse a negative balance. Stock and pre-order limits are taken under row locks. GST rounding is integer-laari and covered by `GstMathsTest`.

The most important remaining issues are:

1. **IRU-001 (high).** A seller who has any line on an order can open the customer order page and see the full delivery address, phone, and every shop’s items.
2. **IRU-002 (high).** Loyalty points are deducted with `increment()` and no row lock or non-negative check. Two checkouts at once can spend the same balance. This was confirmed in code and was not load-tested.
3. **IRU-003 (medium).** A checkout request that sends `delivery_zone=greater_male` with a typed non–Greater Malé address, and no `island_id` or saved address, is charged the Greater Malé fee.
4. **IRU-004 (medium).** Approving a return records a seller adjustment and a return refund amount, but does not put the order on the admin “refund due” list.

Release readiness: the application is fit to keep running on `main`, with IRU-001 and IRU-002 worth fixing before loyalty points or multi-seller orders carry much money. BML card payments are still off in production until keys exist (`docs/SERVERS.md`). This audit did not browse the rendered UI and did not run concurrent load tests.

## B. Exact audit baseline

| Item | Value |
| --- | --- |
| Branch | `main` |
| Commit | `07f9e0fa1c1a1daaeadaec11d4a768bb4efdb00f` |
| Working tree at start | Clean. `git status --short` empty. No staged, unstaged, or untracked files. |
| Local vs GitHub | Local `main` matched `origin/main` at that commit when the audit started. No fetch or pull was done during the audit. |
| PHP | 8.4.26 |
| Laravel | 12.69.3 |
| Node | 22.14.0 (not used; no asset build) |
| Database for PHPUnit | MariaDB on `127.0.0.1`, database `iruali_test`, user `root`, empty password, as `phpunit.xml` sets. Disposable local test database. |
| Dev database | SQLite path in `.env.example`. Not migrated or seeded for this audit. |
| Mail / SMS / BML | PHPUnit fakes HTTP and notifications. No live payment, SMS, email, or webhook was sent. |
| Scope | This checkout only. `iruali.mv` and `test.iruali.mv` were not contacted. |

Counts from the tree: 393 registered routes (124 `admin.*`, 78 `seller.*`, 36 under `api/`, 154 other web), 371 PHP files in `app/`, 65 model files, 120 migrations, 122 test classes, 9 enums, 29 files in `routes/web/`. Dhivehi URLs are the same routes under `/dv`, applied by `LocalePrefix`, so they do not appear as a second route list.

## C. What changed

Recent history on this commit, newest first:

- `07f9e0f` / `aca1910` — `docs/SERVERS.md`, deploy routine, `AGENTS.md` pointer.
- `dff721c` / `8851fd0` / `b9597af` — review of the hardening: guest BML return token in the path, webhook timestamp window removed, trusted proxies read through `config()` in `AppServiceProvider`.
- `ae9ea48` / `d96941a` — mass-assignment tightening, Sanctum abilities, script-src nonce, health token, secure session cookie default.
- `b285704` / `f98f707` — `scripts/deploy-lib.sh` sends `X-Health-Token` and only compares the commit when the health body includes one.
- Earlier on `main`: Dhiraagu SMS (`sms:test`), production follows `main` with no release tags, seller-application pause/hide/waitlist.

Those paths were re-read against the current files. The old timestamp window is gone on purpose (`BmlPaymentTest` accepts a signature an hour old). `verify_site` matches the token-gated health body. None of those review fixes were re-opened as defects.

## D. Feature and route inventory

Coverage words:

- **Automated** — a current feature test hits the workflow. The suite as a whole passed; this is not a claim that every edge was re-executed by hand.
- **Inspected** — read in this audit (route, controller, service, or view) and not contradicted by the suite.
- **Not run here** — no browser pass and no extra script beyond PHPUnit.

| ID | Area | Main routes | Coverage |
| --- | --- | --- | --- |
| F-01 | Storefront, catalogue, search, compare | `home`, `products.*`, `categories.*`, `search`, `compare` | Automated (`ApiTest`, `ImagesAndSearchTest`, catalogue tests). Browser not run here. |
| F-02 | Brands, aliases, follow, campaigns | `brands.*`, `account.brands`, `admin.brands.*` | Automated (brand and campaign tests). Inspected docs vs `BrandService`. |
| F-03 | Accounts, OTP, password, 2FA, social login | `login`, `register`, `password.*`, `2fa.*`, `social.*`, `account.*` | Automated (`SocialLoginTest`, `StaffAccessTest`, security tests). |
| F-04 | Cart, wishlist, saved, vouchers, shop codes, points | `cart.*`, `wishlist.*`, `saved.*`, `checkout.redeemPoints` | Automated (`ApiCartTest`, `ShopDiscountCodesTest`, `WishlistPriceDropsTest`). IRU-002 is the points race. |
| F-05 | Checkout and guest checkout | `orders.store`, `guest.*` | Automated (`GuestCheckoutTest`, `BmlPaymentTest`). IRU-003 on zone tampering. |
| F-06 | BML pay, return, webhook | `payments.bml.*`, `POST /api/payments/bml/webhook` | Automated. Inspected after the review fixes. |
| F-07 | Customer orders, returns, disputes, messages | `orders.*`, `returns.*` | Automated. IRU-001 on seller access to `orders.show`. |
| F-08 | Delivery zones, islands, slots, surcharges | `delivery`, checkout fields | Automated (`DeliveryRatesTest`, `DeliverySlotsTest`, `AddressesTest`). IRU-003 and IRU-005. Island seeder has 4 rows, not a full atoll list. |
| F-09 | Seller centre, products, bulk CSV, videos, pre-orders | `seller.*` | Automated (`ProductBulkToolsTest`, `ProductVideosTest`, `PreorderShoppingTest`, shop-staff tests). |
| F-10 | Seller staff and invitations | `seller.staff.*`, `shop-invitations.*` | Automated (`ShopStaffAccessTest`, `ShopStaffInvitationsTest`). |
| F-11 | Seller applications pause / waitlist | `seller.apply`, `admin.seller-applications` | Automated (`SellerApplicationsPauseTest`). |
| F-12 | Admin staff roles | `admin.*` under `auth`, `staff`, `staff.2fa` | Automated (`StaffAccessTest`). Support cannot confirm payment or record a refund. |
| F-13 | Payouts, GST, invoices, wallet, gift cards | `admin.payouts.*`, wallet routes | Automated (`PayoutBatchesTest`, `GstMathsTest`, `WalletAndGiftCardsTest`). IRU-004 on return refunds. |
| F-14 | API v1 and health | `routes/api.php` | Automated (`ApiTest`, `ApiCartTest`, `HealthEndpointTest`, `SecurityHardeningTest`). |
| F-15 | SEO, feeds, JSON-LD, CSP | `sitemap`, feeds, `SecurityHeaders` | Automated (`StructuredDataAndFeedsTest`, `ProductVideosTest`). |
| F-16 | Scheduler, smoke, deploy scripts | `routes/console.php`, `scripts/deploy-*.sh` | Inspected. Scripts were not executed. |

Unnamed or single routes also present: newsletter, storage download, marketing unsubscribe, stock alerts, referral, gift cards, locale switch, Sanctum CSRF cookie. They are inside the 393-route list and are covered by feature tests where those features have tests (gift cards, newsletter, stock alerts, referral).

## E. Automated test results

Commands (read-only with respect to source; PHPUnit resets `iruali_test` via `RefreshDatabase`):

| Command | Result |
| --- | --- |
| `php artisan test --log-junit /tmp/iruali-audit/phpunit.xml` | **918 passed, 0 failed, 0 skipped, 10,146 assertions**, 65.6s |
| `./vendor/bin/pint --test` | **PASS**, 703 files |
| `php /tmp/composer analyse` (`composer analyse`, Larastan) | **OK**, no errors, 371 files |

PHPUnit log is local-only. Per-file counts are in `audit-evidence/phpunit-by-file.txt`. These counts are the automated suite only. Sections F–J add inspection findings the suite does not fail on.

## F. Detailed findings

### IRU-001 — Seller can open the customer order page

- **Severity:** High
- **Confidence:** Confirmed by inspection (not replayed as an HTTP request in this audit)
- **Where:** `app/Policies/OrderPolicy.php` `view()` (sellers pass when any item’s product `seller_id` is theirs); `app/Http/Controllers/Customer/OrderController.php` `show()` calls that policy and loads every item, seller part, and conversation; `resources/views/orders/show.blade.php` prints every shop’s lines, `shipping_address`, and `shipping_phone`.
- **Expected:** A shop sees only its `SellerOrder` in the Seller Centre (`onlyOwnItems()` is the seller screen).
- **Actual:** `GET /orders/{order}` is allowed for that seller and renders the customer layout: other shops’ names, quantities and prices, the delivery address, and the phone. Pickup codes are hidden unless `auth` id equals `order.user_id`. Posting a customer message is rejected (`MessageController::storeAsCustomer` requires `order.user_id`).
- **Impact:** On a multi-seller order, one shop learns the customer’s delivery contact and the rest of the basket.
- **Remediation:** Do not authorize sellers on the customer order policy. Keep seller reads on `seller.orders.show`.
- **Test to add:** Seller with one line on a two-shop order receives 403 on `orders.show`, and still 200 on `seller.orders.show` without the other shop’s lines or the phone.

### IRU-002 — Loyalty redemption is not locked

- **Severity:** High
- **Confidence:** Confirmed in code. Not reproduced under concurrent requests.
- **Where:** `app/Services/OrderService.php` caps redemption with `$user->fresh()->loyalty_points` then calls `PointsService::record()` with a negative amount. `app/Services/PointsService.php` `record()` does `User::whereKey($user->id)->increment('loyalty_points', $points)` inside a transaction with no `lockForUpdate()` and no check that the balance stays ≥ 0.
- **Contrast:** `app/Services/WalletService.php` `move()` locks the user row and throws if the balance would go negative.
- **Expected:** Two simultaneous checkouts cannot spend the same points.
- **Actual:** Both requests can read the same balance before either increment lands. The column can go negative and both orders can keep `points_redeemed_discount`.
- **Impact:** Points are a discount on the goods total. The loss is the value of the double-spent points, not a second card charge.
- **Remediation:** Lock the user row and refuse the redemption when the balance would go negative, the same way the wallet does.
- **Test to add:** Two overlapping checkouts against one balance; exactly one succeeds, and `loyalty_points` is never negative.

### IRU-003 — Greater Malé fee can be forced with a typed address

- **Severity:** Medium
- **Confidence:** Confirmed by inspection. Not submitted as a live checkout.
- **Where:** `DeliveryService::areaFor()` returns Greater Malé whenever `delivery_zone` is `greater_male`, and ignores the atoll. `StoreOrderRequest::applyAddressChoice()` overwrites the zone only when `address_id` or `island_id` is present. `OrderService::createOrderFromCart()` then calls `zoneFor($zone, $city)` and `prepareOrder()`.
- **Expected:** An address in Addu or another island is charged the island or atoll rate even if the zone radio says Greater Malé.
- **Actual:** A request with `delivery_zone=greater_male`, `shipping_city=Hithadhoo`, `shipping_state=Seenu`, and no `island_id` keeps the Greater Malé zone. The normal island picker does send `island_id`, so the storefront path is safe; a hand-built POST is not.
- **Impact:** Underpaid delivery on a crafted checkout.
- **Remediation:** Derive the zone from the island or from the city/atoll on the server, and reject a zone that contradicts them.
- **Test to add:** POST checkout with a conflicting zone and no `island_id`; fee matches the atoll, not Greater Malé.

### IRU-004 — Return refunds are not the order’s “refund due”

- **Severity:** Medium
- **Confidence:** Confirmed by inspection
- **Where:** `app/Services/ReturnService.php` has no call to `PaymentService::flagRefund()` and does not set `orders.refund_status`. It writes `ReturnRequest.refund_amount` and a negative `SellerAdjustment` using `(1 - commission_rate)` of the returned goods. Admin “refund due” is `orders.refund_status = due` (cancel, duplicate BML confirm, disputes).
- **Expected:** Money still owed to the customer appears in one queue.
- **Actual:** A return can be approved and the shop debited while the order never shows as refund-due. A later order-level refund could pay the same value again if someone flags it separately.
- **Impact:** Missed or double card refunds, limited to return amounts. `refundableLeft()` caps the return figure against `total_amount`.
- **Remediation:** When a return is approved, flag the order refund (or drive the card refund from the return) and block a second flag for the same amount.
- **Test to add:** Approving a return sets `refund_status` (or an equivalent single ledger) and a second order-level refund cannot exceed what is left.

### IRU-005 — “Ships to other islands” is not enforced

- **Severity:** Medium
- **Confidence:** Confirmed by inspection
- **Where:** `users.ships_to_islands` is saved from the seller profile (`SellerController`). Checkout stock checks use `CartItem::availableStock()` (product or variant quantity only). `product_island` exists as a table and as validation on the product form; no checkout or stock service reads it.
- **Expected:** If the shop says it does not ship to other islands, checkout to those islands fails.
- **Actual:** The flag is descriptive. Any deliverable address can buy the product if stock and holiday rules pass.
- **Impact:** Orders the shop did not agree to fulfil. Pickup rules are enforced (`StoreOrderRequest::validateDeliveryChoices()`).
- **Remediation:** Either enforce `ships_to_islands` (and any real per-island catalogue) in `OrderService`, or stop presenting the checkbox as a delivery limit.
- **Test to add:** Shop with `ships_to_islands` false cannot be checked out to an island outside Greater Malé.

### IRU-006 — `PaymentService::confirm()` repeats side effects

- **Severity:** Low
- **Confidence:** Confirmed by inspection
- **Where:** `PaymentService::confirm()` always sets `payment_status` and calls `FunnelService::orderPaid()`, `GstService::orderPaid()`, `awardRewards()`, `GiftCardService::issueForOrder()`, and `paymentUpdated()`. `FunnelService::orderPaid()` inserts rows with no uniqueness check. `syncBml()` does not call `confirm()` again when the order is already paid. Points and gift cards are idempotent in tests; funnel rows and the payment email are not.
- **Expected:** A second confirm is a no-op.
- **Actual:** An admin or legacy caller can confirm twice and duplicate funnel events and payment notifications.
- **Remediation:** Return immediately when `payment_status` is already `paid`.
- **Test to add:** Second `confirm()` does not insert another `order_paid` funnel row and does not send a second payment notification.

### IRU-007 — Guest capability token is in the BML redirect URL

- **Severity:** Low
- **Confidence:** Confirmed (intentional; `GuestCheckoutTest` asserts the token is in the URL BML is given)
- **Where:** `PaymentService::startBmlPayment()` builds `payments.bml.return` with `token` = `guest_token` when the order is a guest order.
- **Impact:** BML, redirect logs, and browser history see the guest code. Anyone with that URL can land on the guest order page. The code is long and random; this is exposure, not a guessable id.
- **Remediation:** If BML can accept a return URL without the secret, keep the token only in the session or in a one-time server-side map. Do not weaken the cross-browser guest return without a replacement.
- **Test:** Already covered. A change should keep the guest’s second browser working.

### IRU-008 — BML v2 signature has no freshness window

- **Severity:** Informational
- **Confidence:** Confirmed, and intentional
- **Where:** `BmlConnect::verifyWebhookSignature()` checks `sha256(nonce + timestamp + api_key)` only. `BmlPaymentTest` accepts a timestamp one hour old. The review that removed the 300-second window is on `main` (`b9597af`).
- **Impact:** A captured signature can be replayed. `syncBml()` re-fetches BML and matches amount, currency, and local id, so a replay does not mark an unpaid order paid by itself.
- **Remediation:** None required for correctness. A nonce cache is optional hardening if BML’s timestamp format becomes documented.

### IRU-009 — Public health and deploy docs disagree with the code

- **Severity:** Informational
- **Confidence:** Confirmed
- **Where:** `routes/api.php` `/health` returns `status`, `timestamp`, and `version` unless `X-Health-Token` matches `config('app.health_detail_token')`. `docs/FEATURES.md` still says the endpoint reports the running commit and tag. `docs/PAYMENTS_BML.md` and `docs/FEATURES.md` still show `/payments/bml/return/{order}` without `{token}`. `docs/FEATURES.md` still describes production as tag-based; `docs/SERVERS.md` and `docs/PRODUCTION_DEPLOY.md` say production follows `main` via `bash ~/deploy-live.sh`. `AGENTS.md` says 918 tests, which matches this run.
- **Impact:** An operator who trusts `FEATURES.md` can think a public health check failed because `commit` is missing, or can document the wrong guest return URL.
- **Remediation:** Update those paragraphs. `scripts/deploy-lib.sh` `verify_site` already matches the code.

## G. Financial verification

Authoritative order of operations in `OrderService::createOrderFromCart()`:

1. Shop discounts (`ShopDiscountService`), including multi-buy, allocated to the laari.
2. Iruali voucher on the goods left after shop discounts.
3. Points, clamped to the balance and to the goods left (`floor` of that remainder).
4. Delivery fee from `DeliveryService::prepareOrder()` (zone fee once for delivered parts, plus per-item surcharges). Free delivery does not waive surcharges.
5. `total_amount = final goods + shipping`.
6. `FulfilmentService::refreshPart()` sets commission and seller earnings on `(subtotal − shop discount)`. Iruali voucher and points do not reduce seller earnings (platform subsidy).

Worked example from `ShopDiscountCodesTest::test_shop_codes_come_first_then_the_iruali_voucher_on_what_is_left_then_points` (this test passed in the suite):

| Step | Amount (MVR) |
| --- | --- |
| Cart (shop A 33.33×3 + 19.99, shop B 100) | 219.98 |
| Shop code 10% of shop A’s 119.98 | −12.00 |
| After shop code | 207.98 |
| Voucher 5% of 207.98 | −10.40 |
| Points | −50.00 |
| Goods | 147.58 |
| Island delivery (fixture) | +75.00 |
| Order total | 222.58 |

The test also checks shop A’s 12.00 split as 10.00 and 2.00 across its lines, and that the order “adds up”.

GST, from `GstMathsTest` (passed): inclusive GST is computed in laari, half up. Examples: 8% of 100.00 → 7.41; 8% of 0.13 → 0.01; 100% of 0.01 → 0.01. No legal opinion; this matches `GstService::gstIncluded()`.

BML charge is `cardAmount()` in laari (`round(amount × 100)`), after any wallet portion. Duplicate BML confirmations are flagged for refund rather than applied twice (`RefundsAndHardeningTest`). Invoice numbers are allocated under a series lock (`GstInvoiceConcurrencyTest`).

Not independently re-calculated outside the suite: payout batch totals, partial-return rounding against stored `seller_earnings`, and quote prices. The suite covers typical cases. IRU-002 and IRU-004 are the gaps that matter.

Referral points are not clawed back when the first paid order is cancelled. `ReferralProgrammeTest` expects the referee to keep them. That is current intended behavior, not a regression.

## H. Security and authorization

Enforced, and covered by tests:

- Sanctum abilities on profile, cart, wishlist, orders, and product create (`routes/api.php`, `App\Support\ApiToken`). A token with only `profile:read` cannot add to the cart (`SecurityHardeningTest`).
- Privileged user columns are not mass-assignable (`is_seller`, approval, bans, loyalty, smoke-test flag).
- Staff routes follow `config/staff.php`. Support cannot confirm payment or record a refund. Wallet refunds stay admin-only.
- Guest order pages need the signed URL and a constant-time token match.
- BML `syncBml()` confirms only when the API amount, currency, and local id match.
- Shop staff are limited to their role list and cannot act on another shop.
- Product descriptions and JSON-LD names are escaped (`SecurityHardeningTest`). CSP sends a per-request script nonce. `script-src-attr 'unsafe-inline'` remains so existing `onclick` handlers work (defense in depth only).

Residual, not demonstrated as an exploit:

- `Order`, `Product`, and parts of `User` still have wide `$fillable`. Current controllers pass explicit arrays or `forceFill`. A future `$request->all()` into those models would be dangerous.
- `POST /api/v1/logout` requires `auth:sanctum` but no ability. It only deletes the current token.
- `GET /storage/{path}` serves any file on the public disk. Uploads use generated names. A sensitive file stored on the public disk would be readable.
- Webhook replay is possible (IRU-008) and does not by itself confirm a payment.
- The return URL runs `syncBml()` before the visitor check, so a stranger who knows `transactionId` can force a sync. The suite asserts they are sent to login and do not see the order. State still comes from BML.

No live secrets were found in tracked config. `.env.example` uses empty placeholders. Nothing from `.env` is copied into this report.

## I. Data integrity and concurrency

| Topic | What the code does | This audit |
| --- | --- | --- |
| Stock | Conditional decrement; failure rolls the order back | Covered by order and pre-order tests. Not re-run as a custom race. |
| Pre-order caps | Product row locked in `PreorderService::reserveForCheckout` | `PreorderConcurrencyTest` in the suite. |
| Wallet | `lockForUpdate` and non-negative balance | `WalletAndGiftCardsTest` in the suite. |
| Points | No lock, no floor | IRU-002. Not load-tested. |
| BML confirm | Transaction and order locked; duplicate confirm flags a refund | Suite. |
| Invoice numbers | Series lock | `GstInvoiceConcurrencyTest` in the suite. |
| Quotes | Locked before place | Suite. Not re-run as a custom race. |
| `flagRefund()` | Read-modify-write without its own order lock | Low. Callers that already hold the order lock (`syncBml`) are safe. |

## J. UI, mobile, accessibility, localization, and SEO

No browser session was opened. Viewport, contrast, keyboard, and console checks were **not run**. Feature tests do render Blade and assert copy, Dhivehi strings (`DhivehiTest`), RTL markers, CSP, JSON-LD, sitemap, and feed XML. That is not a substitute for a visual pass.

Static notes only:

- Customer order pages use `max-w-4xl` while much of the storefront uses `max-w-7xl`. Not a defect.
- Checkout delivery script does not move the zone radio when the island picker changes. The server corrects the zone when `island_id` is posted (see IRU-003 for the case where it is not).
- JSON-LD script tags are left without a nonce on purpose (`SecurityHeaders` skips non-JavaScript types). `StructuredDataAndFeedsTest` passed.

## K. Operational and integration review

Scheduler (`routes/console.php`), all inspected, none invoked:

Hourly: cancel unpaid card orders, cart reminders, quote expiry, seller-application reopen. Every minute: scheduler heartbeat, and `queue:work --stop-when-empty` when the queue is not `sync`. Daily: DB backup 02:00, backup clean 01:30, guest-cart prune, gift-card expiry, funnel prune, Sanctum prune, failed-job prune, error digest 07:00 Maldives, low-stock digest 08:00, brand-follow digest 09:00, wishlist price drops 10:00, late pre-order flag 06:45. Monthly: backup restore drill and points expiry.

`docs/SERVERS.md` shows the production crontab already runs `schedule:run` every minute for `/home/iruali/iruali`. This audit did not log into the server to confirm that line is still installed.

Deploy scripts were not executed. From reading them:

- `verify_site` sends `X-Health-Token` when `HEALTH_DETAIL_TOKEN` is in the environment and compares `commit` only if the JSON contains it. That matches `/api/health`.
- If `APP_URL` is empty, health and smoke are skipped and the function returns success. A mis-configured production `.env` could deploy without those checks.
- GitHub check state `unknown` warns and continues (unauthenticated API). Failed or pending checks block a `main` deploy when the API responds.
- Rollback checks out the previous commit and does not reverse migrations.
- `storage:link`, deploy stamp, `queue:restart`, and `artisan down` failures are warnings.

SMS is Dhiraagu-shaped (`sms:test`). It was not run. BML keys are unset in production, so card pay is off until they are added. Social login buttons appear only when provider keys exist.

`backup:restore-drill` is scheduled. This audit did not restore a backup, so restore is **not** claimed to work.

## L. Documentation and implementation mismatches

| Source | Says | Code |
| --- | --- | --- |
| `docs/FEATURES.md` health paragraph | Public commit and tag | Token-gated (`routes/api.php`) |
| `docs/FEATURES.md`, `docs/PAYMENTS_BML.md` | Return URL `…/return/{order}` | Optional `{token}` for guests (`routes/web.php`) |
| `docs/FEATURES.md` operations | Tag then `deploy-production.sh <tag>` | `docs/SERVERS.md`: `bash ~/deploy-live.sh` on `main`; tags still accepted by the script |
| `docs/FEATURES.md` schedule list | Subset | Full list in section K |
| `docs/PRODUCTION_DEPLOY.md` | Follow `main` at the top; one later paragraph still says every release is a tag | Script supports both |
| Island marketing vs seeder | A full inhabited-island list is not in `IslandSeeder` | Seeder inserts 4 islands (Malé, Hulhumalé, Addu City, Fuvahmulah). Tests insert more. No admin UI to add islands. |
| `ships_to_islands` copy | Reads as a coverage choice | Not enforced (IRU-005) |

`docs/SERVERS.md` matches the deploy and health behavior that is actually in the scripts.

## M. Untested and blocked

- No browser, mobile, or keyboard pass. No screenshots.
- No concurrent HTTP load for IRU-002 or checkout stock. Concurrency evidence is the existing tests plus code inspection.
- No crafted checkout POST for IRU-003 (would need a disposable app database and a running server; not started, to avoid touching the dev SQLite file).
- No HTTP replay of IRU-001. The policy and the Blade template are sufficient to show the data is rendered.
- Deploy, rollback, backup restore, anonymise, and `sms:test` were not run.
- Production and `test.iruali.mv` were not opened.
- Real BML, SMS, and mail were not called.
- Payout-file generation and bank verification were not executed against files.
- `composer` was not on `PATH`; Larastan was run with `php /tmp/composer analyse`.

## N. Prioritized remediation backlog

1. IRU-001 — Stop sellers using `OrderPolicy::view` on the customer order page. Add the cross-shop test.
2. IRU-002 — Lock points redemptions and reject a negative balance. Add an overlapping-checkout test.
3. IRU-003 — Server-side zone must follow the island or the atoll. Reject mismatches.
4. IRU-004 — One refund ledger for returns and order cancellations.
5. IRU-005 — Enforce or relabel `ships_to_islands`.
6. IRU-006 — Make `confirm()` return when the order is already paid.
7. Docs in section L — Align `FEATURES.md` and the tag paragraph in `PRODUCTION_DEPLOY.md` with `SERVERS.md`.
8. IRU-007 — Only if guest-token exposure to BML is no longer acceptable.

Do not treat IRU-008 as a functional bug. The timestamp window was removed because BML’s format is undocumented and the live check is the signature plus the API re-fetch.

## O. Final integrity checklist

- The current checkout was inspected at `07f9e0fa1c1a1daaeadaec11d4a768bb4efdb00f` on `main`.
- The working tree was clean at the start. Recent commits in section C were read as history, not as a dirty diff.
- Every route group in section D has a coverage status. Individual unnamed routes are included in the 393-route total.
- Major workflows were either in the 918-test run or marked not run in section M.
- Financial totals were checked against `ShopDiscountCodesTest` and `GstMathsTest`, which passed in this run. IRU-002 and IRU-004 were not re-solved by those tests.
- Authorization boundaries were read (staff list, shop staff, Sanctum abilities, order policy). IRU-001 is the exception that failed that reading.
- UI, mobile, and a live localization pass were not done. Dhivehi coverage is the existing `DhivehiTest` inside the suite.
- Findings cite files and, where noted, tests that passed. No finding is marked as an executed exploit unless the suite already asserts that behavior.
- No email, SMS, payment, webhook, deploy, or remote site was used.
- No application code, test, config, migration, or asset was edited.
- After the report and `audit-evidence/` were added, `git status --short` should list only those new untracked paths. No existing file should be modified.
