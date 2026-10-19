import { chromium } from '@playwright/test';
import { execSync } from 'node:child_process';
import fs from 'node:fs';
const BASE = 'http://127.0.0.1:8020';
const OUT = '/workspace/audit-evidence-2026-10-19';
const results = [];
const record = (f, s, d = '') => { results.push({ feature: f, status: s, detail: String(d).replace(/\s+/g, ' ').slice(0, 280) }); console.log(s + '\t' + f + '\t' + results.at(-1).detail); };
const sql = (q) => execSync('sqlite3 /workspace/database/audit.sqlite ' + JSON.stringify(q), { encoding: 'utf8' }).trim();
const otp = (f) => sql(`SELECT code FROM otps WHERE is_used=0 AND IFNULL(phone,'') LIKE '%${f}%' ORDER BY id DESC LIMIT 1;`);
const totp = (secret) => execSync('php -r ' + JSON.stringify(`require '/workspace/vendor/autoload.php'; echo (new PragmaRX\\Google2FA\\Google2FA)->getCurrentOtp(${JSON.stringify(secret.replace(/\s+/g,''))});`), { encoding: 'utf8' }).trim();
const shot = (p, n) => p.screenshot({ path: OUT + '/screenshots/' + n + '.png' });
async function section(n, fn) { try { await fn(); } catch (e) { record(n, 'Fail', e.message.split('\n')[0]); } }
execSync(`/tmp/iruali-audit-env.sh php artisan tinker --execute=${JSON.stringify("Illuminate\\Support\\Facades\\RateLimiter::clear('phone-login:'.sha1('+9607911103').':m');")}`);
const browser = await chromium.launch({ executablePath: '/usr/bin/google-chrome', headless: true });
const open = async () => { const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, acceptDownloads: true }); return { ctx, page: await ctx.newPage() }; };

await section('verified checkout SMS', async () => {
    const { ctx, page } = await open();
    await page.goto(BASE + '/products/lacquer-box-small');
    const d = page.locator('dialog[data-island-dialog][open]');
    if (await d.waitFor({ state: 'visible', timeout: 1500 }).then(() => true, () => false)) await d.getByRole('button', { name: /not now/i }).click();
    await page.locator('[data-add-to-cart]').first().click();
    await page.goto(BASE + '/checkout');
    await page.fill('#login', '7911103');
    await page.locator('[data-checkout-sign-in] form[action$="/login/phone"] button[type="submit"]').click();
    await page.waitForSelector('#password');
    await page.locator('[data-sms-code] button').click();
    await page.waitForSelector('#code', { timeout: 8000 });
    record('checkout SMS code for a verified phone', /^\d{6}$/.test(otp('7911103')) ? 'Pass' : 'Fail');
    await shot(page, 'checkout-sms-verified');
    await ctx.close();
});

await section('wishlist 2FA', async () => {
    const { ctx, page } = await open();
    await page.goto(BASE + '/login');
    await page.fill('#login', '7911103');
    await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
    await page.waitForSelector('#password');
    await page.fill('#password', 'Audit-pass-1!');
    await page.locator('form[action$="/login/phone/password"] button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
    await page.goto(BASE + '/products/snorkel-set-adult');
    await page.getByRole('button', { name: 'Add to Wishlist' }).click();
    await page.waitForLoadState('domcontentloaded');
    await page.goto(BASE + '/wishlist');
    record('wishlist', /snorkel/i.test(await page.locator('body').innerText()) ? 'Pass' : 'Fail');
    await shot(page, 'wishlist');
    await page.goto(BASE + '/profile/2fa/setup');
    await shot(page, '2fa-setup');
    const secret = (await page.locator('.font-mono').first().innerText()).replace(/\s+/g, '');
    await page.locator('input[name="code"]').fill(totp(secret));
    await page.locator('button[type="submit"]').first().click();
    await page.waitForLoadState('domcontentloaded');
    record('2FA enabled', /recovery|on|enabled/i.test(await page.locator('body').innerText()) ? 'Pass' : 'Fail', page.url());
    await shot(page, '2fa-enabled');
    await ctx.close();
    const c2 = await open();
    await c2.page.goto(BASE + '/login');
    await c2.page.fill('#login', '7911103');
    await c2.page.locator('form[action$="/login/phone"] button[type="submit"]').click();
    await c2.page.waitForSelector('#password');
    await c2.page.fill('#password', 'Audit-pass-1!');
    await c2.page.locator('form[action$="/login/phone/password"] button[type="submit"]').click();
    await c2.page.waitForLoadState('domcontentloaded');
    record('2FA challenge', c2.page.url().includes('/2fa') ? 'Pass' : 'Fail', c2.page.url());
    await shot(c2.page, '2fa-challenge');
    if (c2.page.url().includes('/2fa')) {
        await c2.page.locator('input[name="code"]').fill(totp(secret));
        await c2.page.locator('button[type="submit"]').first().click();
        await c2.page.waitForLoadState('domcontentloaded');
        record('2FA code accepted', c2.page.url().includes('/2fa') ? 'Fail' : 'Pass', c2.page.url());
    }
    await c2.ctx.close();
});

await section('export and roles', async () => {
    const s = await open();
    await s.page.goto(BASE + '/login/email');
    await s.page.fill('#email', 'islandcrafts@example.com');
    await s.page.fill('#password', 'password');
    await s.page.locator('form[action$="/login"] button[type="submit"]').click();
    await s.page.waitForLoadState('domcontentloaded');
    const csv = await s.page.request.get(BASE + '/seller/products/export.csv');
    record('seller csv export', csv.status() === 200 && (csv.headers()['content-type'] || '').includes('csv') ? 'Pass' : 'Fail', `${csv.status()} ${csv.headers()['content-type']}`);
    const imp = await s.page.goto(BASE + '/seller/products/import');
    record('seller csv import page', (imp?.status() ?? 500) < 400 ? 'Pass' : 'Fail', String(imp?.status()));
    await shot(s.page, 'seller-import');
    const hol = await s.page.goto(BASE + '/seller/settings/holiday');
    record('seller holiday page', (hol?.status() ?? 500) < 400 && s.page.url().includes('holiday') ? 'Pass' : 'Fail', s.page.url());
    await shot(s.page, 'seller-holiday');
    await s.ctx.close();
    const roles = [['thulhaadhoo@example.com', 'support', '/admin/orders', '/admin/settings'], ['maafushifresh@example.com', 'finance', '/admin/payouts', '/admin/products'], ['reefline@example.com', 'catalogue', '/admin/brands', '/admin/orders']];
    const admin = await open();
    await admin.page.goto(BASE + '/login/email');
    await admin.page.fill('#email', 'admin@example.com');
    await admin.page.fill('#password', 'password');
    await admin.page.locator('form[action$="/login"] button[type="submit"]').click();
    await admin.page.waitForURL(/admin/);
    for (const [email, role] of roles) {
        await admin.page.goto(BASE + '/admin/users');
        const row = admin.page.locator('tr', { hasText: email });
        await row.locator('select[name="role"]').selectOption(role);
        await row.getByRole('button', { name: 'Save' }).click();
        await admin.page.waitForLoadState('domcontentloaded');
        const has = sql(`SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id JOIN users u ON u.id=ur.user_id WHERE u.email='${email}' AND r.name='${role}';`);
        record('assign ' + role, has === role ? 'Pass' : 'Fail', has || 'missing');
    }
    await admin.ctx.close();
    for (const [email, role, allow, deny] of roles) {
        const { ctx, page } = await open();
        await page.goto(BASE + '/login/email');
        await page.fill('#email', email);
        await page.fill('#password', 'password');
        await page.locator('form[action$="/login"] button[type="submit"]').click();
        await page.waitForLoadState('domcontentloaded');
        const a = await page.goto(BASE + allow);
        const au = page.url();
        const d = await page.goto(BASE + deny);
        record('staff role ' + role, (a?.status() ?? 500) < 400 && au.includes(allow) && d?.status() === 403 ? 'Pass' : 'Fail', `${allow}=${a?.status()} ${au} ${deny}=${d?.status()} ${page.url()}`);
        await shot(page, 'role-' + role + '-denied');
        await ctx.close();
    }
});

await browser.close();
fs.writeFileSync(OUT + '/finish-results.json', JSON.stringify(results, null, 2));
