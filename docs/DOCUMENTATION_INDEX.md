# Iruali documentation

Everything in this folder, and what each file is for. The repository `README.md` covers
installing, running, testing, building and the release flow; `AGENTS.md` covers the dev
container and the test database.

## Start here

- **[FEATURES.md](FEATURES.md)** — one paragraph per feature area (customer, seller, admin,
  operations) with the routes and pages involved. The quickest way to learn what the app does.
- **[PROJECT_OVERVIEW.md](PROJECT_OVERVIEW.md)** — vision, target audience and goals.
- **[TECH_STACK.md](TECH_STACK.md)** — the frameworks and packages actually installed.
- **[ARCHITECTURE_AND_AUDIT_GUIDE.md](ARCHITECTURE_AND_AUDIT_GUIDE.md)** — code structure, the
  original audit findings and their status, test environment, production checklist.

## How the marketplace works

- **[MARKETPLACE.md](MARKETPLACE.md)** — per-shop order parts, commission, seller earnings,
  payouts and payout batches, returns.
- **[PAYMENTS_BML.md](PAYMENTS_BML.md)** — BML Connect setup, the payment flow, webhook,
  reconciliation; BML card is the only payment method.
- **[BML_COMPLIANCE.md](BML_COMPLIANCE.md)** — where each of BML's website requirements is met.

## Deploying and running it

- **[PRODUCTION_DEPLOY.md](PRODUCTION_DEPLOY.md)** — releases (`scripts/release.sh`), the
  production deploy from a tag (`scripts/deploy-production.sh`), rollback
  (`scripts/rollback-production.sh`), the first-deploy walk-through, and what must run on the
  server: scheduler, backups and restore drill, error alerts, staff roles and 2FA, audit log,
  admin inbox, staging refresh with the anonymiser.
- **[TEST_AUTO_DEPLOY.md](TEST_AUTO_DEPLOY.md)** — how test.iruali.mv pulls `main`
  automatically (webhook, cron fallback, Imunify360) and the smoke test that follows.
- `../tests/load/README.md` — the k6 load test and what to expect on shared hosting.

## Brand

- `brand/iruali-colours.png`, `brand/logo-options.png` — colour and logo references used by the
  Tailwind theme.

## Reference

- Schema: `database/migrations/`. Routes: `routes/web.php`, `routes/web/*.php`, `routes/api.php`
  (`php artisan route:list`). Scheduled jobs: `routes/console.php`. Commands:
  `php artisan list iruali` (`iruali:ready`, `iruali:smoke`, `iruali:anonymise`), `errors:digest`,
  `seller:low-stock-digest`, `orders:cancel-unpaid-card`, `backup:restore-drill`,
  `images:variants`.
- The REST API has no separate specification: `routes/api.php` and the
  `App\Http\Controllers\Api` controllers are the reference; the README has the Sanctum
  authentication examples.
- Status values (orders, shop parts, payments, returns, disputes, payouts) are the enums in
  `app/Enums/`, each with `label()` and `badgeClass()`.

## Getting started (short version)

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed && php artisan storage:link
npm run build && php artisan serve
```

Admin: `/admin/dashboard`, `admin@example.com` / `password` (seeded outside production; on
production create the admin with `ADMIN_EMAIL` / `ADMIN_PASSWORD` and
`php artisan db:seed --class=UserSeeder`). Change the default credentials straight away.

Contact: tech@iruali.mv (technical), business@iruali.mv (business).
