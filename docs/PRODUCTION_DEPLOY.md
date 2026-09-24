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
