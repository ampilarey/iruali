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

**Queued mail:** every notification is queued. The scheduler starts a short
`queue:work --stop-when-empty` every minute, so with `QUEUE_CONNECTION=database` no
separate worker process is needed. Failed jobs are kept 7 days (`queue:prune-failed`) and
show up as `php artisan queue:failed`; `php artisan queue:retry all` resends them.
