# Load test (k6)

`k6-browse-and-checkout.js` puts 500 virtual users on the site for 5 minutes: 450 browse
(home → a category → a product → a search, with think time), 50 sign in as the smoke customer,
add a product to the cart and open the checkout page, then clear the cart. Nothing is ordered
and nothing is paid. Thresholds: **p95 of every request under 800 ms** and **error rate under
1 %** (the checkout page alone may take 1.5 s); k6 exits non-zero when either is missed.

Run it against **test.iruali.mv** (or a local `php artisan serve`), never against production.

## Running it

1. Install k6 on your machine: https://grafana.com/docs/k6/latest/set-up/install-k6/
   (`brew install k6`, `sudo apt install k6`, or the Windows installer). Nothing is installed
   on the server.
2. On the test site, make sure the smoke customer exists: `SMOKE_USER_EMAIL` and
   `SMOKE_USER_PASSWORD` in its `.env`, then `php artisan iruali:smoke --setup` once.
   Without these the 50 shoppers only browse the product and cart pages.
3. Run:

   ```bash
   k6 run -e BASE_URL=https://test.iruali.mv \
          -e SMOKE_USER_EMAIL=smoke@iruali.mv -e SMOKE_USER_PASSWORD='the password' \
          tests/load/k6-browse-and-checkout.js
   ```

   Knobs: `-e DURATION=2m` (steady-state length; 1 min ramp up and 30 s ramp down are added),
   `-e BROWSERS=100 -e SHOPPERS=10` for a gentler first run. The full result is written to
   `tests/load/last-run.json` (ignored by git); the terminal shows the k6 summary and one
   `RESULT:` line with requests, p95 and error rate.

4. Watch the server while it runs: cPanel → **Resource Usage** (CPU, physical memory, entry
   processes, I/O) and `tail -f storage/logs/laravel.log` for errors. Imunify360 may start
   blocking the k6 IP as a bot after a few hundred requests a minute; whitelist your IP first
   (cPanel → Imunify360 → White List) or you will see a wall of 403s that are not the app's fault.

## What to expect on cPanel shared hosting

Shared hosting gives one account a small slice of a server (typically 1–2 CPU cores, 1–2 GB of
RAM, 20–40 "entry processes" = concurrent PHP requests, LiteSpeed in front). Rough numbers for
this app on such a plan, uncached:

| Load | Typical result |
| --- | --- |
| 50 VUs browsing | p95 300–600 ms, 0 % errors |
| 200 VUs browsing | p95 600–1200 ms, 0–1 % errors (first 503s when entry processes are used up) |
| 500 VUs browsing + 50 shoppers | p95 1.5–4 s, 2–15 % errors (503 / 508 "Resource Limit Is Reached"); the thresholds fail |

So the 500-VU target is **not** expected to pass on a basic shared plan out of the box; it is
there to show where the ceiling is and whether a change moved it. A realistic launch-day load
for a Maldives marketplace is tens of concurrent people, not hundreds; a plan that passes the
200-VU level with p95 under 800 ms is comfortable. The checkout path is heavier than browsing
(session, cart, delivery quotes, voucher checks), so the 50 shoppers are the first to see slow
pages.

If the 503/508 errors appear at low VU counts, the account's **entry processes** or **memory**
limit is the ceiling, not PHP: the only fixes are a bigger plan / VPS or reducing the work per
request (below).

## The three usual fixes

1. **OPcache.** Without it PHP recompiles every file on every request. In cPanel → **Select PHP
   Version → Extensions** enable `opcache`, then under **Options** set
   `opcache.memory_consumption=128`, `opcache.max_accelerated_files=20000`,
   `opcache.validate_timestamps=1` with `opcache.revalidate_freq=60` (with timestamps off a
   deploy's new files are not seen until the lsphp processes recycle, which the deploy script
   cannot force on shared hosting). Check with `php -i | grep opcache.enable`. Typical gain:
   2–3× on every page.

2. **Cache driver: Redis if the plan has it, otherwise file.** `CACHE_STORE=database` and
   `SESSION_DRIVER=database` cost one or two MySQL round trips per request on top of the page's
   own queries. If the host offers Redis (cPanel → Redis, or a `redis` PHP extension and a
   socket), set `CACHE_STORE=redis`, `SESSION_DRIVER=redis` and the `REDIS_*` keys. If not,
   `CACHE_STORE=file` is still faster than the database on shared hosting for this app's cached
   bits (settings, categories, sitemap, home sections), and `SESSION_DRIVER=file` is fine for one
   server. Always run `php artisan config:cache` after changing `.env` (the deploy does).

3. **Find the slow queries with the query log.** Run one browse and one checkout with the
   query log on and look for N+1s and missing indexes:

   ```php
   // tinker, or temporarily in AppServiceProvider::boot() on the TEST site only
   \DB::listen(fn ($q) => \Log::info($q->time.' ms '.$q->sql, $q->bindings));
   ```

   then `grep -c 'ms select' storage/logs/laravel.log` per page. More than ~30 queries for a
   product page, or any query over 50 ms, is worth fixing: `->with([...])` eager loads for the
   N+1, an index migration for the slow `WHERE`/`ORDER BY` (see
   `database/migrations/2026_10_01_000003_add_query_indexes.php` for the pattern). Turn the
   listener off again; it doubles the page time while it runs. `php artisan model:show` and
   `EXPLAIN` in phpMyAdmin tell you whether an index is used.

After each fix, run the same k6 command again and compare the `RESULT:` line; keep
`last-run.json` files from before and after if you want to graph them (`k6 run --out json=...`
gives per-request data).

## Light check without k6

`light-baseline.py` needs only Python 3: one visitor, then 5, then 10, browsing with think time
(about 100 requests a minute at most), and it stops by itself after 5 refused or failed requests.

```bash
python3 tests/load/light-baseline.py https://test.iruali.mv
```

## Baseline, 8 October 2026 (test site, about 11:45 pm Maldives time)

Measured from a cloud machine outside the Maldives (every request opens a new secure
connection, which costs about 0.55–0.8 s on that route before the server is even asked):

| Page | Server time, one visitor (median) | Whole request from outside the Maldives (median) |
| --- | --- | --- |
| `/api/health` | 0.35 s | 1.0 s |
| Home, category, product, search, `/brands`, `/dv` | 0.61–0.67 s | 1.4–1.9 s |

"Server time" is time to first byte after the connection is up, so it still includes one round
trip to the Maldives (roughly 0.3 s from where the test ran): the app itself spends about 0.3 s
on a page. With 5 visitors browsing at once the medians did not move (1.7 s whole request), so
light traffic does not slow the site down.

What the run could not measure: about 1 in 20 new connections to test.iruali.mv stalled for 7–12
seconds in the TLS handshake and then failed, even at one request every two seconds, while 20 of
20 requests to another site from the same machine went through. That is the network route or the
server's firewall (Imunify360 / connection limits), not the app, so the check stopped itself
before the 10-visitor phase. Ask the host whether the firewall throttles new connections, and run
the full k6 test from your own computer with its IP whitelisted (above) to measure capacity.
