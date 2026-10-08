# Production deploy (iruali.mv)

Production is **never** deployed automatically. `test.iruali.mv` deploys itself on every
merge to `main` (see `TEST_AUTO_DEPLOY.md`); production runs **tagged releases only**, deployed
by hand with one command on the server, and rolled back with one command.

```
main ──push──▶ GitHub Tests ──▶ test.iruali.mv (automatic)
  │
  └─ scripts/release.sh ──▶ tag vYYYY.MM.DD ──▶ GitHub "Release" workflow (tests + release notes)
                                  │
                                  └─ on the server: bash scripts/deploy-production.sh vYYYY.MM.DD
                                                    bash scripts/rollback-production.sh   (if needed)
```

Why no deploy from GitHub: the production server is cPanel shared hosting reached over SSH /
cPanel Terminal, and a release should go out when a person has looked at the test site, picked
a quiet moment, and is there to watch the health check and smoke test (which roll the release
back on failure). GitHub's job stops at "the tagged commit passes the suite, here are the notes".

## 1. Cut a release (on your machine)

```bash
git checkout main && git pull
bash scripts/release.sh            # tags today's date, e.g. v2026.10.01 (then .2, .3 … the same day)
bash scripts/release.sh v2026.10.05 -m "Island delivery fees"   # or name it yourself
```

The script refuses unless you are on `main`, the tree is clean and `main == origin/main`.
With `GITHUB_TOKEN` (or `GH_TOKEN`) in the environment it also asks the GitHub API whether
every check run for that commit is green and stops if not; without a token it tags anyway
and says so. It creates an **annotated** tag and pushes it. The push starts
`.github/workflows/release.yml`, which runs Pint + the full test suite on the tagged commit
and publishes a GitHub Release with generated notes. If that workflow is red, do not deploy
the tag.

## 2. Deploy the tag (on the server)

In cPanel → Terminal (or SSH), in the production checkout:

```bash
cd <app root>
git fetch --tags origin && git checkout origin/main -- scripts/     # the first time only: get the current scripts
bash scripts/deploy-production.sh v2026.10.01 [<app root>] [<docroot>]
bash scripts/deploy-production.sh --latest-tag                         # or: the newest v* tag
```

- `<app root>`: the Laravel checkout (folder with `artisan` and `.env`). Defaults to the folder
  the script lives in; `DEPLOY_ROOT` in the environment works too.
- `<docroot>`: only if the domain's document root is a **separate folder** from
  `<app root>/public` (as on test.iruali.mv). Built CSS/JS, images, favicon, manifest,
  `.htaccess` and a front-controller `index.php` pointing at the app root are synced there.
  Leave it off when the domain already points at `<app root>/public` (`DEPLOY_DOCROOT` works too).

What it does, in order:

1. Refuses anything that is not a tag (`main`, a branch, a bare commit), and refuses if the
   server checkout has local changes.
2. Appends a `status=started` line to `storage/app/deploys.log` (tag, new commit, previous
   commit and its tag, UTC time, who ran it).
3. `php artisan down`, `git fetch --tags`, **detached checkout of the tag**.
4. `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`.
5. `config:cache`, `route:cache`, `view:cache`, `event:cache`; `storage:link` if the link is
   missing; docroot sync when a docroot was given; deploy stamp; `queue:restart`.
6. `php artisan up`, then `GET APP_URL/api/health` must answer `healthy` with the new commit,
   then `php artisan iruali:smoke` must pass (home, product, category, search, cart, login,
   `/up`, health, sitemap, robots — and, when `SMOKE_USER_EMAIL` is set in `.env`, a real order
   placed, taken to BML's pay page, cancelled and its stock checked back).
7. Appends `status=ok`.

**On any failure** after the checkout it rolls back by itself: checkout of the previous commit,
`composer install`, caches, docroot sync, `up`, a `status=rolled-back` line, exit code 1.
Database migrations are **not** reversed (they only add columns and tables in this project;
check `php artisan migrate:status` if the failed release carried one).

`https://iruali.mv/api/health` then shows `"tag": "v2026.10.01"` and the commit.

## 3. Roll back (on the server)

```bash
bash scripts/rollback-production.sh                 # back to what ran before the last successful deploy
bash scripts/rollback-production.sh v2026.09.30     # or to a named tag / commit
```

It prints a warning that migrations are not reversed and lists the migration commits between
the two versions, then does the same steps as a deploy without `migrate`, ends with the health
check and smoke test, and appends a `rollback … status=ok` line to `deploys.log`. If a migration
must go, reverse it by hand first (`php artisan migrate:rollback --step=1` on the *new* code,
then roll back the code).

`storage/app/deploys.log` is the history: one line per attempt, `deploy` or `rollback`, with
`status=started|ok|rolled-back|failed`.

## 4. First deploy, step by step

One-off, in cPanel → Terminal, as the cPanel user:

1. **Clone** next to the test site: `cd ~ && git clone https://github.com/ampilarey/iruali.git iruali`
   (`<app root>` is `/home/iruali/iruali`). Point the domain's document root at
   `<app root>/public` in cPanel → Domains; if cPanel insists on its own docroot folder, keep
   that folder and pass it as `<docroot>` to every deploy.
2. **Runtime folders**: `mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/public storage/app/private bootstrap/cache`.
3. **`.env`**: `cp .env.example .env`, then set `APP_ENV=production`, `APP_DEBUG=false`,
   `APP_URL=https://iruali.mv`, the MySQL `DB_*` values (database and user made in cPanel →
   MySQL Databases), `SESSION_DRIVER=database`, `CACHE_STORE=database`,
   `QUEUE_CONNECTION=database`, the mail settings, `ALERTS_EMAIL`, and the BML keys from
   `PAYMENTS_BML.md`. Then `php artisan key:generate`.
4. **Smoke customer** (recommended): set `SMOKE_USER_EMAIL=smoke@iruali.mv` and a long
   `SMOKE_USER_PASSWORD`, then after the first deploy run `php artisan iruali:smoke --setup`.
   The account is flagged `is_smoke_test` and never gets emails, points or counted in analytics.
5. **Deploy the first tag**: `bash scripts/deploy-production.sh --latest-tag`. The first run
   migrates the empty database, caches, links storage and runs the checks; the health check
   needs the domain to already resolve to this checkout.
6. **Admin account**: never run `db:seed` on production. Set `ADMIN_EMAIL` and
   `ADMIN_PASSWORD` in `.env`, run `php artisan db:seed --class=UserSeeder`, sign in, turn on
   two-step sign-in from My Account → Security, remove the two values from `.env`, and
   `php artisan config:cache`.
7. **Scheduler cron** (cPanel → Cron Jobs, every minute):
   `cd /home/iruali/iruali && php artisan schedule:run >> /dev/null 2>&1`
8. **Check**: `php artisan iruali:ready` must end with `READY`; `php artisan iruali:smoke --place-order`
   must end with `SMOKE OK`; open `https://iruali.mv/api/health`.

Every later release is step 2 above: `bash scripts/deploy-production.sh <tag>`.

## After deploying

- `https://iruali.mv/api/health` shows `"tag"` and `"commit"` of the running release.
- Demo data (`MarketplaceDemoSeeder`) is for test only. Don't seed it on production.
- Before launch, take the demo shops and sample products off test.iruali.mv from
  **Admin → Settings → Sample data** (or `php artisan demo:remove --force` on the server);
  **Restore sample data** (`php artisan demo:restore --force`) puts them back. Keep at least one
  real product on sale afterwards: the smoke test orders the cheapest product on sale.
- `php artisan iruali:smoke --place-order` can be run at any time; it leaves one cancelled
  order per run under the smoke customer.

## What must keep running on the server

- **Scheduler cron** (set up in the first-deploy steps; unpaid-order cleanup, nightly database
  backups, queued mail, token pruning): check with `php artisan schedule:list`. Backups go to
  the disk set in `config/backup.php`; run `php artisan backup:run --only-db` once by hand and
  keep copies off the server.
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
