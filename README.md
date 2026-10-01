# iruali — multi-vendor marketplace for the Maldives

Laravel 12 marketplace where island shops sell to the whole country: one checkout, one card
payment through BML Connect, one order split into a part per shop, commission and payouts for
the shops, delivery by zone (Greater Malé / islands). English and Dhivehi (right-to-left)
throughout. Runs on cPanel shared hosting; `test.iruali.mv` deploys itself from `main`,
`iruali.mv` runs tagged releases deployed by hand.

## What it does

- **Customers:** catalogue with departments, brands, shops, deals, search with suggestions,
  product variants, reviews with photos, Q&A, stock alerts, wishlist, compare, saved items;
  cart with vouchers and loyalty points; checkout with an address book and island picker,
  guest checkout, card payment through BML Connect; order tracking with a progress bar per
  shop, messages to the shop, returns, disputes, cancel, buy again, receipts; email and SMS
  notifications; accounts with OTP verification and two-step sign-in; referral rewards.
- **Sellers:** application and approval, shop profile, products with photos (WebP variants),
  variants, bulk edit and CSV import/export, stock page, order fulfilment with tracking
  details, customer messages, returns, reviews with replies, earnings and payouts, analytics,
  performance (late shipping), low-stock digest, bank account for payouts.
- **Admin (admin / support / finance roles, 2FA required):** dashboard with system status,
  inbox of everything waiting, sellers, products, users, orders and payments (BML re-check,
  refunds), returns, disputes, messages, payouts and bank-file payout batches, vouchers,
  reviews and questions moderation, settings and legal pages, newsletter, analytics, error
  tracker, audit log, SMS log.
- **Operations:** scripted deploy with health check, smoke test and automatic rollback;
  readiness check; nightly backups with a monthly restore drill; error alerts and digests;
  staging-data anonymiser; k6 load test; Sanctum REST API under `/api/v1`.

`docs/FEATURES.md` has a paragraph per area with the routes and pages; `docs/` has the rest
(index in `docs/DOCUMENTATION_INDEX.md`).

## Requirements

- **PHP 8.4** (`composer.json` requires `^8.4`; production and CI run 8.4) with the usual
  extensions: mbstring, intl, gd, zip, pdo_mysql, pdo_sqlite, bcmath
- Composer 2
- **Node.js 22** and npm (Vite 6, Tailwind 4; only needed to build assets or run the browser test)
- **MySQL/MariaDB** for the test suite and for production; SQLite is enough for local development
- cPanel with LiteSpeed and the scheduler cron for production (see `docs/PRODUCTION_DEPLOY.md`)

## Run it locally

```bash
git clone https://github.com/ampilarey/iruali.git && cd iruali
composer install
npm install
cp .env.example .env            # SQLite, local mail log, BML off
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed       # admin@example.com / password, demo shops, products, islands
php artisan storage:link
npm run dev                      # or: npm run build (assets are committed in public/build)
php artisan serve                # http://127.0.0.1:8000
```

- Admin: `http://127.0.0.1:8000/admin/dashboard` as `admin@example.com` / `password`
  (`STAFF_REQUIRE_2FA=false` in `.env` skips the 2FA requirement on a dev machine).
- Card payment needs BML Connect keys (`docs/PAYMENTS_BML.md`). To walk through checkout
  without BML set `BML_FAKE=1` in `.env`: the payment page is skipped and the order stays
  unpaid, and it is ignored when `APP_ENV=production`.
- `composer dev` runs the server, queue listener, log tail and Vite together.

## Tests

```bash
php artisan test                                           # PHPUnit, all Feature + Unit tests
./vendor/bin/pint --test                                   # code style (CI fails on violations)
npm run test:browser                                       # Playwright checkout test (see below)
composer analyse                                           # Larastan level 5, after: composer require --dev larastan/larastan
```

- `phpunit.xml` runs against a MariaDB/MySQL database named `iruali_test` (user `root`, no
  password, host `127.0.0.1`), not the SQLite dev database; create it first. Another database:
  `DB_DATABASE=other php artisan test`.
- The **browser test** (`tests/browser/`) boots its own SQLite database (`database/browser.sqlite`,
  migrated and seeded, BML faked) and PHP's built-in server, signs in, searches, adds to cart,
  checks out to the "Pay now" page and finds the order under My Orders. First time:
  `npx playwright install --with-deps chromium`. With browsers already installed elsewhere, set
  `PLAYWRIGHT_BROWSERS_PATH`, or `PLAYWRIGHT_CHROMIUM_EXECUTABLE` to a Chromium binary.
- **Load test:** `tests/load/README.md` (k6, against the test site only).
- CI (`.github/workflows/tests.yml`) runs Pint, PHPUnit on MariaDB, the browser test, checks
  that `public/build` is current, and Larastan as an advisory job.

## Build

```bash
npm run build      # vite build, then fix-manifest.sh copies .vite/manifest.json → public/build/manifest.json
```

`public/build/` is committed (cPanel deploys from git and has no Node), so after any change under
`resources/css` or `resources/js` run the build and commit the result; CI fails when it is stale.
`vite.config.js` uses `base: '/'` (the domains point at the app root).

## Deploy and release

- **Test site:** every push to `main` that passes CI is pulled by `test.iruali.mv` within a
  minute (webhook, cron fallback) and smoke-tested. `docs/TEST_AUTO_DEPLOY.md`.
- **Release:** `bash scripts/release.sh` on `main` makes an annotated `vYYYY.MM.DD` tag (after
  checking CI is green when `GITHUB_TOKEN` is set) and pushes it; GitHub runs the suite on the
  tag and publishes a Release with generated notes (`.github/workflows/release.yml`).
- **Production:** on the server, `bash scripts/deploy-production.sh v2026.10.01` (or
  `--latest-tag`): maintenance mode, detached checkout of the tag, composer, migrations, caches,
  health check and `php artisan iruali:smoke`; any failure rolls back automatically.
  `bash scripts/rollback-production.sh [tag]` rolls back on demand. Every attempt is in
  `storage/app/deploys.log`. First-deploy walk-through and everything that must run on the
  server: `docs/PRODUCTION_DEPLOY.md`.
- After deploying: `php artisan iruali:ready` (`READY`), `/api/health` shows the tag.

## Environment variables that matter

```env
APP_ENV=production  APP_DEBUG=false  APP_URL=https://iruali.mv  APP_TIMEZONE=Indian/Maldives
DB_CONNECTION=mysql  DB_HOST=localhost  DB_DATABASE=…  DB_USERNAME=…  DB_PASSWORD=…
SESSION_DRIVER=database  CACHE_STORE=database  QUEUE_CONNECTION=database
MAIL_MAILER=smtp …  ALERTS_EMAIL=ops@iruali.mv
BML_API_KEY=…  BML_ENVIRONMENT=production  BML_WEBHOOK_SECRET=…     # docs/PAYMENTS_BML.md
BML_FAKE=0                                                           # 1 only on dev/test
SMOKE_USER_EMAIL=smoke@iruali.mv  SMOKE_USER_PASSWORD=…              # php artisan iruali:smoke --setup
STAFF_REQUIRE_2FA=true
BACKUP_DISKS=local,s3  AWS_*=…                                       # off-site backups
TEST_DEPLOY_WEBHOOK_SECRET=…                                         # test site only
```

`.env.example` lists all of them with comments.

## Troubleshooting

- **500 / blank page:** `storage/logs/laravel.log`; `php artisan iruali:ready` names the
  missing piece (cache dir, APP_KEY, mail, queue…). Admin → Errors shows reported exceptions.
- **`ViteManifestNotFoundException`:** `public/build/manifest.json` is missing — run
  `npm run build` (locally) and commit, or `npm run dev` while developing.
- **Card payment hidden at checkout:** `BML_API_KEY` is empty (or `BML_FAKE` is not set on a
  dev machine). Run `php artisan iruali:ready` to see the BML row.
- **Database connection error in tests:** the `iruali_test` MariaDB database must exist and
  the server must be running (`sudo mysqld_safe &` where there is no systemd).
- **Permissions on the server:** `chmod -R 775 storage bootstrap/cache`.

## API (Sanctum)

Sign in at `POST /api/v1/login` for a Bearer token, send it as `Authorization: Bearer …` to
the protected endpoints, `POST /api/v1/logout` to revoke it. Tokens carry abilities
(`order:read`, `cart:write`, `wishlist:write`, `profile:read`, `profile:write`) checked with
`$request->user()->tokenCan('order:read')`, and expire after 30 days (`SANCTUM_EXPIRATION`,
minutes); `sanctum:prune-expired` runs daily from the scheduler.

```bash
curl -X POST https://iruali.mv/api/v1/login -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password"}'
# {"success":true,"data":{"user":{…},"token":"1|…","token_type":"Bearer","abilities":[…]}}

curl https://iruali.mv/api/v1/user -H "Authorization: Bearer 1|…"
```

Endpoints: products (`/api/v1/products`, `/featured`, `/on-sale`, `/{id}`), categories, search,
cart, wishlist, orders (`/api/v1/orders`, `/{id}`, `/{id}/track`), checkout points, profile and
password. `routes/api.php` is the reference.

## License

Proprietary software developed for the iruali marketplace.
