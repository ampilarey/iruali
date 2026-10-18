# Servers: how iruali is actually set up and deployed

Read this before touching deploys, `.env` or the server. It describes the real setup as of
October 2026; `PRODUCTION_DEPLOY.md` and `TEST_AUTO_DEPLOY.md` have the background.

## The two sites

Both run on one cPanel account (`iruali@sg-s2`, Singapore), reached through cPanel → Terminal.

| | test.iruali.mv | iruali.mv (production) |
| --- | --- | --- |
| App folder | `/home/iruali/test` | `/home/iruali/iruali` |
| Web root (docroot) | `/home/iruali/home/iruali/test.iruali.mv` | `/home/iruali/public_html` |
| Database | its own (see its `.env`) | `iruali_prod` (user `iruali_iruali`) |
| Updates | **automatically** on every merge to `main` that passes CI | **by hand**: `bash ~/deploy-live.sh` |
| Data | demo shops and products (Admin → Sample data) | real data only, never demo seeders |

The old pre-launch database `iruali_iruali` is kept as a backup and is not used.

## Deploying production

```bash
bash ~/deploy-live.sh
```

That is the whole routine. `~/deploy-live.sh` (made once, content in `PRODUCTION_DEPLOY.md`) fetches
`main`, copies the newest `scripts/deploy-*.sh` to `~/deploy-tools/`, and runs
`deploy-production.sh main ~/iruali ~/public_html`:

- refuses while GitHub's checks for the newest `main` commit are failing or still running
  (wait ~5 minutes after a merge);
- maintenance page → checkout → `composer install --no-dev` → `migrate --force` → caches →
  copies built assets and a front-controller `index.php` into `public_html` → `up`;
- `/api/health` must answer healthy (and, with `HEALTH_DETAIL_TOKEN` in `.env`, report the new
  commit), then `php artisan iruali:smoke` must pass;
- any failure rolls the code back by itself (migrations are not reversed);
- every run is a line in `~/iruali/storage/app/deploys.log`.

Roll back by hand: `bash ~/deploy-tools/rollback-production.sh`.

**No release tags for now.** Production follows `main`. `deploy-production.sh <tag>` still works if
tags come back. Claude Code sessions cannot push tags; the owner would create them on GitHub.

## Production `.env` (values that matter; secrets live only on the server)

| Setting | Value |
| --- | --- |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://iruali.mv` |
| `DB_DATABASE` | `iruali_prod` |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | `database` |
| Mail | SMTP `mail.iruali.mv`, port 465, `MAIL_SCHEME=smtps`, user and sender `admin@iruali.mv` |
| `ALERTS_EMAIL` | `admin@iruali.mv` |
| `HEALTH_DETAIL_TOKEN` | random; lets the deploy check the commit in `/api/health` |
| SMS | Dhiraagu, same API and login as Akuru (`PRODUCTION_DEPLOY.md` → SMS); set with `smssetup` |
| BML | **not set yet**: card payment is off until the BML keys are added. Bank transfer and cash on delivery can be switched on meanwhile in Admin → Settings → Payment methods |

Admin: `admin@iruali.mv`, two-step sign-in required for all staff unless switched off in Admin → Settings ("Staff must use two-step sign-in"). Locked out of /admin by it: `php artisan staff:two-factor off` on the server (`on` puts it back).

Scheduler (crontab of `iruali`):
```
* * * * * cd /home/iruali/iruali && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * /home/iruali/test/scripts/self-update-test.sh >> /home/iruali/self-update-test.log 2>&1
```

Health: `php artisan iruali:ready` (expects only BML to fail until the keys exist). For an uptime
monitor, `/api/health/ready` answers 200, or 503 when the database, cache or storage fails
(`/api/health` is unchanged for the deploy check). `ops:watch` (every 10 minutes) alerts when queued
jobs fail or the queue worker stops.

Optional `.env` for social posting (Admin → Social posts): `FACEBOOK_PAGE_ID`, `FACEBOOK_PAGE_TOKEN`
(a long-lived page token) and `INSTAGRAM_ACCOUNT_ID`. Nothing posts without them and an approval.

Staff from the server: `php artisan staff:list`, `staff:create email --role=…` (the password is typed
hidden, or left empty for "Forgot password"), `staff:unlock email` after too many wrong tries.

Copy live data to test: restore a production backup on test, then `php artisan iruali:anonymise`
there before anyone uses it (it replaces customers' names, emails, phones and addresses).

## Rules learned the hard way

- **Never run `db:seed` on production.** Reference data was loaded once with
  `PermissionSeeder RoleSeeder CategorySeeder IslandSeeder`; demo seeders never.
- **Edit `.env` with a helper, not by appending.** The file may lack a final newline, so a plain
  `echo KEY=value >> .env` glues onto the last line. Use (escapes `& | \`, adds the newline):
  ```bash
  setenv() { v=$(printf '%s' "$2" | sed 's/[&|\\]/\\&/g'); if grep -q "^$1=" .env; then sed -i "s|^$1=.*|$1=$v|" .env; else [ -n "$(tail -c1 .env)" ] && echo >> .env; printf '%s=%s\n' "$1" "$2" >> .env; fi; }
  ```
  then `php artisan config:cache`.
- **Commands that ask questions (`read`) must be wrapped in a function** and the function called
  by typing its name: in a pasted block, `read` swallows the next pasted line as its answer.
- **Never put passwords or keys in commands you hand out, or ask for them in chat.** Prompt with
  `read -s` on the server instead. Never leave placeholders like `CHOOSE-A-STRONG-PASSWORD` in a
  command that will be run as-is.
- `config:cache` is on: after changing `.env`, run `php artisan config:cache`. `env()` outside
  `config/` returns null then; values given on the command line (`KEY=x php artisan …`) still work.
- `bootstrap/app.php`'s middleware callback runs **before** `.env` is loaded on a web request: read
  settings through `config()` in a service provider, never `env()` there (see `TRUSTED_PROXIES`).
- `/api/health` hides the commit unless `X-Health-Token` matches `HEALTH_DETAIL_TOKEN`; the deploy
  script sends it. Any change to `/api/health` or the smoke test must keep `scripts/deploy-lib.sh`
  `verify_site` working: CI does not run the deploy scripts.
- The old `~/public_html/robots.txt` and `debug-live.php` were removed; files left in `public_html`
  win over the app's routes, so don't add static files there.
