# Iruali full test pass — 2026-10-19

Read-only pass of GitHub `main` at `043853d450c4d88e37a13accbff01cc941b4176f` (merge of PR #104, 2026-10-18). No application code was changed. The only files added on `audit/full-test-2026-10-19` are this report and `audit-evidence-2026-10-19/`.

`git diff --stat origin/main` for the commit that adds this report (`ff4ced6`) is `120 files changed, 6522 insertions(+)`, and every path is this file or `audit-evidence-2026-10-19/`. `public/build/` was rebuilt for the demo with `NODE_ENV` unset and then left matching `origin/main`. `database/database.sqlite` was not created. Playwright used `database/browser.sqlite` (mtime `2026-10-19 03:10:56` after its own setup). The demo used `database/audit.sqlite` only.

## Environment

| Item | Value |
| --- | --- |
| Commit | `043853d450c4d88e37a13accbff01cc941b4176f` |
| PHP | 8.4.26 |
| Laravel | 12.69.3 |
| Node | 22.14.0 |
| Chrome | `/usr/bin/google-chrome` (Playwright 1.56.1) |
| PHPUnit database | MariaDB `iruali_test` via `phpunit.xml` (root, empty password, `127.0.0.1`) |
| Demo database | SQLite `/workspace/database/audit.sqlite` |
| Demo URL | `http://127.0.0.1:8020` |
| Mail | `MAIL_MAILER=log` |
| Queue | `database`, `queue:work` in tmux |
| SMS | `SMS_DRIVER=http` pointed at a local fake on `127.0.0.1:8099` (the `log` driver does not count as live, so phone codes stay switched off) |
| Card payments | `BML_FAKE=1`, `BML_API_KEY` empty. No call left the machine |
| Staff 2FA | `STAFF_REQUIRE_2FA=false` for the walk, then one extra boot with it `true` |
| Seeded admin | `admin@example.com` / `password` |

## Automated checks

| Check | Result | Wall time |
| --- | --- | --- |
| `php artisan test` | **1109 passed** (11839 assertions), Duration 95.50s, exit 0 | 96s |
| `./vendor/bin/pint --test` | **PASS**, 882 files, exit 0 | 11s |
| `composer analyse` (Larastan level 5, `phpstan.neon`, 490 files) | **[OK] No errors**, exit 0 | 7s |
| `npm run test:browser` | **3 passed** (13.3s), exit 0. Specs: `tests/browser/checkout.spec.ts`, `tests/browser/phone-width.spec.ts`. Server port 8001 | 14s |

Logs: `audit-evidence-2026-10-19/phpunit.txt`, `pint.txt`, `analyse.txt`, `playwright.txt`.

## Feature results

Pass / Fail below are what the product did. A few early Playwright scripts failed on the wrong column name, a download navigation, or the search button instead of the two-factor button. Those are not product failures; the later result is the one in this table. Raw JSON is kept under `audit-evidence-2026-10-19/`.

### Sign-in

| Feature | Result | Notes |
| --- | --- | --- |
| `/login` accepts a 7-digit mobile or an email | Pass | Desktop and 390px |
| Password step, and “Email me a code instead” | Pass | Present on the password step |
| “Text me a code instead” hidden on `/login` | Pass | It is shown only at checkout |
| New number: SMS code, account with no email, then name and password | Pass | “Set a password later” path also reached; password was saved on the next sign-in (`/account/setup`) |
| Forgot password | Pass | Unverified shop phones get email; a verified phone gets SMS |
| Unknown email | Pass | “No account uses that email…” |
| Staff email | Pass | Redirects to `/login/email` |
| One code per minute | Pass | “Please wait a minute…” |
| Five wrong passwords | Pass | Locked with “Too many wrong tries…” |
| Social buttons with no provider keys | Pass | Hidden. Live Google / Facebook / Apple round-trip: **Not tested** (no keys in `.env.audit`) |
| Customer 2FA | Pass | Enabled from `/profile/2fa/setup`, signed out, `/2fa` was shown, the current code was accepted |
| Cart survives sign-in | Pass | Guest lines were still there after sign-in |

### Checkout and delivery

| Feature | Result | Notes |
| --- | --- | --- |
| `GET /checkout/guest` | Pass | HTTP 404. Guest orders stay on signed `/orders/guest/{order}/{token}` links |
| Signed-out `/checkout` | Pass | Sign-in box beside the summary. “Text me a code instead” is visible |
| Checkout SMS for a verified phone | Pass | Code stored and the SMS row written |
| Checkout SMS click for an unverified phone | Fail | See finding 4. The button is shown; the click returns to the password step with no message |
| First order, island delivery | Pass | `ORD-H5FETBMG4U`: cotton sarong 280 + island fee 75 = **355**. Zone `islands`, city Feridhoo. No voucher, points, shop discount, surcharge, small-order fee, or GST on this order |
| Posted `delivery_zone=greater_male` with Feridhoo and no `island_id` | Pass | `ORD-QNGX8TTDJC`: coir rope 85 + **75** (not the Greater Malé 25) = 160. Zone stored as `islands`. `DeliveryService::zoneFor` refused the posted zone |
| Dhivehi storefront | Pass | `/dv`, `/dv/products/coir-rope-10-m`, `/dv/cart` at 1280px and 390px, Thaana rendered |
| `/dv/login` and `/dv/checkout` | Pass | HTTP 404 is intended. `SetLocale` does not put `/dv` on login, checkout, admin, or seller |

### Catalogue and money

| Feature | Result | Notes |
| --- | --- | --- |
| Home, products, brands, search, product, cart, help | Pass | HTTP 200 at desktop and 390px |
| Search “tuna”, brands (6), compare | Pass | |
| Wishlist | Pass | Snorkel set added; heart count 1 |
| Saved for later, review with a photo, Q&A, stock alert, price-drop alert | Not tested | Seeded catalogue has the pages, but these forms were not submitted in this pass |
| Product video, pre-order | Not tested | No seeded product has `video_id` or `preorder_enabled` |
| Voucher | Not tested at checkout | Admin create of fixed voucher `AUDIT10` (amount 10) succeeded. It was not applied to an order |
| Shop code, multi-buy, points spend at checkout, wallet, gift card, pay later, minimum order, small-order fee, ordering pause | Not tested | No seeded codes, offers, balances, or gift cards. Pause / fee / minimum were left at their seeded values so the walk would not change store rules |

### Payment link

Replaces the old BML Forbidden investigation. That error was not analysed.

| Feature | Result | Notes |
| --- | --- | --- |
| Admin sends a payment link | Pass | `payment_link_sent_at` set. SMS went to the local fake |
| Open the link signed out, 390px | Pass | HTTP 200 |
| Pay by card with `BML_FAKE=1` | Pass | Order 2’s place-order response redirected to `.../payments/bml/return/2?transactionId=FAKE...&fake=1`. No outbound BML call. The browser ends on the pay page waiting for the bank, because the fake transaction stays `CREATED` |
| Upload transfer slip | Pass | “Thank you. We will check your transfer and confirm your order soon.” Screenshot `screenshots/pay-slip.png` |
| Link signed to expire in the past | Pass | HTTP 403. A fresh signature returned 200. Did not wait 48 hours |
| Unpaid-card cancel while the link is live | Pass | Order aged 30 hours, link still fresh: `orders:cancel-unpaid-card` cancelled 0, status stayed `pending` |
| Same command after the link is older than 48 hours | Pass | Cancelled 1. Status `cancelled` |
| Admin order page after the link is sent | Fail | See finding 1 |

### Seller Centre

| Feature | Result | Notes |
| --- | --- | --- |
| Dashboard, products, create, stock, orders, payouts, staff | Pass | HTTP 200 as Island Crafts |
| CSV export | Pass | `GET /seller/products/export.csv` 200 `text/csv` |
| CSV import page, holiday page | Pass | HTTP 200. A file was not imported and holiday mode was not saved |
| Seller opening the customer URL `/orders/1` | Pass | HTTP 403. `OrderPolicy::view` allows the admin or the buyer |
| Create a product with variants, bulk edit, packing slip, accept a staff invite | Not tested | The pages open. Those submits were not walked |

### Admin and staff

| Feature | Result | Notes |
| --- | --- | --- |
| Admin sign-in and dashboard | Pass | |
| Menu pages other than Rewards | Pass | Exports, tax, tax report, settlements, webhooks, audit log, errors, home blocks, SMS, SMS campaigns, returns, payouts, finance, users: HTTP 200 |
| Rewards | Fail | See finding 2 |
| Settings save | Pass | Contact phone must match `^\+\d[\d ]{6,20}$`. `7770001` was rejected and nothing was saved. `+9607770001` plus the transfer account saved |
| Support / finance / catalogue | Pass | Separate customers (a seller cannot be made staff). Support: orders 200, settings 403. Finance: payouts 200, products 403. Catalogue: brands 200, orders 403 |
| `STAFF_REQUIRE_2FA=true` | Pass | Support with no authenticator: `GET /admin/dashboard` redirected to `/profile/2fa/setup` |
| Customer merge and erase | Pass | Throwaway accounts only. `merge-away@example.com` became `merged-13@iruali.invalid` with `merged_into_id=12`. `erase-audit@example.com` became `erased-14@iruali.invalid` with `erased_at` set |
| Refund recorded from the returns UI, settlement import, GST period lock, export download, SMS campaign send, home-block publish, webhook create | Not tested | The index pages open. Those writes were not submitted. SMS stayed on the local fake |

### API

| Call | With the ability | Without it |
| --- | --- | --- |
| `GET /api/v1/user` (`profile:read`) | 200 | 403 |
| `PUT /api/v1/user` (`profile:write`) | 200 | 403 |
| `GET /api/v1/cart` (`cart:write`) | 200 | 403 |
| `GET /api/v1/wishlist` (`wishlist:write`) | 200 | 403 |
| `GET /api/v1/orders` (`order:read`) | 200 | 403 |
| `POST /api/v1/checkout/remove-points` (`order:write`) | 200 | 403 |
| `POST /api/v1/products` (`products:write`) | Seller token: **422** (ability accepted, validation failed). Customer token that has the ability: **403** (`StoreProductRequest` allows a seller or an admin) | 403 `Invalid ability provided.` |
| `GET /api/health` | 200 `{"status":"healthy",...}` | |
| `GET /api/health/ready` | 200 `{"status":"ready","checks":{"database":true,"cache":true,"storage":true}}` | |

With `APP_DEBUG=true`, the 403 JSON includes the exception class and a `/workspace/vendor/...` path. With `APP_DEBUG=false` the storefront error page does not (finding 5).

## Findings

### 1. High — admin order page crashes after a payment link is sent

`resources/views/admin/orders/_payment_link.blade.php:10` calls `$order->payment_link_sent_at->diffForHumans()`. `payment_link_sent_at` is not in `Order::$casts` (`app/Models/Order.php:52`), so on both SQLite and MySQL it is a string.

Steps: as admin, open an unpaid order (HTTP 200), send the payment link, open the order again.

Expected: the order page, with “Last sent …”.

Actual: HTTP 500. `local.ERROR` at `2026-10-19 08:33:47`: `Call to a member function diffForHumans() on string` in that blade. Reproduced on orders 1 and 2. The link itself still works for the customer.

### 2. Medium — Admin → Rewards crashes on SQLite

`app/Http/Controllers/Admin/RewardsReportController.php:34` uses `having('referrals_count', '>', 0)` on a query that is not aggregated. SQLite rejects it (`SQLSTATE[HY000]: HAVING clause on a non-aggregate query`). MySQL often accepts `HAVING` on a selected alias, so this may be fine in production and still breaks the SQLite demo.

Steps: sign in as admin, open `/admin/rewards`.

Expected: the rewards report.

Actual: HTTP 500. Screenshot `audit-evidence-2026-10-19/screenshots/admin-rewards-500.png`. Every other admin menu target opened in this pass returned 200.

### 3. High — approving a return twice restocks the item twice

`ReturnService::approve` (`app/Services/ReturnService.php:182`) checks `$request->status !== 'requested'` on the model already in memory, then updates and restocks inside a transaction. The update is not conditional on the status still being `requested`, and the row is not locked first.

Steps: one return in `requested` for 1 unit of product 14 (stock 24). Two processes called `approve(..., restock: true)` at the same moment.

Expected: one approval, stock 25, the other call refused.

Actual: both returned success. Status `approved`. Stock **26**. The extra unit was put back after the count was saved (`durability.json`). Double “confirm payment” did **not** have this hole: `PaymentService::confirm` (`app/Services/PaymentService.php:228`) updates only where `payment_status != paid`. One of two parallel confirms succeeded, one earned-points ledger row was written, balance became 2.

### 4. Medium — checkout “Text me a code” does nothing when the phone is not verified

`PhoneLoginController::smsCode` (`app/Http/Controllers/Customer/PhoneLoginController.php:141`) returns to the password step, with no error, unless the account exists, the session is in checkout, and the phone is verified.

Steps: at signed-out checkout, use a seeded shop number whose `phone_verified_at` is null (for example `7771001`). The button is on the page. Click it.

Expected: a code, or a message that the number must be verified first.

Actual: back on the password step, no message. A verified number (`+9607911103`) does get the code. That path passed.

### 5. Medium — a dead SMS gateway still says the code was texted

`PhoneLoginController::sendCode` (`app/Http/Controllers/Customer/PhoneLoginController.php:332`) notifies and then always flashes “We have texted a 6-digit code…”. `HttpSms::send` turns a connection error into `SmsResult::failed` and does not throw (`app/Services/Sms/HttpSms.php:46`), so the `catch` on line 334 never runs.

Steps: restart the demo with `SMS_URL=http://127.0.0.1:9/sms` (nothing listening). On `/login`, submit a new mobile `7911777`.

Expected: a clean failure, not a 500, and not a claim that the text was sent.

Actual: HTTP 302 back to `/login`, HTTP 200, the success sentence about a 6-digit code. `sms_messages.status` for that number is `failed` with `cURL error 7: Failed to connect to 127.0.0.1 port 9`. The response is not a 500.

### 6. Low — debug error pages leak SQL and filesystem paths

With `APP_DEBUG=true`, `GET /admin/rewards` returned a 1,004,196-byte page containing the `HAVING` SQL and `/workspace/` paths. After growing `storage/logs/laravel.log` by about 9MB (from 2.5MB to 12MB), the app still answered. With `APP_DEBUG=false` the same URL returned a 1,285-byte “Something went wrong” page with neither the SQL nor the path. Production needs `APP_DEBUG=false`. This was not left that way: the demo was put back to `APP_DEBUG=true`.

## Durability

All of this used `database/audit.sqlite` or a second scratch file. Nothing targeted `iruali_test` except PHPUnit.

| Drill | Result |
| --- | --- |
| Two spends of the same 100 points (`PointsService::record` −100, type `redeemed`) | Pass. One `ok`, one “You no longer have enough points…”. Balance 100 → 0 |
| Two checkouts, product stock set to 1 | One order placed (85 + island fee 75). The other failed with SQLite `database is locked` on the order insert, before `takeStock`, and the customer message was the generic “We could not place your order.” Stock ended at 0, not below. Stock was then restored to 24 (25 seeded, minus the one real order) |
| Two direct `takeStock` calls on a stock of 1 | Pass. One returned 1, one returned 0. The artificial unit was put back |
| Two “confirm payment” calls on the transfer order | Pass. One `ok`, one `fail`. `payment_status=paid`. Earned-points rows 0 → 1 |
| Two return approvals with restock | Fail. Finding 3 |
| 20 clients, 120 seconds, home / search / product / cart | 11,728 requests, **0 errors**. Overall p50 201ms, p95 259ms. Per path p95: home 272ms, search 249ms, product 268ms, cart 234ms. `load.json` |
| Stop `queue:work`, queue `ReadyCheckTestMail`, start the worker | Pass. `jobs` 0 → 1 → 0. The log shows `To: audit-queue@example.com`, subject `iruali test mail` |
| Closed SMS port | Finding 5. Not a 500 |
| Grown log, then a page | Finding 6. No crash |
| Each schedule entry in `routes/console.php` | 34 artisan commands exit 0, except `errors:digest` exit 1: “No address to send the digest to” (`ALERTS_EMAIL` unset). Closures `expire-gift-cards`, `prune-guest-carts` (0 deleted), `heartbeat`, `prune-funnel-events`, and the `QueueHeartbeat` job all ran. `rewards:check-balances` reported “Fixed 1 points balance(s) to match the ledger” (the nightly repair; the audit row is the detail) |
| `backup:run --only-db` then `backup:restore-drill --no-mail` | **Passed.** Newest zip `Iruali/iruali-backup-2026-10-19-08-41-49.zip` (53KB). 1,132 SQL statements restored into `/workspace/database/audit-drill.sqlite`. Counts matched: orders 3/3, users 17/17, products 18/18. 102 tables. Drill tables were dropped again. A second SQLite file works; MariaDB `iruali_drill` was not needed |

## What to fix first

1. Cast `payment_link_sent_at` as `datetime` (finding 1), or format it as a string in the blade. Admins cannot reopen an order once a link has been sent.
2. Make return approval conditional and locked: update only `where status = requested`, and restock only when that update changes a row (finding 3).
3. On SQLite, rewrite the rewards referrer query so the filter is a `where` on the count, not `HAVING` (finding 2).
4. If the SMS driver returns `failed`, show “We could not send a code”, not the success sentence (finding 5). Same for the silent return in `smsCode` when the phone is not verified (finding 4).
5. Keep `APP_DEBUG=false` on any shared host (finding 6). No code change required for that.

## Evidence

Scripts and logs live in `audit-evidence-2026-10-19/` (`walk*.mjs`, `roles.mjs`, `rest.mjs`, `rest2.mjs`, `durability.php`, `load.mjs`, plus the JSON and text logs). Screenshots (77) are in `audit-evidence-2026-10-19/screenshots/`, including the pay link, the slip, rewards 500, role denials, 2FA, and the English and Dhivehi storefront at both widths.
