# TEST Auto-Deploy

`test.iruali.mv` pulls `main` automatically. Production (`iruali.mv`) is never auto-deployed.

App root on the server: `/home/iruali/test` (document root: `/home/iruali/test/public`).

## Fast path (default)

On every push to `main`, workflow **Deploy TEST (immediate)** runs
(`.github/workflows/deploy-test-immediate.yml`):

1. `POST https://test.iruali.mv/api/deploy/test-pull` with the commit SHA
2. Server runs `scripts/pull-deploy-test.sh` in the background

Typical delay: under a minute after the push.

Cron is only a fallback if the webhook fails (e.g. DNS/SSL hiccup).

## One-time setup

**1. TEST server secret**

```bash
cd /home/iruali/test
SECRET=$(openssl rand -hex 32)
sed -i '/^TEST_DEPLOY_WEBHOOK_SECRET=/d' .env
echo "TEST_DEPLOY_WEBHOOK_SECRET=${SECRET}" >> .env
sed -i '/^TEST_DEPLOY_ALLOWED_HOSTS=/d' .env
echo "TEST_DEPLOY_ALLOWED_HOSTS=test.iruali.mv" >> .env
sed -i '/^TEST_DEPLOY_HOME=/d' .env
echo "TEST_DEPLOY_HOME=/home/iruali" >> .env
php artisan config:cache
echo "$SECRET"
```

**2. GitHub** → Settings → Environments → create **test** → add secret  
`TEST_DEPLOY_WEBHOOK_SECRET` = same value printed above

**3. Cron fallback (recommended)**

```bash
bash /home/iruali/test/scripts/install-self-update-cron-test.sh
```

**4. Switch TEST checkout to `main`**

Auto-deploy only fast-forwards `main`. After the auto-deploy code is on `main`:

```bash
cd /home/iruali/test && git fetch origin && git checkout main && git pull origin main
```

## After a push to `main`

1. Open Actions → **Deploy TEST (immediate)** — should go green within ~1 minute
2. Refresh https://test.iruali.mv/
3. Optional: `tail -f ~/self-update-test.log` on the server

## Disable webhook

Remove `TEST_DEPLOY_WEBHOOK_SECRET` from TEST `.env` and the GitHub `test` environment, then `php artisan config:cache` on the server.

## Disable cron

```bash
crontab -l | grep -v 'self-update-test.sh' | crontab -
```
