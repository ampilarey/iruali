// k6 load test for iruali: 500 virtual users browse (home, category, product, search) for
// 5 minutes; 50 of them also sign in as the smoke customer, add the product to the cart and
// reach the checkout page (nothing is ordered). See tests/load/README.md for how to run it.
//
//   k6 run -e BASE_URL=https://test.iruali.mv \
//          -e SMOKE_USER_EMAIL=smoke@iruali.mv -e SMOKE_USER_PASSWORD=... \
//          tests/load/k6-browse-and-checkout.js
//
// Never point this at production.

import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.2/index.js';

const BASE = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const EMAIL = __ENV.SMOKE_USER_EMAIL || '';
const PASSWORD = __ENV.SMOKE_USER_PASSWORD || '';
const DURATION = __ENV.DURATION || '5m';
const BROWSERS = Number(__ENV.BROWSERS || 450);
const SHOPPERS = Number(__ENV.SHOPPERS || 50);

export const errors = new Rate('errors');
export const pageTime = new Trend('page_time', true);
export const checkoutTime = new Trend('checkout_time', true);

export const options = {
    scenarios: {
        browse: {
            executor: 'ramping-vus',
            exec: 'browse',
            startVUs: 0,
            stages: [
                { duration: '1m', target: BROWSERS },
                { duration: DURATION, target: BROWSERS },
                { duration: '30s', target: 0 },
            ],
            gracefulRampDown: '30s',
        },
        shop: {
            executor: 'ramping-vus',
            exec: 'shop',
            startVUs: 0,
            stages: [
                { duration: '1m', target: SHOPPERS },
                { duration: DURATION, target: SHOPPERS },
                { duration: '30s', target: 0 },
            ],
            gracefulRampDown: '30s',
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<800'],
        errors: ['rate<0.01'],
        'http_req_duration{page:product}': ['p(95)<800'],
        'http_req_duration{page:checkout}': ['p(95)<1500'],
    },
    userAgent: 'Iruali-k6/1.0 (load test)',
};

// Discover real product and category URLs from the sitemap once, shared by every VU.
export function setup() {
    const res = http.get(`${BASE}/sitemap.xml`);
    const locs = [...res.body.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]);
    const products = locs.filter((u) => u.includes('/products/')).slice(0, 50);
    const categories = locs.filter((u) => u.includes('/categories/')).slice(0, 20);
    if (products.length === 0 || categories.length === 0) {
        throw new Error('sitemap has no products or categories; is the site seeded?');
    }
    // The cart needs a product id, which the product page carries in the add-to-cart form. A
    // product sold in variants needs one picked, so the shoppers use the first product without.
    let productId = null;
    let productUrl = products[0];
    for (const url of products.slice(0, 10)) {
        const body = http.get(url).body;
        const idMatch = body.match(/name="product_id"\s+value="(\d+)"/);
        if (idMatch && !body.includes('data-variant-picker')) {
            productId = idMatch[1];
            productUrl = url;
            break;
        }
    }

    return { products, categories, productId, productUrl };
}

function pick(list) {
    return list[Math.floor(Math.random() * list.length)];
}

function page(name, url, extraChecks = {}) {
    const res = http.get(url, { tags: { page: name } });
    const ok = check(res, {
        [`${name} is 200`]: (r) => r.status === 200,
        [`${name} is html`]: (r) => (r.headers['Content-Type'] || '').includes('text/html'),
        ...extraChecks,
    });
    errors.add(!ok);
    pageTime.add(res.timings.duration, { page: name });

    return res;
}

export function browse(data) {
    group('home', () => page('home', `${BASE}/`));
    sleep(1 + Math.random() * 2);
    group('category', () => page('category', pick(data.categories)));
    sleep(1 + Math.random() * 2);
    group('product', () => page('product', pick(data.products)));
    sleep(1 + Math.random() * 2);
    group('search', () => page('search', `${BASE}/search?q=${encodeURIComponent(pick(['a', 'e', 'bag', 'phone', 'mas', 'dress']))}`));
    sleep(2 + Math.random() * 3);
}

function csrf(body) {
    const m = body.match(/name="_token"\s+value="([^"]+)"/);

    return m ? m[1] : null;
}

export function shop(data) {
    if (!EMAIL || !PASSWORD || !data.productId) {
        // Without a smoke user the shoppers just browse the product and the cart page.
        page('product', data.productUrl);
        page('cart', `${BASE}/cart`);
        sleep(3);

        return;
    }

    const jar = http.cookieJar();
    jar.clear(BASE);

    group('sign in', () => {
        const form = page('login', `${BASE}/login`);
        const res = http.post(`${BASE}/login`, { email: EMAIL, password: PASSWORD, _token: csrf(form.body) }, {
            redirects: 0,
            tags: { page: 'login-post' },
        });
        const ok = check(res, { 'login redirects away from /login': (r) => r.status === 302 && !(r.headers['Location'] || '').includes('/login') });
        errors.add(!ok);
    });
    sleep(1);

    group('add to cart', () => {
        const product = page('product', data.productUrl);
        const res = http.post(`${BASE}/cart/add`, { product_id: data.productId, quantity: 1, _token: csrf(product.body) }, {
            redirects: 0,
            tags: { page: 'cart-add' },
        });
        const ok = check(res, { 'add to cart redirects': (r) => r.status === 302 });
        errors.add(!ok);
    });
    sleep(1);

    group('checkout', () => {
        const start = Date.now();
        page('cart', `${BASE}/cart`);
        const res = page('checkout', `${BASE}/checkout`, { 'checkout shows the terms box': (r) => r.body.includes('agree_terms') });
        checkoutTime.add(Date.now() - start);
        // Leave the cart as it is: the next iteration adds the same product again (quantity 2, 3 …),
        // which is fine for a test site; the cart is cleared when the VU signs in afresh.
        if (res.status !== 200) {
            errors.add(1);
        }
    });

    group('clear cart', () => {
        const cart = http.get(`${BASE}/cart`);
        http.post(`${BASE}/cart/clear`, { _token: csrf(cart.body) }, { redirects: 0, tags: { page: 'cart-clear' } });
    });
    sleep(3 + Math.random() * 3);
}

export function handleSummary(data) {
    const p95 = data.metrics.http_req_duration.values['p(95)'].toFixed(0);
    const err = (data.metrics.errors.values.rate * 100).toFixed(2);
    const reqs = data.metrics.http_reqs.values.count;
    const line = `\nRESULT: ${reqs} requests, p95 ${p95} ms, error rate ${err}%\n`;

    return {
        stdout: textSummary(data, { indent: ' ', enableColors: true }) + line,
        'tests/load/last-run.json': JSON.stringify(data, null, 2),
    };
}
