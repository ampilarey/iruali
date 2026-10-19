# Iruali second test pass — 2026-10-19

Read-only second pass of GitHub `main` at `a31987ab8700d32e91b38d53503e3110c7e1ebff` (merge of PR #106, 2026-10-19). That commit is the required `a31987a` tip, and it contains `1981420` (“Fix the findings from the 2026-10-19 full test pass”). No application code was changed. The only files added on `audit/second-pass-2026-10-19` are this report and `audit-evidence-second-pass/`.

`git diff origin/main` for the commit that adds this report contains only this file and `audit-evidence-second-pass/`. `public/build/` was rebuilt for the demo with `NODE_ENV` unset and left matching `origin/main`. `database/database.sqlite` was not created. Playwright’s own server used `database/browser.sqlite`. The demo used `database/audit2.sqlite` only. `database/audit.sqlite` from the first pass was not opened.

## Environment

| Item | Value |
| --- | --- |
| Commit | `a31987ab8700d32e91b38d53503e3110c7e1ebff` |
| Working tree at checkout | Clean |
| PHP | 8.4.26 |
| Laravel | 12.69.3 |
| Node | 22.14.0 |
| Chrome | `/usr/bin/google-chrome` (Playwright 1.56.1) |
| PHPUnit database | MariaDB `iruali_test` via `phpunit.xml` (root, empty password, `127.0.0.1`) |
| Demo database | SQLite `/workspace/database/audit2.sqlite` |
| Demo URL | `http://127.0.0.1:8030` |
| Mail | `MAIL_MAILER=log` |
| Queue | `database`, `queue:work` in tmux `audit2-queue` |
| SMS | `SMS_DRIVER=http` pointed at a local fake on `127.0.0.1:8099`. Finding 5 pointed it at `127.0.0.1:9` and then restored `8099` |
| Card payments | `BML_FAKE=1`, `BML_API_KEY` empty. No call left the machine |
| Staff 2FA | `STAFF_REQUIRE_2FA=false` |
| Seeded admin | `admin@example.com` / `password` |
| Webhook catcher | Local HTTPS on `127.0.0.1:8443` (self-signed, trusted only on this machine) |

`composer install` had nothing to install. `npm ci` installed the lockfile. `npm run build` wrote `public/build/` (manifest copied by `fix-manifest.sh`). That build was not committed.

## Automated checks

| Check | Result | Wall time |
| --- | --- | --- |
| `php artisan test` | **1112 passed** (11862 assertions), Duration 102.34s, 0 failures, exit 0 | 103s |
| `./vendor/bin/pint --test` | **PASS**, 882 files, exit 0 | 7s |
| `composer analyse` (Larastan level 5, `phpstan.neon`, 490 files) | **[OK] No errors**, exit 0 | 3s |
| `npm run test:browser` | **3 passed** (14.3s), exit 0. Specs: `tests/browser/checkout.spec.ts`, `tests/browser/phone-width.spec.ts` | 15s |

Logs: `audit-evidence-second-pass/phpunit.txt`, `pint.txt`, `analyse.txt`, `playwright.txt`.

## Demo data

Created in `database/audit2.sqlite` after `migrate --seed`. Sellers are the seeded shops (Island Crafts, Hulhumalé Style, Reef Line, and the others). The customer walk used `buyer@audit2.test` (500 loyalty points, a Malé address on island 102). A second account `staff2@audit2.test` accepted a packer invitation. `unverified@audit2.test` has a mobile with `phone_verified_at` null.

| Fixture | What was stored |
| --- | --- |
| Colour tee | Product 19, size/colour variants, S/Blue stock 8 at MVR 200 and M/Red stock 6 at MVR 220 |
| Pre-order | Product 20, `preorder_enabled`, limit 2, ships 2026-12-15, note “Expected mid December” |
| Product video | Product 22, YouTube `dQw4w9WgXcQ`. The iframe is created only after the play control |
| Multi-buy | Product 25 at MVR 100, offer “2 or more, 10% off” |
| Iruali vouchers | `FIXED10` fixed MVR 10, and `PCT10` 10% (both capped at MVR 20) |
| Shop code | `SHOP20` on Island Crafts, 20% off, once per customer |
| Gift card | Redeemed into the buyer wallet (MVR 40 + MVR 100 = MVR 140). The card balance went to 0 |
| Pay later | Credit account, limit MVR 200, 30-day terms, status active |
| Ordering rules | Minimum, small-order fee, and pause were each saved, exercised, and put back. The settings table now has minimum 0, fee 0, threshold 0, pause `off`, message empty |

Seller Centre then added product 26 (`AUDIT-SELLER-TEE`, two variants, left inactive until approval) and product 27 from a valid CSV row (also inactive until approval).

## How the walks were judged

Playwright (desktop 1280 and 390px, storefront English and `/dv`) drove the UI. A few early scripts clicked the wrong control (the search button, a hidden variant `<select>`, a settings button with no `type=submit`, a confirm dialog that Playwright dismisses). Those script misses are not product failures. The result in the table is the later walk, checked against the order row. Screenshots are in `audit-evidence-second-pass/screenshots/` (82 images). Step JSON is `walk-money.json`, `walk-money2.json`, `walk-money3.json`, `ordering.json`, `seller-admin.json`, `follow.json`, `tail.json`.

Console: the seller/admin walk recorded one `404` and one `429`. The 429 is the login throttle (`throttle:5,1` on `POST /login`) after the script signed in too often. The 404 response URL was not captured. No other console errors were stored.

`/dv/login` and `/dv/checkout` are 404 by design. The island picker dialog was dismissed in the script so it would not cover the controls.

## Feature results

| Feature | Result | Evidence |
| --- | --- | --- |
| Iruali fixed voucher, shop code, multi-buy, points on then off | Pass | Order 1 below. Second use of `SHOP20` says “You have already used the shop code SHOP20 as many times as it allows.” Points line showed −20, then the remove control cleared it and the order stored 0 |
| Percent voucher | Pass | Order 2 |
| Gift card redeemed into the wallet | Pass | Card balance 0, wallet 40 → 140, screenshot `gift-card-redeemed.png` |
| Part pay with the wallet | Pass | Order 4. Wallet 140 → 0. Remainder left on the card |
| Pay later within the limit | Pass | Order 3, method `credit`, due 2026-11-18 |
| Pay later over the limit | Pass | “This order is more than your pay-later credit left (MVR 120.00).” Limit 200 minus order 3’s 80 |
| Minimum order | Pass | Cart of MVR 55 refused: “The minimum order is MVR 500.00. Add MVR 445.00 more to check out.” Then reset to 0 |
| Small-order fee | Pass | Order 5. Fee 15 added under a threshold of 100. Then reset to 0 |
| Ordering pause | Pass | Mode `all`, message “Audit pause: ordering is closed for the second pass.” Checkout returned to the cart with that message. Then mode `off` and the message cleared |
| Discount order | Pass | Shop discounts first (multi-buy, then the shop code on what is left), then the iruali voucher on what is left, then points. Screenshot `checkout-points-on.png` |
| Save for later | Pass | “Audit price drop item” on the wishlist |
| Review with a photo, and the shop’s reply | Pass | Review text “Solid for the price” is on the product. Screenshot `review-submitted.png` |
| Question and answer | Pass | Shop reply “Yes, a larger size is on the next boat.” |
| Stock alert | Pass | Stock set to 0, one open alert, restock set `notified_at` and the log mentioned stock |
| Price-drop alert | Pass | `wishlist:price-drops` printed “Price-drop alerts sent to 1 customer(s).” (`price-drop.txt`) |
| Product video | Pass | One YouTube iframe after play. `/dv` product page HTTP 200 at 1280 and 390 |
| Pre-order within the limit, and over it | Pass | Qty 1 stayed in the cart (“Only 2 left to pre-order”, “ships around 15 Dec”). Qty 3 did not raise the cart above 1 |
| Dhivehi storefront | Pass | `/dv` and `/dv/products/audit-product-video` HTTP 200 at 1280 and 390. Cart at both widths |
| Seller: product with variants | Pass | Product 26, two variants, `is_active` 0 until an admin approves it |
| Seller: bulk edit | Pass | “Set stock to 9” set each active variant to 9. `BulkProductService::setStock` then `syncStockFromVariants()`, so the product row is 18 (the sum). That is what the code does |
| Seller: CSV with errors, and a valid file | Pass | Bad price: “price must be a number between 0.01 and 999999.99.” Valid row became product 27, inactive until approval |
| Holiday mode on, then off | Pass | While on, add-to-cart is disabled and the page says “This shop is on holiday for now” plus “Audit holiday: back next week.” Forcing the button still shows that message. `holiday_mode` is 0 afterwards. The old message text is still stored and is shown only while the shop is on holiday (`sellers/_holiday_notice.blade.php`) |
| Packing slip | Pass | `GET /seller/orders/1/packing-slip` HTTP 200, “From Island Crafts”, order `ORD-VADQRKP3ID`, deliver to Audit Buyer, Audit House, Malé |
| Staff invitation accepted | Pass | Invitation for `staff2@audit2.test` as packer, accepted 2026-10-19 10:30:19, lands on `/seller/orders`. The raw token is only in the log mailer and is not copied here |
| Admin: record a refund | Pass, with finding 1 | Return 1 on order 5 approved with restock, then marked refunded, reference `AUDIT2-REFUND`, amount 95. Stock of `AUDIT-STOCK` went 2 → 3. The order had never been paid. See finding 1 |
| Import a settlement file | Pass | CSV row for order 1 matched: `bank_statement_lines` id 1, status matched, amount 159 |
| Lock and unlock a GST period | Pass | September 2026 locked with reference `AUDIT-GST-09`, then Unlock (confirm accepted) left no locked periods |
| Download an export | Pass | `GET /admin/exports/orders?from=2026-10-01&to=2026-10-19` HTTP 200, 635 bytes, contains `ORD-`. File `export-orders.csv` |
| SMS campaign to the local fake | Pass | Test message to the admin number, then `sms:send-campaigns` sent the campaign to the one marketing opt-in (the buyer). Both rows are `sent` on the local fake. Nothing else was contacted |
| Home-page block | Pass | “Audit home block” / “Second pass banner for the home page.” is on `/` at desktop and 390, including `/dv` |
| Webhook delivery | Pass | A webhook was saved and a test produced `order.created` (body length 377) on the local catcher. `webhook-hits.json` |
| Google / Facebook / Apple sign-in | Not tested (no keys) | `services.google.client_id`, Facebook, and Apple are empty. No `[data-social-login]` control is rendered |

### Orders checked by hand

Goods price is `order_items.price`. There is no subtotal column. Delivery to Malé is 25 unless a small-order fee is folded into `shipping_amount`.

| Order | What was bought | Hand total | Stored |
| --- | --- | --- | --- |
| 1 `ORD-VADQRKP3ID` | 2 × multi-buy tee at 100 | 200 − 20 multi-buy − 36 shop code (20% of 180) − 10 voucher = 134, + 25 delivery = **159**. Points were applied (20) and then removed | total 159, shop_discount 56, voucher_discount 10 (`FIXED10`), points 0, shipping 25, fee 0, wallet 0, `bml` unpaid |
| 2 `ORD-CTX69K8SXL` | Video product 90 | 90 × 10% = 9, 81 + 25 = **106** | total 106, voucher_discount 9 (`PCT10`), shipping 25 |
| 3 `ORD-AAUGNCM8QD` | Stock-alert item 55 | 55 + 25 = **80**, inside the 200 credit limit, due 30 days out | total 80, method `credit`, unpaid, `credit_due_on` 2026-11-18 |
| 4 `ORD-PR4H0SKZN5` | Tee S/Blue 200 | 200 + 25 = **225**. Wallet pays 140, card would pay 85 | total 225, wallet_amount 140, `bml` unpaid. Buyer wallet 140 → 0 |
| 5 `ORD-JNGTHFHYDG` | Stock-alert item 55, small-order rule on | 55 + 25 delivery + 15 fee = **95** | total 95, shipping_amount 40, small_order_fee 15 |

## Re-check of the first pass

| # | First-pass finding | This commit |
| --- | --- | --- |
| 1 | Admin order page crashed after “Send payment link” | **Pass.** `POST` the link (SMS ticked) on `/admin/orders/1`, reload HTTP 200, body contains “Last sent”. `payment_link_sent_at` is 2026-10-19 10:31:39. Screenshot `payment-link.png` |
| 2 | Admin → Rewards 500 on SQLite (`HAVING` on a non-aggregate) | **Pass.** `GET /admin/rewards` HTTP 200. The page lists “Audit Referrer” and does not list the buyer or the referred customer. Screenshot `admin-rewards.png` |
| 3 | Two return approvals at once could both restock and both deduct the shop | **Pass.** Two processes called `ReturnService::approve` on return 2 with restock on. One returned approved, the other returned false (`ReturnService.php:199` updates only while status is `requested`). Stock of product 24 went 14 → 15 (one unit). `seller_adjustments` for that return is one row, −72.00. `races.json` |
| 4 | Signed-out checkout offered “Text me a code instead” for an unverified mobile | **Pass** for the check that was specified. Signing in at checkout with `unverified@audit2.test` shows Password and “Email me a code instead”. The text-me button count is 0. `POST /login/phone/sms-code` comes back with “This account has no verified mobile number. Use your password, or ask for a code by email.” (`PhoneLoginController.php:145-146`). Typing the unverified number itself does not reach that step: `start()` looks the number up with `findByVerifiedPhone` (`PhoneLoginController.php:77`), finds nobody, and texts a code as if the number were new. That is what this commit does. The button guard applies once the account has been recognised |
| 5 | SMS gateway down still said “We have texted a code” | **Pass.** With `SMS_URL` on a closed port, sign-in showed “We could not send the code just now.” and did not show “We have texted”. After the URL was put back on the local fake and the server restarted, the next try showed “We have texted”. The SMS row for that number is `failed`, then `sent`. Screenshots `sms-down.png`, `sms-retry.png`. The URL is back on `127.0.0.1:8099` |

## Durability (local SQLite only)

Two PHP processes, barrier-started, `PRAGMA busy_timeout = 8000`, against `audit2.sqlite`. Results in `races.json`.

| Race | Result |
| --- | --- |
| Single-use voucher `ONCE2` (max uses 1) | One order (`ORD-JUF9JRZYZX`, user 11, voucher discount 5, total 105 = 85 − 5 + 25). `used_count` is 1. One order row carries the code. The other process got “We could not place your order. Please try again.” |
| Same gift card | One redeem (user 10, wallet +25). The other: “This gift card has already been redeemed.” Card status `redeemed`, balance 0, one `redeemed_by` |
| Last unit of a pre-order limit | New product 28, limit 1. One pre-order line (order 10, total 65 = 40 + 25). The other process got the same generic message. `is_preorder` lines for that product: 1 |
| Whole wallet (user 12, balance set to 80, two carts of a MVR 80 order) | One order (`ORD-ESXTASLO42`) paid entirely from the wallet (`wallet_amount` 80, `payment_status` paid). Balance afterwards is 0. The other checkout did not create an order. The balance did not go below zero |
| Two return approvals | See re-check 3. One success, stock +1, one shop deduction |

An earlier wallet attempt in this same database (order 8) did not send `use_wallet` and is not this result. Order 8 is an ordinary unpaid card checkout of MVR 80.

On the voucher, pre-order, and wallet races the loser died inside `OrderService::createOrderFromCart`’s `QueryException` handler (`OrderService.php:195-200`). The log for those three is SQLite `database is locked` (`SQLITE_BUSY`) on `insert into orders`, not the voucher-limit, sold-out, or wallet message. SQLite allows one writer, so those three losers never reached `Voucher`’s `lockForUpdate` (`OrderService.php:259`) or `WalletService::debit` (`WalletService.php:47`). The gift-card race and the return race did reach the application check, and both refused the second caller with a clear result. The requirement (only one success, wallet never negative) holds on this database. It does not, by itself, show how MySQL would phrase the losing checkout.

## Load

20 workers, 120 seconds, keep-alive HTTP against `http://127.0.0.1:8030`, paths `/`, `/search?q=tuna`, `/products/audit-product-video`, `/cart`. Run after the races, not during them. `load.json`.

| | First pass (`043853d`, port 8020) | This pass (`a31987a`, port 8030) |
| --- | --- | --- |
| Requests | 11,728 | 12,060 |
| Errors | 0 | 0 |
| p50 | 201 ms | 195.7 ms |
| p95 | 259 ms | 254.8 ms |
| Home p95 | 272 ms | 271.3 ms |
| Search p95 | 249 ms | 240.4 ms |
| Product p95 | 268 ms | 257.0 ms |
| Cart p95 | 234 ms | 229.2 ms |

The shape matches the first pass. Every path is a few milliseconds faster. No errors.

## New findings

### 1. Medium — a refund and a shop deduction are recorded on an order that was never paid

Steps:

1. Place an order that stays `payment_method=bml` and `payment_status=unpaid` (order 5, `ORD-JNGTHFHYDG`, total 95).
2. As the customer, request a return (reason faulty).
3. As admin, approve it with restock, then Admin → Returns → record the refund with a reference.

Expected: the shop is not charged, and the return is not marked refunded, until the customer has actually paid.

Actual: return 1 is `refunded`, `refund_amount` 95, reference `AUDIT2-REFUND`. `seller_adjustments` id 1 is −49.50 for Island Crafts (“Return on order ORD-JNGTHFHYDG”) and is not attached to a payout. `orders.payment_status` is still `unpaid` and `paid_at` is empty. Stock was put back (2 → 3), which is the part that should happen.

`ReturnController::refunded` (`app/Http/Controllers/Admin/ReturnController.php:69`) only checks the reference and `RefundApproval`. `ReturnService::markRefunded` (`app/Services/ReturnService.php:253`) only checks that the return is `approved`. The shop deduction is created in `ReturnService::approve` (`app/Services/ReturnService.php:212-227`) with no look at `payment_status`.

## Fix list

1. **Medium.** Before `ReturnService::approve` writes a seller adjustment, and before `markRefunded` stores a reference, require the order to have been paid (or split “restock and close” from “money went back”). An unpaid card order should not reduce the shop’s next payout.
2. No other product defect turned up in this pass. Re-checks 1 to 5 match the fixes in `1981420`. The SQLite `database is locked` message on a losing checkout is a property of this demo database; the gift-card and return races already return a specific refusal.

## Scope

No file under `app/`, `routes/`, `resources/`, `config/`, `database/`, `public/`, `tests/`, or `scripts/` was edited. `iruali.mv` and `test.iruali.mv` were not opened. No live SMS, email, BML charge, or off-machine webhook was sent.
