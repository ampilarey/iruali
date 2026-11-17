# Audit fix plan (audit of 2026-11-16)

Status: **All six batches done and live (2026-11-17), checked item by item against `main` the same day; what was left for later is in `docs/LATER.md` ("Left over from the audit").** A read-only audit of `main` at `93832c1` found the items below.
This file is the work order: do the batches in order, one pull request per batch.

Tick an item (`[x]`) in this file in the same PR that fixes it.

## How to work (read first)

- Read `AGENTS.md` and `docs/SERVERS.md`. Branch from `main`, open a PR, never push to `main`.
- **Confirm before you fix.** Line numbers are from `93832c1` and will drift; find the code by the
  names given. Items marked **(confirm)** were found by reading only: reproduce them with a failing
  test first. If one does not reproduce, write why under the item and move on.
- **Every fix gets a test that fails before and passes after.** Go through HTTP (the real route and
  form request) wherever the bug lives in a controller or request. Several bugs were missed because
  tests called the service directly.
- Money: work in laari (integers) like `ShopDiscountService::toLaari()`. Guard every "do once" step
  with a conditional `UPDATE ... WHERE status = ...` and act only when one row changed, or
  `lockForUpdate()` inside the transaction. Never check status on a model loaded before the transaction.
- Before each PR:
  - `./vendor/bin/pint`
  - `composer analyse` (as root: `COMPOSER_ALLOW_SUPERUSER=1`)
  - `php artisan test`
  - Dhivehi for any new `__()` string in `resources/lang/dv.json`
  - `npm run build` and commit `public/build` when CSS/JS/classes change
  - for a page change, a Playwright check at 390×844 with `scrollWidth === 390`
- Rate limits must be named: `throttle:N,M,name`. Read settings with `config()`, never `env()`
  outside `config/`.
- Never `db:seed` production. No secrets in commands, docs or commits.
- Do not widen a batch. If a fix needs a product decision, check "Owner decisions" at the end. If the
  question is not there, stop and ask.

Severity: **H** high, **M** medium, **L** low.

---

## Batch 1: sign-in and privacy

### 1.1 [x] H: phone number changes keep "verified"

- **Where:**
  - `Api/UserController::update` (`PUT /api/v1/user`);
  - `Seller/SellerController` profile update (`PUT /seller/profile`);
  - `Seller/ApplicationController` (`POST /seller/apply`).
- **Bug:** all three write `phone` and leave `phone_verified_at` set.
  - `User::findByVerifiedPhone()` trusts that flag for SMS sign-in, so an attacker can make their
    account the "verified" holder of a victim's number. The victim's SMS sign-in then lands in the
    attacker's account.
  - The uniqueness rules compare raw strings, while the lookup is normalised
    (`App\Services\Sms\PhoneNumber::normalize`). The API has no unique rule at all.
- **Fix:**
  - Add one `saving` hook on `User`: when the *normalised* phone changes, set `phone_verified_at`
    to null. `Customer/ProfileController` and `Admin/CustomerController` already do this by hand;
    keep them working.
  - Make sure the OTP-verify paths, which set the phone and the flag together, still end up verified.
    Set the flag after the phone, or bypass the hook explicitly there.
  - Add a normalised uniqueness rule (a reusable `Rule` class) to all phone-writing forms, including
    the API. Reject a number that another account holds verified.
- **Tests:**
  - each of the three routes: changing the phone nulls the flag;
  - the same number typed differently (`+960 7771234`) does not null it;
  - a number verified on another account is refused;
  - phone OTP sign-in still verifies.

### 1.2 [x] H: service worker keeps private pages after sign-out

- **Where:** `public/sw.js` (the `PRIVATE` regex, the page cache `iruali-pages-v1`).
- **Bug:**
  - `/messages`, `/quotes`, `/request-an-item`, `/pay`, `/feedback`, `/complaints`, `/gift-cards`
    and `/shop-invitations` are cached and readable offline after logout.
  - Public pages viewed while signed in are cached with the personal header and CSRF token.
  - A redirect to `/login` is cached under the original URL.
- **Fix:**
  - Add the missing prefixes to `PRIVATE`.
  - Do not cache when `response.redirected`.
  - Do not cache HTML for signed-in users. Simplest: have the server send `Cache-Control: no-store`
    (or a header such as `X-Iruali-Private: 1`) on every HTML response when `auth()->check()`, and
    have the SW skip caching when it sees it.
  - On logout, delete `iruali-pages-*` (post a message to the SW from the logout response page, or
    bump the cache name).
  - Bump the cache version so existing caches are dropped.
- Also in this file (**L**):
  - `notificationclick` compares an absolute URL to a relative one; use
    `new URL(url, self.location.origin).href`.
  - `pushsubscriptionchange` should resubscribe. **Not done (deferred):** re-subscribing from the
    service worker needs an endpoint that accepts the new subscription without a session or CSRF
    token. Until that exists, the account page re-subscribes on the next visit, as before.
- **Tests:**
  - a PHPUnit test that signed-in HTML responses carry the no-store marker and guest ones do not;
  - a Playwright check that after logout the caches hold no `/messages` entry.

### 1.3 [x] H: Facebook sign-in keys are overwritten

- **Where:** `config/services.php` has two `'facebook' =>` blocks (sign-in near line 141, page
  posting near line 165). PHP keeps the last, so `services.facebook.client_id` is always null.
- **Fix:**
  - Rename the second block to `facebook_page`.
  - Update every reader: grep for `services.facebook.page_id`, `page_token`, `instagram_account_id`,
    and `graph_version` used by `SocialPublisher`.
  - Keep `graph_version` correct for each use.
- **Test:** load the real config file with env set and assert that both `services.facebook.client_id`
  and `services.facebook_page.page_id` resolve. Add a generic test that no top-level key is duplicated
  in `config/services.php` (tokenise the file).

### 1.4 [x] M: staff email shown to anyone who types a staff phone number

- **Where:** `PhoneLoginController::staffStep` (puts the full email in the session as `shown`) and
  `resources/views/auth/_sign-in-steps.blade.php` (prints it and puts it in a hidden input).
- **Fix:**
  - Show the phone number that was typed, not the email.
  - Keep the email only in the session.
  - Have `AuthController::login` read it from the session when the hidden field is absent.
- **Test:** the response for a staff phone does not contain the staff email.

### 1.5 [x] M: anyone can keep staff locked out

- **Where:** `App\Support\StaffLockout` (keyed on the account only).
- **Fix:**
  - Key the hard lock on account + IP.
  - Keep a much higher account-wide ceiling (e.g. 50 per hour) that triggers a team alert, not a lock.
- **Tests:**
  - wrong passwords from IP A do not block a correct sign-in from IP B;
  - the same IP is still locked after 5.

### 1.6 [x] M: API password reset is weaker than the web one

- **Where:** `Api/AuthController` (`forgot-password`, `reset-password`).
- **Fix:**
  - Use the same `Password` rule as the web reset.
  - Call `$user->endOtherSessions()` and delete the user's Sanctum tokens in the reset callback.
  - `forgot-password` always returns the same success message.
- **Tests:** new `ApiPasswordTest` covering forgot (same answer for known and unknown email), reset
  (weak password refused, tokens revoked), change-password and logout.

### 1.7 [x] M: voucher box has no guess limit

- **Where:** `routes/web.php`, `cart/apply-voucher`; code format in `Admin/VoucherController`
  (batch codes: prefix + 6 characters).
- **Fix:** add `throttle:20,1,cart.applyVoucher`. New batch codes get 10 random characters.
- **Test:** `RateLimitTest` covers the name; the 21st attempt gets 429.

### 1.8 [x] M: shop link leaks any user's name in the page title

- **Where:** `/shops/{seller}` → `ShopController::seller` / `SeoService` (profile title built before
  `abort_unless($seller->isSeller(), 404)`).
- **Fix:** abort before any SEO data is set. Error pages use the default title.
- **Test:** `GET /shops/{id of a non-seller}` is 404 and the body does not contain that user's name.

### 1.9 [x] L: CSP nonce is stamped onto every inline script

- **Where:** `app/Http/Middleware/SecurityHeaders.php` (`stampScriptNonce`).
- **Fix:**
  - Emit the nonce from Blade (`Vite::useCspNonce()` and `nonce="{{ ... }}"` on inline scripts).
  - Remove the response rewrite.
  - Leave `script-src-attr` for a later pass.
- Do this last in the batch. Check every layout in a browser for CSP errors.

### 1.10 [x] L: private routes are indexable

- **Where:** `resources/views/components/seo-meta.blade.php` (noindex list) and `public/robots.txt`.
- **Fix:**
  - Add to the noindex list: `login.*`, `messages.*`, `quotes.*`, `item-requests.*`, `guest.*`,
    `complaints.*`, `pay*`, `orders.feedback`.
  - Add `/messages`, `/quotes`, `/pay` to robots.txt.

---

## Batch 2: refunds and money

Do 2.1 first; 2.2–2.4 build on it.

### 2.1 [x] H: returns and disputes are valued without the iruali voucher and points (confirm)

- **Where:**
  - `ReturnService::unitsValue`, `suggestedRefund`, `approve`;
  - `DisputeService::maxClaim`;
  - `OrderItem::netTotal()` (after shop discounts only).
- **Bug:**
  - An item bought with a site voucher or redeemed points is refunded at its pre-voucher value.
  - Points come back as cash.
  - A dispute can claim more than was paid.
  - A "changed my mind" return can end up refunding delivery through the order-total cap.
- **Fix:**
  - Add one allocation that spreads the order's voucher discount and points redemption over its
    items in laari. Use the largest-remainder method, like `ShopDiscountService::allocate`.
  - Expose `OrderItem::paidTotal()`.
  - Preferably store it at order time: a migration adds `order_items.paid_total`, backfilled for
    existing orders by the same allocation.
  - Value returns and disputes on that figure.
  - Decide what the shop gives back (see owner decision A).
  - Keep `refund_value` history working for old returns.
- **Tests:** scenarios with a 20% voucher, with points, with both, multi-shop, and partial quantities.
  Refunds across all items must never exceed the amount paid.
- **Done** with owner decision A at its default. **Follow-up (not decided):** points redeemed on
  returned items are not given back as points; only a full cancellation returns them.

### 2.2 [x] H: dispute refund is not re-capped, and a later return pays again

- **Where:** `DisputeService::resolve` (uses `amount_claimed` fixed at open); `ReturnService::canRequest`
  (ignores open disputes).
- **Fix:** inside the resolve transaction:
  - lock the order row;
  - recompute `maxClaim()`;
  - cap the refund at `min(claimed, maxClaim now, order paid − already refunded)`;
  - refuse when 0.

  Make return approval subtract resolved dispute amounts the same way.
- **Test:** dispute opened → return approved and refunded → dispute resolved "full" pays only the
  remainder. Also the opposite order.

### 2.3 [x] H: cancelling after a dispute/return refund refunds twice (confirm)

- **Where:**
  - `OrderService` cancel path (around `reverseOrder`);
  - `BookingFeeService::refundSplit`;
  - `WalletService::reverseOrderPayment`.
- **Fix:**
  - The cancel refund = amount paid − refunds already flagged or paid (dispute `amount_resolved`,
    approved/refunded returns).
  - Reverse the related negative `SellerAdjustment` rows, or do not create a new deduction for a
    part that will never be paid out.
- **Test:** paid order, dispute resolved full, then admin cancel → total refunded equals amount paid.
  Shop adjustments net to zero.

### 2.4 [x] H: "refund to wallet" can credit twice

- **Where:**
  - `WalletService::refundOrderToWallet`, `refundReturnToWallet`;
  - `PaymentService::markRefunded`;
  - `ReturnService::markRefunded`.
- **Fix:**
  - Inside the transaction, first run a conditional update
    (`where('refund_status','due')->update([... 'refunded' ...])`, and `status = 'approved'` for
    returns).
  - Credit the wallet only if one row changed.
  - Apply the same guard when recording a card refund.
- **Test:** call twice in a row with a stale model; the wallet is credited once.

### 2.5 [x] M: dispute resolve is not once-only; `flagRefund` loses updates

- **Where:** `DisputeService::resolve`; `PaymentService::flagRefund`.
- **Fix:**
  - A conditional update on the open statuses (as `ReturnService::approve` does).
  - `flagRefund` locks the order row and re-reads before adding.
- **Test:** double resolve creates one refund and one adjustment.

### 2.6 [x] M: refund approval is reused when the amount changes

- **Where:** `PaymentService::flagRefund`; `App\Support\RefundApproval`.
- **Fix:**
  - Null `refund_approved_at/by` whenever the due amount changes.
  - When the status is already `refunded`, do not overwrite the old amount. This needs a
    `refunds` table (one row per refund: amount, reason, reference, approved by, paid at), with the
    order columns as a running summary. If that is too large for this batch, at least clear the
    approval and write the audit record. Note the table as a follow-up.
  - **Done:** the approval is cleared and the audit record keeps the earlier status, amount and
    reference. **Follow-up (not done):** the `refunds` table, one row per refund.

### 2.7 [x] M: referral points are not taken back on cancel, and can be awarded twice

- **Where:** `DiscountService::processReferralRewards`; `OrderService::reverseOrder`.
- **Fix:** award when the order is **delivered** (owner decision B), with a conditional stamp on
  `referral_rewarded_at`. If awarding at payment is kept, record the rewarding order and reverse on cancel.

### 2.8 [x] M: pay-later credit limit race

- **Where:** `CreditService` (the check called from `OrderService` around line 141).
- **Fix:** `lockForUpdate()` the `credit_accounts` row inside the checkout transaction before the check.

### 2.9 [x] M: BML "mismatch" payments are silent

- **Where:** `PaymentService::syncBml` (the MISMATCH branch); `ReconcileBmlPayments`.
- **Fix:** on MISMATCH, flag a refund for the amount BML reports and send a team alert.

### 2.10 [x] L: wallet rows treated as BML rows

- **Where:**
  - `Admin/AdminController::syncBmlPayment` (loops over all transactions);
  - `FinanceService` (card total);
  - `GstReportService` (the dispute goods cap uses the gross subtotal).
- **Fix:**
  - Add `where('gateway','bml')` in the first two.
  - `GstReportService` uses `netSubtotal()`.

---

## Batch 3: orders, stock and checkout

### 3.1 [x] H: accepted item requests cannot be ordered on the website

- **Where:** `StoreOrderRequest` (the cart loop: `! $item->product->is_active`). Item-request
  products are created inactive (`ItemRequestService::quote`).
- **Fix:**
  - Move the "still on sale" check into `OrderService::createOrderFromCart`'s pre-check so the API
    gets it too (this also fixes 3.5).
  - Allow a line whose product belongs to an accepted item request of this customer.
    `PreorderService::accepting()` already has this exception.
  - Keep the form-request message.
- **Test:** full HTTP flow: request → quote → accept → `POST /checkout` succeeds.

### 3.2 [x] H: a shop can advance an unpaid order

- **Where:** `FulfilmentService::nextStatuses` / `advance`; `OrderService::advanceTo`.
- **Fix:**
  - `nextStatuses()` returns `[]` unless the order is paid, deposit-paid, cash on delivery, or
    pay-later credit. Reuse an existing helper on `Order` if one expresses "may be prepared";
    otherwise add `Order::mayBeFulfilled()`.
  - Hide card orders not yet announced (`placed_notified_at` null) from the seller order list and
    counts.
- **Tests:**
  - an unpaid card order's part cannot be moved (HTTP 4xx or redirect with error);
  - it is absent from the seller list;
  - the auto-cancel still cancels it and restores stock.

### 3.3 [x] H: seller stock page overwrites live stock

- **Where:** `Seller/StockController::update`; `resources/views/seller/stock.blade.php`.
- **Fix:**
  - Post the original value beside each input (`original[id]`).
  - Apply only rows where the posted value ≠ the original.
  - Apply as a delta (`new − original`) with `lockForUpdate`, never below 0.
  - Route increases through `PreorderService::receiveStock()` when pre-orders are waiting.
- **Test:** page loaded at 5, a sale takes 2, the shop saves an unrelated row → stock stays 3. The
  shop changes 5 → 8 → stock becomes 6.

### 3.4 [x] M: double tap creates two orders

- **Where:** `OrderService::createOrderFromCart` (the cart is loaded before the transaction).
- **Fix:**
  - As the first step in the transaction: lock the cart row, re-read its items, and abort with the
    "cart is empty" error if there are none.
  - Disable the Place order button on submit in `checkout` JS.
- **Test:** two calls with the same pre-loaded cart create one order.

### 3.5 [x] M: API checkout skips the "still on sale" check

Covered by 3.1's move. Add an API test for a deactivated product. Refuse the silent pre-order of a
sold-out item when no terms were shown.
**Done:** the app must send `accept_preorders: true` to buy pre-order lines (POST /api/v1/orders).

### 3.6 [x] M: pre-orders paid after stock arrived are never allocated

- **Where:**
  - `PreorderService::receiveStock` (skips unpaid orders);
  - `PaymentService::confirm`;
  - `BookingFeeService::feePaid`.
- **Fix:** after a payment is confirmed, allocate any free stock to that order's waiting pre-order
  lines. Use the same method with an empty arrival.

### 3.7 [x] M: "Remove seller access" leaves products on sale

- **Where:** `Admin/AdminController::rejectSeller`; the guard in `OrderService` (around line 51).
- **Fix:** deactivate the shop's products, as suspend does. Change the order guard to refuse products
  whose owner may not sell (`maySell()`).

### 3.8 [x] L: item request price and quantity not re-checked at order time

- **Where:** `ItemRequestService::placeOrder`; `CartController::update`.
- **Fix:**
  - Refuse unless the request is ACCEPTED, not expired, and the line quantity equals the request
    quantity.
  - Block quantity changes on item-request lines.

### 3.9 [x] L: "shop confirms first" pre-orders never expire

- **Where:** `PreorderConfirmService` (around line 247).
- **Fix:** auto-cancel after N days without confirmation (setting, default 7). Notify the customer.
- **Done:** `preorder_confirm_days` (default 7) is set in Admin → Settings → Pre-order imports (added
  afterwards); the daily `preorders:balance-reminders` run cancels them and the customer gets the usual
  cancellation message.

### 3.10 [x] L: voucher and points forgotten before the order can still fail

- **Where:** `OrderService` (session `forget` inside the transaction, around lines 291–296).
- **Fix:** move the forget to after commit.

### 3.11 [x] L: price changed between checkout page and submit

- **Fix:** post the displayed total (hidden field). If the recomputed total differs, return to
  checkout with "prices changed, please review". Model: `BookingFeeService::forOrder`.

### 3.12 [x] L: two concurrent return requests

- **Where:** `ReturnService::request`.
- **Fix:** lock the `SellerOrder` row and re-check `canRequest()` in the transaction.
  (`ReturnService::request()` now enforces `canRequest()` itself: window, one open request per part.)

---

## Batch 4: operations and data safety

### 4.1 [x] H: SMS campaign stuck in "sending"

Done: stuck "sending" campaigns resume (recipients table skips the ones already sent), one alert per campaign stuck over an hour, counts taken from the recipients table, and the command runs in the background (4.11), so no queued chunks were needed.

- **Where:** `SendSmsCampaigns` (selects `scheduled` only); `SmsCampaignService::send`.
- **Fix:**
  - Select `sending` campaigns too. The recipients table already makes a re-run safe.
  - Alert when a campaign has been `sending` for over an hour.
  - Better: send in queued chunks so the scheduler is not blocked (see 4.11).

### 4.2 [x] H: backups (confirm on the server)

Done: weekly `files-*.zip` of `storage/app`, `iruali:ready` fails in production without an off-server disk, drill checks the newest files backup and a sample of product images, documented. **Owner decision C still open.**

- **Where:** `routes/console.php` (`backup:run --only-db`), `config/backup.php`, `ReadyChecks`.
- **Fix:**
  - Add a weekly files backup of `storage/app` (public and private) to the off-server disk.
  - `iruali:ready` fails when no off-server backup disk is configured.
  - Document `BACKUP_DISKS` in `docs/SERVERS.md` and `.env.example`.
  - The restore drill also checks that a sample of files exists.
- **Owner action:** set the off-server disk on production (owner decision C).

### 4.3 [x] H: staging anonymiser misses personal data

Done: all of the listed tables plus guest conversations, disputes, item requests, gift cards, reviews, email lists, IPs; `AnonymiseTest` fails on any personal-looking column not covered or explained. `notifications` has no data columns here, so nothing to replace.

- **Where:** `AnonymiseCommand`; `docs/PRODUCTION_DEPLOY.md` claims more than it does.
- **Fix:** cover:
  - `addresses`;
  - `seller_bank_accounts`;
  - `sms_messages`;
  - `messages`;
  - `complaints`;
  - `stock_alerts.email`;
  - `customer_notes`;
  - `sessions` (truncate);
  - `notifications`;
  - `push_subscriptions`;
  - `social_accounts`;
  - `otps` (truncate);
  - `users.birth_date`;
  - on orders: guest email and name, gift receiver name and phone, gift message.
- **Test:** build a list of columns whose names look personal (`name`, `phone`, `email`, `address`,
  `account_number`, `message`, `body`) across the schema. Fail when one is neither handled nor on an
  explicit allow-list.

### 4.4 [x] M: deploy script

Done: `bash -n` and shellcheck clean.

- **Where:** `scripts/deploy-lib.sh`, `scripts/deploy-production.sh`.
- **Fixes:**
  - Database dump before `migrate --force`; abort if it fails.
  - `php artisan down --render="errors::503" --retry=30`; stop the deploy if `down` fails.
  - `config:clear` before migrate.
  - Do not run `up` in the "rollback failed" branch.
- **Constraint:** `verify_site` must keep working. CI does not run these scripts, so run
  `bash -n` and shellcheck, and describe the manual test in the PR.

### 4.5 [x] M: tables and logs that only grow

Done: codes stored as an HMAC with the app key; the migration empties `otps`. The daily clean-up got its test (`UntestedRoutesTest`) in the verification pass.

- Daily prune of expired `cache` and `cache_locks` rows (database cache store).
- Daily prune of `otps` older than a day. Store OTP codes hashed (`hash('sha256', code . app key)`),
  compared with `hash_equals`. Migration: widen the column and delete existing rows.
- `config/logging.php` defaults `LOG_STACK` to `daily`. Add a ready check on log level and size.
  Document it in SERVERS.md.

### 4.6 [x] M: sign-in codes readable in logs and the SMS table

Done: code texts stored masked and never resent; the log driver masks digits in production.

- **Where:** `Sms/LogSms`; `SmsManager::record`.
- **Fix:**
  - Mask digits in stored and logged text for code-type messages. Keep the real text only for the
    `log` driver in non-production.
  - The ready check **fails** on `SMS_DRIVER=log` in production.

### 4.7 [x] M: account deletion leaves data behind

Done (decision D as default: rows kept, text blanked). API tokens and sessions are deleted too.

- **Where:** `CustomerAccountService::erase`.
- **Fix:** also remove:
  - `sms_messages` to the user's number;
  - the user's message bodies (keep the row, blank the text, per the "conversations are never
    deleted" rule; see owner decision D);
  - quote business details;
  - the avatar file;
  - payment and deposit slip files;
  - the user's `sessions` rows and Sanctum tokens.

### 4.8 [x] M: financial rows cascade-delete from users and orders

Done: tested up, down, up on SQLite and on MariaDB through the suite.

- **Where:** the foreign keys in the orders, products, wallet, points ledger, seller orders/payouts
  and payment transactions migrations.
- **Fix:**
  - New migration changing those keys to `restrictOnDelete`. Check each on MariaDB; drop and
    re-create the foreign key.
  - Remove `User::forceDeleteUser()` and the order force-delete helper if nothing calls them.
- Run the whole suite; tests that hard-delete users will need changing.

### 4.9 [x] M: dependencies

Done: framework 12.72, `composer audit` and `npm audit` clean.

- `composer update "symfony/*" laravel/framework` (9 advisories); `npm audit fix` (vite,
  source-map-js; dev only).
- Add `trustHosts()` in `bootstrap/app.php` with the hosts from config (iruali.mv, test.iruali.mv,
  local). Check `LocalePrefix` (`URL::forceRootUrl`) still works.
- CI: add `composer audit --no-dev` to `tests.yml`.

### 4.10 [x] M: CI

Done, except the Playwright upgrade and returning the browser job to `ubuntu-latest`: **deferred** (the pin works; an upgrade is its own change to test). `isTestHost()` now needs both the request host and APP_URL to be the test site.

- "Built assets are committed and current" must actually build. In a job with Node, run
  `npm ci && npm run build`, then `git diff --exit-code -- public/build`.
- `deploy-test-immediate.yml`: require `github.event.workflow_run.event == 'push'` and
  `head_repository.full_name == github.repository`.
- `permissions: contents: read` on `tests.yml`.
- The browser job is pinned to `ubuntu-24.04` (Playwright 1.56.1 cannot install Chromium on 26.04).
  Upgrade `@playwright/test` to a version that supports 26.04, then return the job to
  `ubuntu-latest`.
- `Http::preventStrayRequests()` in `tests/TestCase::setUp`. Fix any test this exposes.
- Tests for `POST api/deploy/test-pull` with `TestDeployTrigger` mocked: short secret → 404, wrong
  secret → 401, non-test host → 404, correct → 202. Make `isTestHost()` check the request host too.

### 4.11 [x] L: scheduler and speed

Done: both commands run in the background; header departments cached 10 minutes (dropped on category save or delete). The home page measured 13 queries before and 12 after; the product rows are **deferred**, since 12 queries does not justify a cache that has to follow islands, stock and prices.

- Long events (`sms:send-campaigns`, the queue worker) get `runInBackground()`, or move campaigns to
  queued jobs.
- Home page: cache the product rows per island, locale and view for a few minutes. Cache the nav
  categories used by the layout composer (`AppServiceProvider`). Invalidate on product and category
  save. Measure the query count before and after and state it in the PR.

---

## Batch 5: phone layout, Dhivehi, accessibility

Check every change at 390×844 in English and Dhivehi.

- [x] **5.1 M:** the "Message us" bubble covers the price in the sticky add-to-cart bar on product
  pages (`layouts/app.blade.php` bubble; `products/show.blade.php` bar). Hide the bubble on
  `products.show` below `lg`, or raise it above the bar.
  Done: hidden on product pages below `lg`.
- [x] **5.2 M:** tap targets under 44px. Add `min-h-11` and padding on text-only buttons:
  - "Buy again" / "Cancel Order" (`orders/index`);
  - "Remove" / "Clear cart" and the quantity select (`cart/index`);
  - product card "Compare", heart and "Add to cart";
  - the product page island select and "Copy link";
  - "Continue" on `/login` and `/checkout`;
  - the `/brands` letter links;
  - admin: the users role select and Save, islands "Switch off", home-page arrows.
  Done: every control listed is 44px tall (or wide), plus the home rows' "See all" and the 404/403 buttons. A Playwright pass at 390×844 finds no button, select or link button under 40px on the home, product (English and Dhivehi), brands, sign-in, policy and 404 pages.
- [x] **5.3 M:** Dhivehi `/dv/about`, `/dv/terms` and the policy pages are mostly English. Until
  translated (owner decision E), wrap untranslated blocks in `dir="ltr" lang="en"` with a one-line
  Dhivehi note.
  Done: on `/dv` the policy text sits in `dir="ltr" lang="en"` under a Dhivehi note (the wording itself waits for owner decision E).
- [x] **5.4 M:** error pages ignore the language. Apply the locale in the 404/403/500 views (detect
  the `/dv` prefix or the session). Redirect `/dv/{route that is not localised}` to the unprefixed URL.
  Done: error pages take the language from `/dv` or the session, also where no route matched. GET `/dv/{page without a Dhivehi address}` redirects to the plain address (and keeps the visitor in Dhivehi); other methods stay 404.
- [x] **5.5 L:** add `errors/403.blade.php` modelled on the 404 page.
  Done.
- [x] **5.6 L:** the first-visit island modal covers product pages reached from a link. Auto-open
  only on home and listing pages (`shopping-island.js`). The banner still offers the choice.
  Done: auto-opens on home, shop, product list, departments, search, brand and deals pages only.
- [x] **5.7 L:** `SellerPerformanceService` uses MySQL `DATE_FORMAT`, so the page returns 500 on dev
  SQLite. Group by month in PHP.
  Done.
- [x] **5.8 L:** text under 12px: `text-[11px]` → `text-xs` on product cards and badges; chart
  axis labels.
  Done on the places named: product cards, badges and the chart labels. The rest of the 10–11px text (count bubbles, tab labels, order progress steps, message times, badges) was raised to 12px afterwards, checked at 390px in English and Dhivehi.
- [x] **5.9 L:** Seller Centre with a Dhivehi session emits `lang="dv" dir="ltr"`. Output `lang="en"`
  when RTL is forced off.
  Done.
- [x] **5.10 L:** unlabelled controls. Add `aria-label`s:
  - seller products filters and bulk action;
  - stock inputs;
  - the orders status filter;
  - the import file field;
  - admin gift-cards search;
  - the rewards referral link.
  Done. The Seller Centre stock page (`/seller/stock`: its search box and every stock box) was missed at first and added in the verification pass.
- [x] **5.11 L:** the zoom dialog `<img src="">` on product pages (use `data-src` until opened).
  `/login/email` and `/register` need an `<h1>`. The web manifest is English only.
  Done: `site.dv.webmanifest` (Dhivehi names, `dir: rtl`, start `/dv/`) is linked on Dhivehi pages.

---

## Batch 6: clean-up and docs

- [x] **6.1** `.env.example`: add commented entries for every key read in `config/` that is missing:
  the SMS driver and Dhiraagu keys, VAPID, Telegram, test-deploy webhook, backup, Facebook
  page / Instagram, the BML extras, `SANCTUM_STATEFUL_DOMAINS`.
  Done (the app's own settings; framework defaults such as Redis and Memcached are left to Laravel's docs).
- [x] **6.2** Remove dead config: the unused `services.sms` provider keys (move `login_hourly_cap`
  to `config/sms.php`), `whatsapp`, `slack`, the Sentry line.
  Done for `services.sms`, `whatsapp` and `slack` (`login_hourly_cap` is now `sms.login_hourly_cap`). The Sentry lines stay: `ErrorTracker` hands alerting to Sentry when the package is installed and `SENTRY_LARAVEL_DSN` is set.
- [x] **6.3** Delete the stale `CartService::getCartTotals` / `calculateVoucherDiscount` /
  `applyVoucher`. Point `tests/Unit/CartServiceTest` at `DiscountService`.
  Done; the unused `getAppliedVoucher` and `removeVoucher` went too.
- [x] **6.4** Docs:
  - AGENTS.md: PHP `^8.4`, the current test count, the vite `base` note.
  - README: the production deploy flow.
  - `TECH_STACK.md`: the PHP version.
  - Mark `ARCHITECTURE_AND_AUDIT_GUIDE.md` and `BAKEANDGRILL_IMPORT_PLAN.md` as historical at the top.
  Done (README now describes `~/deploy-live.sh` and newest `main`).
- [x] **6.5** Tests for untested deleting or token routes:
  - `DELETE admin/finance/expenses/{expense}`;
  - `DELETE admin/newsletter/{subscriber}`;
  - admin webhooks destroy and toggle;
  - `POST messages/g/{conversation}/{token}`;
  - `GET track/order/{order}`;
  - `sms:prune`.
  Done: `UntestedRoutesTest` (the tracking page was covered for a good link; it now also checks missing, expired and altered signatures).
- [x] **6.6** composer.json: project name and description; remove the pest plugin from
  `allow-plugins`.
  Done.

---

## Owner decisions

Ask the owner before the item that needs each one. Each has a default to use if there is no answer.

- **A (2.1):** when a voucher-paid item is returned, who bears the voucher share? Default: the
  customer gets back what they paid for that item, the shop gives back its earning on that item, and
  iruali absorbs its own voucher.
- **B (2.7):** referral points awarded at delivery instead of at payment? Default: yes.
- **C (4.2):** where do off-server backups go (S3-compatible bucket, another host)? Needs credentials
  set on the server by the owner (`read -s`, never in chat).
- **D (4.7):** on account deletion, blank the customer's message text? Default: yes, keep the
  conversation row with "message removed".
- **E (5.3):** who translates the legal pages into Dhivehi? Until then they show in English with a note.

## What the audit found sound (do not rework)

- BML payments cannot be faked or replayed; amount, currency and reference are checked.
- Stock decrement, voucher use, gift cards, points, wallet debit/credit and payouts are locked
  correctly.
- Customer and seller ownership checks hold on every area sampled. All admin routes need staff +
  2FA.
- Private uploads are on the private disk. `SafeHttp` blocks internal addresses.
- No sideways scroll at 390px on about 190 page loads, English or Dhivehi.
- Timezone is `Indian/Maldives` throughout.
