import { chromium } from '@playwright/test';
import { execFileSync, execSync } from 'node:child_process';
import fs from 'node:fs';

const BASE = 'http://127.0.0.1:8020';
const OUT = '/workspace/audit-evidence-2026-10-19';
const orderId = 2;
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
async function emailLogin(page, email, password) {
    await page.goto(BASE + '/login/email');
    await page.fill('#email', email);
    await page.fill('#password', password);
    await page.locator('form[action$="/login"] button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
}
async function phoneLogin(page, phone, password) {
    await page.goto(BASE + '/login');
    await page.fill('#login', phone);
    await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
    await page.waitForSelector('#password', { timeout: 10000 });
    await page.fill('#password', password);
    await page.locator('form[action$="/login/phone/password"] button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
}

try {
    const aged = php(`$o=App\\Models\\Order::find(${orderId}); $o->created_at=now()->subHours(30); $o->save(); echo $o->fresh()->status.' '.$o->payment_link_sent_at;`);
    record('aged order created_at', aged.includes('pending') ? 'Pass' : 'Fail', aged);
    const cancel1 = execFileSync('/tmp/iruali-audit-env.sh', ['php', 'artisan', 'orders:cancel-unpaid-card'], { encoding: 'utf8' }).trim();
    const still = sql(`SELECT status FROM orders WHERE id=${orderId};`);
    record('unpaid cancel skipped while link is live', still === 'pending' ? 'Pass' : 'Fail', `${cancel1} status ${still}`);
    const agedLink = php(`App\\Models\\Order::whereKey(${orderId})->update(['payment_link_sent_at' => now()->subHours(49)->format('Y-m-d H:i:s')]); echo App\\Models\\Order::find(${orderId})->payment_link_sent_at;`);
    const cancel2 = execFileSync('/tmp/iruali-audit-env.sh', ['php', 'artisan', 'orders:cancel-unpaid-card'], { encoding: 'utf8' }).trim();
    const later = sql(`SELECT status FROM orders WHERE id=${orderId};`);
    record('unpaid cancel after link is older than 48h', later === 'cancelled' ? 'Pass' : 'Fail', `${cancel2} link ${agedLink} status ${later}`);
    fs.writeFileSync(OUT + '/paylink-cancel.txt', `${aged}\n${cancel1}\n${still}\n${agedLink}\n${cancel2}\n${later}\n`);

    const admin = await open();
    await emailLogin(admin.page, 'admin@example.com', 'password');
    const pages = ['/admin/exports', '/admin/tax', '/admin/tax/report', '/admin/settlements', '/admin/webhooks', '/admin/audit', '/admin/errors', '/admin/home-page', '/admin/sms', '/admin/sms/campaigns', '/admin/returns', '/admin/payouts', '/admin/finance', '/admin/rewards', '/admin/users'];
    for (const path of pages) {
        const res = await admin.page.goto(BASE + path);
        const status = res?.status() ?? 0;
        record('admin ' + path, status >= 500 ? 'Fail' : status < 400 ? 'Pass' : 'Not tested', String(status));
        if (status >= 500) await shot(admin.page, 'fail-' + path.replace(/\//g, '-'));
    }

    const made = php(`
        $keep = App\\Models\\User::firstOrCreate(['email' => 'keep-audit@example.com'], ['name' => 'Keep Audit', 'password' => bcrypt('password'), 'phone' => '+9607911301', 'has_password' => true, 'phone_verified_at' => now(), 'status' => 'active', 'is_active' => true, 'referral_code' => 'KEEPAUD1']);
        $dup = App\\Models\\User::firstOrCreate(['email' => 'merge-away@example.com'], ['name' => 'Merge Away', 'password' => bcrypt('password'), 'phone' => '+9607911302', 'has_password' => true, 'phone_verified_at' => now(), 'status' => 'active', 'is_active' => true, 'referral_code' => 'MERGEAUD1']);
        $erase = App\\Models\\User::firstOrCreate(['email' => 'erase-audit@example.com'], ['name' => 'Erase Audit', 'password' => bcrypt('password'), 'phone' => '+9607911303', 'has_password' => true, 'phone_verified_at' => now(), 'status' => 'active', 'is_active' => true, 'referral_code' => 'ERASEAUD1']);
        echo $dup->id.' '.$erase->id.' '.$keep->id;
    `);
    record('throwaway customers', /\d+/.test(made) ? 'Pass' : 'Fail', made);
    const dupId = sql("SELECT id FROM users WHERE email='merge-away@example.com';");
    const eraseId = sql("SELECT id FROM users WHERE email='erase-audit@example.com';");
    const show = await admin.page.goto(BASE + '/admin/customers/' + dupId);
    record('customer page', (show?.status() ?? 0) < 400 ? 'Pass' : 'Fail', `${show?.status()} ${admin.page.url()}`);
    await shot(admin.page, 'admin-customer');
    const merged = await admin.page.request.post(BASE + '/admin/customers/' + dupId + '/merge', {
        headers: { 'X-XSRF-TOKEN': await xsrf(admin.ctx), Accept: 'text/html' },
        form: { into: 'keep-audit@example.com', confirm: '1' },
        maxRedirects: 5,
    });
    const mergedInto = sql("SELECT COALESCE(merged_into_id,'')||' '||COALESCE(status,'') FROM users WHERE email='merge-away@example.com';");
    const mergeBody = (await merged.text()).replace(/\s+/g, ' ').slice(0, 180);
    record('customer merge', mergedInto.split(' ')[0] ? 'Pass' : 'Fail', `http ${merged.status()} ${mergedInto} ${mergeBody}`);
    const erased = await admin.page.request.post(BASE + '/admin/customers/' + eraseId + '/erase', {
        headers: { 'X-XSRF-TOKEN': await xsrf(admin.ctx), Accept: 'text/html' },
        form: { reason: 'audit drill', confirm: '1' },
        maxRedirects: 5,
    });
    const erasedAt = sql("SELECT COALESCE(erased_at,'') FROM users WHERE email='erase-audit@example.com';");
    record('customer erase', erasedAt ? 'Pass' : 'Fail', `http ${erased.status()} erased_at ${erasedAt}`);
    await admin.ctx.close();

    const raw = php(`
        $u = App\\Models\\User::where('phone', '+9607911103')->first();
        $out = ['user' => $u->id];
        foreach (['profile:read','profile:write','cart:write','wishlist:write','order:read','order:write','products:write'] as $a) {
            $out[$a] = $u->createToken('audit-'.$a, [$a])->plainTextToken;
        }
        $out['none'] = $u->createToken('audit-none', [])->plainTextToken;
        echo json_encode($out);
    `);
    const tokens = JSON.parse(raw.split('\n').filter((l) => l.startsWith('{')).pop());
    const call = async (ability, method, path, ok) => {
        const res = await fetch(BASE + path, { method, headers: { Authorization: 'Bearer ' + tokens[ability], Accept: 'application/json', 'Content-Type': 'application/json' }, body: method === 'GET' ? undefined : '{}' });
        const miss = await fetch(BASE + path, { method, headers: { Authorization: 'Bearer ' + tokens.none, Accept: 'application/json', 'Content-Type': 'application/json' }, body: method === 'GET' ? undefined : '{}' });
        record('api ' + ability + ' ' + method + ' ' + path, ok(res.status) && miss.status === 403 ? 'Pass' : 'Fail', `with=${res.status} without=${miss.status}`);
    };
    await call('profile:read', 'GET', '/api/v1/user', (s) => s === 200);
    await call('profile:write', 'PUT', '/api/v1/user', (s) => s !== 403 && s < 500);
    await call('cart:write', 'GET', '/api/v1/cart', (s) => s === 200);
    await call('wishlist:write', 'GET', '/api/v1/wishlist', (s) => s === 200);
    await call('order:read', 'GET', '/api/v1/orders', (s) => s === 200);
    await call('order:write', 'POST', '/api/v1/checkout/remove-points', (s) => s !== 403 && s < 500);
    await call('products:write', 'POST', '/api/v1/products', (s) => s !== 403 && s < 500);
    for (const path of ['/api/health', '/api/health/ready']) {
        const res = await fetch(BASE + path);
        record(path, res.status === 200 ? 'Pass' : 'Fail', `${res.status} ${(await res.text()).slice(0, 180)}`);
    }
    php(`App\\Models\\User::find(8)->tokens()->where('name', 'like', 'audit-%')->delete(); echo 'revoked';`);

    const two = await open();
    await phoneLogin(two.page, '7911103', 'Audit-pass-1!');
    await two.page.goto(BASE + '/profile/2fa/setup');
    const secret = (await two.page.locator('.font-mono').first().innerText()).replace(/\s+/g, '');
    await two.page.locator('#code').fill(totp(secret));
    await two.page.getByRole('button', { name: 'Turn on two-step sign-in' }).click();
    await two.page.waitForLoadState('domcontentloaded');
    const onText = await two.page.locator('body').innerText();
    record('2FA enabled', /two-step sign-in is on|Save your recovery codes/i.test(onText) ? 'Pass' : 'Fail', two.page.url() + ' ' + onText.slice(0, 120));
    await shot(two.page, '2fa-enabled');
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
} catch (e) {
    record('rest2 script', 'Fail', (e.stderr ? e.stderr.toString() : '') + ' ' + (e.stack || e.message).split('\n').slice(0, 6).join(' | '));
}
await browser.close();
fs.writeFileSync(OUT + '/rest2-results.json', JSON.stringify(results, null, 2));
