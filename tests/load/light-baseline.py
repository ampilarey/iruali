"""Light load check for the TEST site, with nothing but Python 3 (no k6 needed).

Browses only (home, categories, products, search, brands, Dhivehi pages); nothing is ordered.
Phase A: one visitor, each page type three times. Phase B: 5 visitors with ~3 s think time
for 90 s. Phase C: 10 visitors for 60 s. It stops by itself after 5 refused or failed
requests (403/429/5xx/connection errors), so it never hammers a site that is struggling.

    python3 tests/load/light-baseline.py https://test.iruali.mv

Pages are taken from the site's sitemap. Results print as JSON per phase and are saved to
tests/load/light-baseline-last.json (ignored by git). Never point this at production.
"""

import json
import random
import re
import statistics
import sys
import threading
import time
import urllib.error
import urllib.request
from pathlib import Path

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'https://test.iruali.mv').rstrip('/')
UA = 'Iruali-light-baseline/1.0 (site owner load check)'
REFUSED = {403, 429, 500, 502, 503, 504, 508}
STOP_AFTER = 5

lock = threading.Lock()
results = []  # (phase, page type, status, seconds)
stop = threading.Event()


def get(path):
    request = urllib.request.Request(BASE + path, headers={'User-Agent': UA, 'Accept': 'text/html,application/json'})
    with urllib.request.urlopen(request, timeout=30) as response:
        return response.status, response.read()


def fetch(phase, kind, path):
    started = time.perf_counter()
    try:
        status, _ = get(path)
    except urllib.error.HTTPError as e:
        status = e.code
    except Exception as e:  # timeouts, resets, refused connections
        status = 'error:' + type(e).__name__
    seconds = time.perf_counter() - started
    with lock:
        results.append((phase, kind, status, seconds))
        failed = sum(1 for r in results if r[0] == phase and (r[2] in REFUSED or str(r[2]).startswith('error')))
    if failed >= STOP_AFTER:
        stop.set()


def pages():
    _, body = get('/sitemap.xml')
    paths = [loc.replace(BASE, '') or '/' for loc in re.findall(r'<loc>([^<]+)</loc>', body.decode())]
    products = [p for p in paths if p.startswith('/products/')] or ['/']
    return {
        'home': ['/'],
        'category': [p for p in paths if p.startswith('/categories/')] or ['/'],
        'product': products,
        'search': ['/search?q=fish', '/search?q=shirt', '/search?q=solar', '/search?q=coconut'],
        'brands': ['/brands'],
        'dhivehi': ['/dv'] + ['/dv' + p for p in products[:3]],
        'health': ['/api/health'],
    }


def visitor(phase, until, site):
    rnd = random.Random()
    while time.time() < until and not stop.is_set():
        for kind in ['home' if rnd.random() < 0.4 else 'category', 'product', rnd.choice(['search', 'brands', 'product', 'dhivehi'])]:
            if time.time() >= until or stop.is_set():
                return
            fetch(phase, kind, rnd.choice(site[kind]))
            time.sleep(rnd.uniform(2, 4))


def p95(values):
    values = sorted(values)
    return values[min(len(values) - 1, int(len(values) * 0.95))]


def summary(phase):
    rows = [r for r in results if r[0] == phase]
    out = {'requests': len(rows), 'status': {}}
    for r in rows:
        out['status'][str(r[2])] = out['status'].get(str(r[2]), 0) + 1
    for kind in sorted({r[1] for r in rows}):
        times = [r[3] for r in rows if r[1] == kind]
        out[kind] = {'n': len(times), 'median_ms': round(statistics.median(times) * 1000), 'p95_ms': round(p95(times) * 1000)}
    return out


def main():
    site = pages()
    report = {'base': BASE, 'started': time.strftime('%Y-%m-%d %H:%M:%S %Z')}

    for kind in ['health', 'home', 'category', 'product', 'search', 'brands', 'dhivehi']:
        for i in range(3):
            if not stop.is_set():
                fetch('A', kind, site[kind][i % len(site[kind])])
                time.sleep(1)
    report['A: 1 visitor'] = summary('A')
    print('A', json.dumps(report['A: 1 visitor']), flush=True)

    for phase, visitors, seconds in [('B', 5, 90), ('C', 10, 60)]:
        name = f'{phase}: {visitors} visitors for {seconds}s'
        if stop.is_set():
            report[name] = 'skipped: too many refused or failed requests in the previous phase'
            continue
        until = time.time() + seconds
        threads = [threading.Thread(target=visitor, args=(phase, until, site)) for _ in range(visitors)]
        for thread in threads:
            thread.start()
            time.sleep(seconds / visitors / 6)
        for thread in threads:
            thread.join()
        report[name] = summary(phase)
        print(phase, json.dumps(report[name]), flush=True)
        time.sleep(10)

    report['stopped_early'] = stop.is_set()
    Path(__file__).with_name('light-baseline-last.json').write_text(json.dumps(report, indent=2))
    print('stopped early' if stop.is_set() else 'finished', '- saved tests/load/light-baseline-last.json')


if __name__ == '__main__':
    main()
