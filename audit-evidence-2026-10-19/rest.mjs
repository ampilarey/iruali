import { chromium } from '@playwright/test';
import { execFileSync, execSync } from 'node:child_process';
import fs from 'node:fs';

const BASE = 'http://127.0.0.1:8020';
const OUT = '/workspace/audit-evidence-2026-10-19';
const results = [];
const record = (f, s, d = '') => {
    results.push({ feature: f, status: s, detail: String(d).replace(/\s+/g, ' ').slice(0, 400) });
    console.log(`${s}\t${f}\t${results.at(-1).detail}`);
};
const sql = (q) => execSync('sqlite3 /workspace/database/audit.sqlite ' + JSON.stringify(q), { encoding: 'utf8' }).trim();
const php = (code) => execFileSync('/tmp/iruali-audit-env.sh', ['php', 'artisan', 'tinker', '--execute=' + code], { encoding: 'utf8' }).trim();
const totp = (secret) => execSync('php -r ' + JSON.stringify(`require '/workspace/vendor/autoload.php'; echo (new PragmaRX\\Google2FA\\Google2FA)->getCurrentOtp(${JSON.stringify(secret.replace(/\s+/g, ''))});`), { encoding: 'utf8' }).trim();
const shot = (p, n) => p.screenshot({ path: `${OUT}/screenshots/${n}.png`, fullPage: false }).catch(() => {});

const browser = await chromium.launch({ executablePath: '/usr/bin/google-chrome', headless: true });
const open = async (width = 1280) => {
    const ctx = await browser.newContext({ viewport: { width, height: 900 } });
    return { ctx, page: await ctx.newPage() };
};
const xsrf = async (ctx) => decodeURIComponent((await ctx.cookies()).find((c) => c.name === 'XSRF-TOKEN')?.value || '');

async function phoneLogin(page, phone, password) {
    await page.goto(BASE + '/login');
    await page.fill('#login', phone);
    await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
    await page.waitForSelector('#password', { timeout: 10000 });
    await page.fill('#password', password);
    await page.locator('form[action$="/login/phone/password"] button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
}
async function emailLogin(page, email, password) {
    await page.goto(BASE + '/login/email');
    await page.fill('#email', email);
    await page.fill('#password', password);
    await page.locator('form[action$="/login"] button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
}

try {
    const buyer = await open();
    await phoneLogin(buyer.page, '7911103', 'Audit-pass-1!');
    record('buyer still signed in', buyer.page.url().includes('/login') ? 'Fail' : 'Pass', buyer.page.url());
    const token = await xsrf(buyer.ctx);
    const add = await buyer.page.request.post(BASE + '/cart/clear', { headers: { 'X-XSRF-TOKEN': token }, form: {}, maxRedirects: 0 });
    const add2 = await buyer.page.request.post(BASE + '/cart/add', {
        headers: { 'X-XSRF-TOKEN': await xsrf(buyer.ctx) },
        form: { product_id: '3', quantity: '1' },
        maxRedirects: 0,
    });
    const order = await buyer.page.request.post(BASE + '/orders', {
        headers: { 'X-XSRF-TOKEN': await xsrf(buyer.ctx), Accept: 'text/html' },
        form: {
            shipping_address: 'Audit house',
            shipping_city: 'Feridhoo',
            shipping_state: 'Alif Alif',
            shipping_country: 'Maldives',
            shipping_phone: '7911103',
            payment_method: 'bml',
            delivery_zone: 'greater_male',
            agree_terms: '1',
            'fulfilment[2]': 'deliver',
        },
        maxRedirects: 0,
    });
    const loc = order.headers()['location'] || '';
    const body = (await order.text()).replace(/\s+/g, ' ').slice(0, 240);
    const row = sql("SELECT id||'|'||order_number||'|'||delivery_zone||'|'||shipping_city||'|'||shipping_amount||'|'||total_amount||'|'||payment_method||'|'||status FROM orders ORDER BY id DESC LIMIT 1;");
    const parts = row.split('|');
    const fee = Number(parts[4]);
    record('crafted greater_male + Feridhoo', fee === 75 && parts[2] === 'islands' ? 'Pass' : 'Fail', `http ${order.status()} loc ${loc} db ${row} body ${body}`);
    fs.writeFileSync(OUT + '/delivery-post.txt', `status ${order.status()}\nlocation ${loc}\nrow ${row}\nclear ${add.status()} add ${add2.status()}\n${body}\n`);
    await buyer.ctx.close();
    const orderId = parts[0];

    const admin = await open();
    await emailLogin(admin.page, 'admin@example.com', 'password');
    const before = await admin.page.goto(BASE + '/admin/orders/' + orderId);
    record('admin order before payment link', (before?.status() ?? 0) < 400 ? 'Pass' : 'Fail', `${before?.status()} ${admin.page.url()}`);
    const send = await admin.page.request.post(BASE + '/admin/orders/' + orderId + '/payment-link', {
        headers: { 'X-XSRF-TOKEN': await xsrf(admin.ctx) },
        form: { 'by[]': 'sms' },
        maxRedirects: 0,
    });
    const sentAt = sql(`SELECT payment_link_sent_at FROM orders WHERE id=${orderId};`);
    record('send payment link', sentAt ? 'Pass' : 'Fail', `http ${send.status()} sent_at ${sentAt}`);
    const after = await admin.page.goto(BASE + '/admin/orders/' + orderId);
    await shot(admin.page, 'admin-order-after-link');
    record('admin order after payment link', (after?.status() ?? 0) >= 500 ? 'Fail' : 'Pass', `${after?.status()} ${admin.page.url()}`);

    const fresh = php(`echo URL::temporarySignedRoute('pay.show', now()->addHour(), ['order' => ${orderId}]);`);
    const expired = php(`echo URL::temporarySignedRoute('pay.show', now()->subMinute(), ['order' => ${orderId}]);`);
    const freshUrl = fresh.split('\n').filter((l) => l.startsWith('http')).pop();
    const expiredUrl = expired.split('\n').filter((l) => l.startsWith('http')).pop();
    const guest = await open(390);
    const live = await guest.page.goto(freshUrl);
    await shot(guest.page, 'pay-link-fresh-phone');
    record('fresh payment link', (live?.status() ?? 0) === 200 && /pay|card|transfer/i.test(await guest.page.locator('body').innerText()) ? 'Pass' : 'Fail', `${live?.status()} ${guest.page.url()}`);
    const dead = await guest.page.goto(expiredUrl);
    await shot(guest.page, 'pay-link-expired');
    record('expired payment link', (dead?.status() ?? 0) === 403 ? 'Pass' : 'Fail', `${dead?.status()} ${guest.page.url()}`);
    await guest.ctx.close();

    php(`$o=App\\Models\\Order::find(${orderId}); $o->created_at=now()->subHours(30); $o->save(); echo $o->status;`);
    const cancel1 = execSync('/tmp/iruali-audit-env.sh artisan orders:cancel-unpaid-card', { encoding: 'utf8' }).trim();
    const still = sql(`SELECT status||'|'||payment_link_sent_at FROM orders WHERE id=${orderId};`);
    record('unpaid cancel skipped while link is live', still.startsWith('pending') ? 'Pass' : 'Fail', `${cancel1} db ${still}`);
    php(`App\\Models\\Order::whereKey(${orderId})->update(['payment_link_sent_at' => now()->subHours(49)->format('Y-m-d H:i:s')]); echo 'aged';`);
    const cancel2 = execSync('/tmp/iruali-audit-env.sh artisan orders:cancel-unpaid-card', { encoding: 'utf8' }).trim();
    const later = sql(`SELECT status FROM orders WHERE id=${orderId};`);
    record('unpaid cancel after link is older than 48h', later === 'cancelled' ? 'Pass' : 'Fail', `${cancel2} status ${later}`);
    fs.writeFileSync(OUT + '/paylink-cancel.txt', `${cancel1}\n${still}\n${cancel2}\n${later}\n`);

    const pages = ['/admin/exports', '/admin/tax', '/admin/tax/report', '/admin/settlements', '/admin/webhooks', '/admin/audit', '/admin/errors', '/admin/home-page', '/admin/sms', '/admin/sms/campaigns', '/admin/returns', '/admin/payouts', '/admin/finance', '/admin/rewards', '/admin/users'];
    for (const path of pages) {
        const res = await admin.page.goto(BASE + path);
        const status = res?.status() ?? 0;
        record('admin ' + path, status >= 500 ? 'Fail' : status < 400 ? 'Pass' : 'Not tested', String(status));
    }
    await shot(admin.page, 'admin-users');

    php(`
        $keep = App\\Models\\User::firstOrCreate(['email' => 'keep-audit@example.com'], ['name' => 'Keep Audit', 'password' => bcrypt('password'), 'phone' => '+9607911301', 'has_password' => true, 'phone_verified_at' => now(), 'status' => 'active', 'is_active' => true, 'referral_code' => 'KEEPAUD1']);
        $dup = App\\Models\\User::firstOrCreate(['email' => 'merge-away@example.com'], ['name' => 'Merge Away', 'password' => bcrypt('password'), 'phone' => '+9607911302', 'has_password' => true, 'phone_verified_at' => now(), 'status' => 'active', 'is_active' => true, 'referral_code' => 'MERGEAUD1']);
        $erase = App\\Models\\User::firstOrCreate(['email' => 'erase-audit@example.com'], ['name' => 'Erase Audit', 'password' => bcrypt('password'), 'phone' => '+9607911303', 'has_password' => true, 'phone_verified_at' => now(), 'status' => 'active', 'is_active' => true, 'referral_code' => 'ERASEAUD1']);
        echo $dup->id.' '.$erase->id.' '.$keep->id;
    `);
    const dupId = sql("SELECT id FROM users WHERE email='merge-away@example.com';");
    const eraseId = sql("SELECT id FROM users WHERE email='erase-audit@example.com';");
    const show = await admin.page.goto(BASE + '/admin/customers/' + dupId);
    record('customer page', (show?.status() ?? 0) < 400 ? 'Pass' : 'Fail', String(show?.status()));
    const merged = await admin.page.request.post(BASE + '/admin/customers/' + dupId + '/merge', {
        headers: { 'X-XSRF-TOKEN': await xsrf(admin.ctx) },
        form: { into: 'keep-audit@example.com', confirm: '1' },
        maxRedirects: 5,
    });
    const mergedInto = sql("SELECT COALESCE(merged_into_id,'') FROM users WHERE email='merge-away@example.com';");
    record('customer merge', mergedInto ? 'Pass' : 'Fail', `http ${merged.status()} merged_into ${mergedInto}`);
    const erased = await admin.page.request.post(BASE + '/admin/customers/' + eraseId + '/erase', {
        headers: { 'X-XSRF-TOKEN': await xsrf(admin.ctx) },
        form: { reason: 'audit drill', confirm: '1' },
        maxRedirects: 5,
    });
    const erasedAt = sql("SELECT COALESCE(erased_at,'') FROM users WHERE email='erase-audit@example.com';");
    record('customer erase', erasedAt ? 'Pass' : 'Fail', `http ${erased.status()} erased_at ${erasedAt}`);

    const camp = await admin.page.goto(BASE + '/admin/sms/campaigns');
    if ((camp?.status() ?? 0) < 400 && await admin.page.locator('input[name="name"], input[name="title"]').count()) {
        record('sms campaign form', 'Pass', 'create form is on the page');
    }
    await admin.ctx.close();

    const tokens = JSON.parse(php(`
        $u = App\\Models\\User::where('phone', '+9607911103')->orWhere('phone', '7911103')->first();
        $out = ['user' => $u->id];
        foreach (['profile:read','profile:write','cart:write','wishlist:write','order:read','order:write','products:write'] as $a) {
            $out[$a] = $u->createToken('audit-'.$a, [$a])->plainTextToken;
        }
        $out['none'] = $u->createToken('audit-none', [])->plainTextToken;
        echo json_encode($out);
    `.replace(/\n/g, ' ')).split('\n').filter((l) => l.startsWith('{')).pop());
    fs.writeFileSync('/tmp/audit-tokens.json', JSON.stringify(tokens));
    const call = async (ability, method, path, ok) => {
        const res = await fetch(BASE + path, { method, headers: { Authorization: 'Bearer ' + tokens[ability], Accept: 'application/json', 'Content-Type': 'application/json' }, body: method === 'GET' ? undefined : '{}' });
        const miss = await fetch(BASE + path, { method, headers: { Authorization: 'Bearer ' + tokens.none, Accept: 'application/json', 'Content-Type': 'application/json' }, body: method === 'GET' ? undefined : '{}' });
        const good = ok(res.status);
        record('api ' + ability + ' ' + method + ' ' + path, good && miss.status === 403 ? 'Pass' : 'Fail', `with=${res.status} without=${miss.status}`);
    };
    await call('profile:read', 'GET', '/api/v1/user', (s) => s === 200);
    await call('profile:write', 'PUT', '/api/v1/user', (s) => s !== 403);
    await call('cart:write', 'GET', '/api/v1/cart', (s) => s === 200);
    await call('wishlist:write', 'GET', '/api/v1/wishlist', (s) => s === 200);
    await call('order:read', 'GET', '/api/v1/orders', (s) => s === 200);
    await call('order:write', 'POST', '/api/v1/checkout/remove-points', (s) => s !== 403);
    await call('products:write', 'POST', '/api/v1/products', (s) => s !== 403);
    for (const path of ['/api/health', '/api/health/ready']) {
        const res = await fetch(BASE + path);
        const text = await res.text();
        record(path, res.status === 200 ? 'Pass' : 'Fail', `${res.status} ${text.slice(0, 180)}`);
    }
    fs.unlinkSync('/tmp/audit-tokens.json');

    const two = await open();
    await phoneLogin(two.page, '7911103', 'Audit-pass-1!');
    await two.page.goto(BASE + '/profile/2fa/setup');
    const secret = (await two.page.locator('.font-mono').first().innerText()).replace(/\s+/g, '');
    await two.page.locator('#code').fill(totp(secret));
    await two.page.getByRole('button', { name: 'Turn on two-step sign-in' }).click();
    await two.page.waitForLoadState('domcontentloaded');
    const onText = await two.page.locator('body').innerText();
    record('2FA enabled', /two-step sign-in is on|recovery/i.test(onText) ? 'Pass' : 'Fail', two.page.url());
    await shot(two.page, '2fa-enabled');
    const recovery = (await two.page.locator('.font-mono').first().innerText().catch(() => '')).trim();
    await two.ctx.close();
    const chal = await open();
    await phoneLogin(chal.page, '7911103', 'Audit-pass-1!');
    record('2FA challenge', chal.page.url().includes('/2fa') ? 'Pass' : 'Fail', chal.page.url());
    await shot(chal.page, '2fa-challenge');
    if (chal.page.url().includes('/2fa')) {
        await chal.page.locator('#code').fill(totp(secret));
        await chal.page.locator('form[action$="/2fa/verify"] button[type="submit"]').click();
        await chal.page.waitForLoadState('domcontentloaded');
        record('2FA code accepted', chal.page.url().includes('/2fa') ? 'Fail' : 'Pass', chal.page.url());
    }
    await chal.ctx.close();
    fs.writeFileSync('/tmp/audit-2fa-secret.txt', secret + '\n' + recovery + '\n');
} catch (e) {
    record('rest script', 'Fail', e.stack?.split('\n').slice(0, 4).join(' | ') || e.message);
}
await browser.close();
fs.writeFileSync(OUT + '/rest-results.json', JSON.stringify(results, null, 2));
