const BASE = 'http://127.0.0.1:8020';
const paths = ['/', '/search?q=tuna', '/products/coir-rope-10-m', '/cart'];
const seconds = 120;
const clients = 20;
const samples = [];
let errors = 0;
const byPath = Object.fromEntries(paths.map((p) => [p, { n: 0, errors: 0, ms: [] }]));
const end = Date.now() + seconds * 1000;

async function worker() {
    while (Date.now() < end) {
        const path = paths[Math.floor(Math.random() * paths.length)];
        const t = performance.now();
        try {
            const res = await fetch(BASE + path, { headers: { Accept: 'text/html' } });
            const ms = performance.now() - t;
            await res.arrayBuffer();
            samples.push(ms);
            byPath[path].n++;
            byPath[path].ms.push(ms);
            if (res.status >= 500) {
                errors++;
                byPath[path].errors++;
            }
        } catch {
            errors++;
            byPath[path].errors++;
            byPath[path].n++;
        }
    }
}

const started = Date.now();
await Promise.all(Array.from({ length: clients }, worker));
const elapsed = (Date.now() - started) / 1000;
const sorted = samples.slice().sort((a, b) => a - b);
const pct = (p) => sorted.length ? sorted[Math.min(sorted.length - 1, Math.floor((p / 100) * sorted.length))] : null;
const summary = {
    clients,
    seconds,
    elapsed,
    requests: samples.length,
    errors,
    p50: pct(50),
    p95: pct(95),
    paths: Object.fromEntries(Object.entries(byPath).map(([k, v]) => [k, { n: v.n, errors: v.errors, p50: (() => { const s = v.ms.sort((a, b) => a - b); return s.length ? s[Math.floor(0.5 * s.length)] : null; })(), p95: (() => { const s = v.ms; return s.length ? s[Math.min(s.length - 1, Math.floor(0.95 * s.length))] : null; })() }])),
};
console.log(JSON.stringify(summary, null, 2));
