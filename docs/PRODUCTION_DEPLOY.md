# Production deploy (iruali.mv)

Production is **never** deployed automatically. `test.iruali.mv` deploys itself on every
merge to `main` (see `TEST_AUTO_DEPLOY.md`); check a change there first, then release it
to production by hand with one command in cPanel → Terminal:

```bash
cd <app root> && git fetch origin main && git checkout origin/main -- scripts/deploy-production.sh scripts/write-deploy-stamp.sh
bash <app root>/scripts/deploy-production.sh <app root> [<docroot>]
```

- `<app root>`: the Laravel checkout for iruali.mv (the folder with `artisan` and `.env`).
- `<docroot>`: only if the domain's document root is a **separate folder** from
  `<app root>/public` (as on test.iruali.mv). Built CSS/JS, images, favicon, manifest,
  `.htaccess` and a front-controller `index.php` pointing at the app root are synced there.
  Leave it off when the domain already points at `<app root>/public`.

The first line fetches the deploy scripts themselves, so the first run uses the current
version even when the server's checkout is old.

## What the script does

1. Maintenance mode (`php artisan down`)
2. `git fetch` + fast-forward `main` only (refuses if the server has local commits)
3. `composer install --no-dev --optimize-autoloader`
4. `php artisan migrate --force`, `storage:link`, `config:cache`, clears routes/views/cache
5. Syncs public files to the docroot (when given)
6. Writes the deploy stamp, `php artisan up`
7. Checks `APP_URL/api/health` and that it reports the new commit

On any failure it brings the site back up and, if the code already moved, prints the
exact `git reset --hard <previous commit>` command to roll back. Database migrations are
not rolled back automatically.

## After deploying

- `https://iruali.mv/api/health` shows `"commit"` = the latest `main` commit.
- Demo data (`MarketplaceDemoSeeder`) is for test only. Don't seed it on production.

## First-time setup and what must run on the server

- **Scheduler cron** (required; unpaid-order cleanup, nightly database backups, token pruning):
  `* * * * * cd /home/iruali/<app> && php artisan schedule:run >> /dev/null 2>&1`
  Check with `php artisan schedule:list`. Backups go to the disk set in `config/backup.php`; run `php artisan backup:run --only-db` once by hand and keep copies off the server.
- **Admin account:** never run `db:seed` on production. Set `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env`, then `php artisan db:seed --class=UserSeeder`, sign in, turn on two-step sign-in from My Account → Security, and remove the two values from `.env`.
- **Demo accounts:** on any non-local server the migration `rotate_demo_account_passwords` gives every `*@example.com` shop a random password. `admin@example.com` is left alone so you are not locked out: change its password from My Account, or delete it once a real admin exists.
- **Uploads:** product photos live in `storage/app/public`. The deploy scripts link `<docroot>/storage` to it; if the docroot is `<app>/public`, `php artisan storage:link` does the same.
- **Runtime folders** on a fresh clone: `mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/public storage/app/private bootstrap/cache`.
- **Timezone** is `Indian/Maldives` by default (`APP_TIMEZONE`).

## Is it ready to run unattended? `php artisan iruali:ready`

Run it after every deploy and whenever something feels off. It prints one row per check
(scheduler heartbeat, cache, sessions, queue worker, mail, BML Connect, last backup,
uploads, APP_* settings, timezone, error alerts) with PASS / WARN / FAIL and a one-line fix,
ends with `READY` or `NOT READY (n failures)` and exits 1 on failure, so it can go in a
deploy script.

- `--offline` skips the network call to the BML API.
- `--send-test-mail=you@iruali.mv` also sends a real email through the configured mailer.

The admin dashboard shows the same checks (offline, refreshed every 5 minutes) in the
**System status** panel, so a red row is visible without a terminal. The scheduler check
works from a heartbeat the cron writes every minute; if it says the scheduler has not run,
nothing scheduled (backups, queued mail, unpaid-order cleanup) is running either.

## Staff roles, 2FA and the audit log

- Roles: **admin** (everything), **support** (dashboard, inbox, orders, returns, moderation,
  users read-only) and **finance** (dashboard, inbox, payouts, refunds, analytics, errors
  read-only, audit log). What each may open is the route list in `config/staff.php`; the
  `StaffAccess` middleware on the admin group enforces it and the admin nav hides the rest.
  Admins give someone support/finance from **Admin → Users**.
- Every staff member must have **two-step sign-in** on (`STAFF_REQUIRE_2FA`, default true);
  without it, `/admin` redirects to My Account → Security. Set `STAFF_REQUIRE_2FA=false`
  only on a development machine.
- **Audit log** at **Admin → Audit log** (`/admin/audit`, admin + finance): order status
  changes and cancellations, refunds flagged/recorded, payouts, seller approve/reject/suspend,
  product approvals, settings and legal pages saved, vouchers, role changes and staff
  sign-ins, with who, when, IP and the changes. Code records one with
  `Audit::record('action', $model, [...])`.

## Refreshing the staging site

`test.iruali.mv` should run on a recent copy of the production database **with the
personal data replaced**. `php artisan iruali:anonymise --force` gives every non-staff user
a made-up Maldivian name, `user<id>@example.test`, phone `7000000+id`, a random password,
and clears their tokens, 2FA secrets, addresses and bank account; order delivery
addresses/phones, return request notes, newsletter emails and OTPs are rewritten or
emptied. Staff accounts (admin, support, finance) are kept so the team can sign in.
Without `--force` it only prints the counts; on an `APP_ENV=production` install it refuses
unless `--i-know-this-is-production` is given.

1. On production: `cd <prod app> && php artisan backup:run --only-db` (or
   `mysqldump -u <user> -p <prod_db> | gzip > /home/iruali/prod.sql.gz`).
2. On staging: `gunzip < /home/iruali/prod.sql.gz | mysql -u <user> -p <test_db>`
3. `cd <test app> && php artisan migrate --force && php artisan iruali:anonymise --force`
4. `php artisan iruali:ready --offline` to confirm the copy works.

Weekly cron on the server (Sunday 03:30, after the nightly backup), with both apps on the
same host:

```
30 3 * * 0 cd /home/iruali/iruali && mysqldump -u iruali -p"$DB_PASS" iruali_prod | mysql -u iruali -p"$DB_PASS" iruali_test && cd /home/iruali/test && php artisan migrate --force && php artisan iruali:anonymise --force >> storage/logs/anonymise.log 2>&1
```

Never run the anonymiser against the production database, and never copy a production
database anywhere without running it.

## Admin inbox

**Admin → Inbox** (`/admin/inbox`, badge in the admin nav) lists what is waiting for a
person: shops awaiting approval, products pending review, refunds due, open returns, BML
payments that failed in the last 24 h, best sellers almost out of stock, unresolved errors.
Each row opens the page that deals with it; support and finance see only the rows they can
open. New features add a row with `AdminInbox::register('key', fn () => [...])` from a
service provider (see `app/Support/AdminInbox.php`).

## Backups: off the server, and tested

- Nightly `backup:run --only-db` (02:00) writes to every disk in `BACKUP_DISKS`
  (comma list; default `local` = `storage/app/private`). Set `BACKUP_DISKS=local,s3` and the
  `AWS_*` keys in `.env` to also keep a copy off-site. The `s3` disk works with AWS S3,
  **Backblaze B2** (`AWS_ENDPOINT=https://s3.<region>.backblazeb2.com`) and **MinIO**
  (`AWS_USE_PATH_STYLE_ENDPOINT=true`). Backup failures are mailed to `ALERTS_EMAIL`.
- `php artisan iruali:ready` fails when the newest backup on the first disk is older than 48 h.
- **Restore drill**, 1st of every month at 04:00 (`php artisan backup:restore-drill`): the
  newest backup is downloaded, unzipped, its SQL dump restored into `DB_DRILL_DATABASE`
  (never the live database; created if missing), the orders/users/products row counts are
  compared with the live database, the drill tables are dropped and a pass/fail report is
  mailed to `ALERTS_EMAIL`. Set `DB_DRILL_DATABASE=iruali_drill` in `.env` and give the DB
  user `CREATE` on it (in cPanel: create the database and assign the same user).
  Run it by hand after changing anything about backups.

## Error alerts (no external service needed)

Every reported exception is counted in the `error_events` table, one row per place in the
code (class + file + line), and listed at **Admin → Errors** (`/admin/errors`) with a
"Mark resolved" button; a resolved error reopens by itself if it happens again. 404s,
419s, validation and sign-in errors are not tracked.

- **Payment errors** (anything thrown from `BmlConnect`, `PaymentService` or the payment
  controller) email `ALERTS_EMAIL` immediately, at most once an hour per error. With no
  `ALERTS_EMAIL`, the contact email from Admin → Settings is used.
- **Daily digest** at 07:00 Maldives time (`php artisan errors:digest`): new errors from the
  last 24 hours and the ten most frequent, sent only when there is something to report.
- **Sentry (optional):** `composer require sentry/sentry-laravel` and set
  `SENTRY_LARAVEL_DSN` in `.env`. Sentry then does the alerting; the local table and admin
  page keep working, only the immediate payment alert email is left to Sentry.

**Queued mail:** every notification is queued. The scheduler starts a short
`queue:work --stop-when-empty` every minute, so with `QUEUE_CONNECTION=database` no
separate worker process is needed. Failed jobs are kept 7 days (`queue:prune-failed`) and
show up as `php artisan queue:failed`; `php artisan queue:retry all` resends them.
