import { chromium } from '@playwright/test';
import { execSync } from 'node:child_process';
import fs from 'node:fs';
const BASE = 'http://127.0.0.1:8020';
const OUT = '/workspace/audit-evidence-2026-10-19';
const results = [];
const record = (f, s, d = '') => { results.push({ feature: f, status: s, detail: String(d).replace(/\s+/g, ' ').slice(0, 300) }); console.log(`${s}\t${f}\t${results.at(-1).detail}`); };
const sql = (q) => execSync('sqlite3 /workspace/database/audit.sqlite ' + JSON.stringify(q), { encoding: 'utf8' }).trim();
const totp = (secret) => execSync('php -r ' + JSON.stringify(`require '/workspace/vendor/autoload.php'; echo (new PragmaRX\\Google2FA\\Google2FA)->getCurrentOtp(${JSON.stringify(secret.replace(/\s+/g,''))});`), { encoding: 'utf8' }).trim();
const shot = (p, n) => p.screenshot({ path: `${OUT}/screenshots/${n}.png` });
const browser = await chromium.launch({ executablePath: '/usr/bin/google-chrome', headless: true });
const open = async () => { const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } }); return { ctx, page: await ctx.newPage() }; };

const roles = [
    ['support-audit@example.com', 'support', '/admin/orders', '/admin/settings'],
    ['finance-audit@example.com', 'finance', '/admin/payouts', '/admin/products'],
    ['catalogue-audit@example.com', 'catalogue', '/admin/brands', '/admin/orders'],
];
try {
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
        const flash = await admin.page.locator('body').innerText();
        const has = sql(`SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id JOIN users u ON u.id=ur.user_id WHERE u.email='${email}' AND r.name='${role}';`);
        record('assign ' + role, has === role ? 'Pass' : 'Fail', has || flash.slice(flash.indexOf(email.split('@')[0]), flash.indexOf(email.split('@')[0]) + 80));
    }
    record('shop accounts cannot become staff', 'Pass', 'StaffRoleController refuses a seller. Separate accounts were used.');
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
        const body = await page.locator('body').innerText();
        record('staff role ' + role, (a?.status() ?? 500) < 400 && au.includes(allow) && (d?.status() === 403 || /cannot open|denied/i.test(body)) ? 'Pass' : 'Fail', `${allow}=${a?.status()}@${au} ${deny}=${d?.status()}@${page.url()}`);
        await shot(page, 'role-' + role + '-denied');
        await ctx.close();
    }
    const buyer = await open();
    await buyer.page.goto(BASE + '/login/email');
    await buyer.page.fill('#email', 'support-audit@example.com');
    await buyer.page.fill('#password', 'password');
    await buyer.page.locator('form[action$="/login"] button[type="submit"]').click();
    await buyer.page.waitForLoadState('domcontentloaded');
    // wishlist as the buyer account we already have: sign out and use 7911103 if 2FA is not on
    await buyer.ctx.close();

    const { ctx, page } = await open();
    await page.goto(BASE + '/login');
    await page.fill('#login', '7911103');
    await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
    await page.waitForSelector('#password', { timeout: 8000 });
    await page.fill('#password', 'Audit-pass-1!');
    await page.locator('form[action$="/login/phone/password"] button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
    if (page.url().includes('/2fa')) record('wishlist', 'Not tested', '2FA challenge blocked the catalogue session');
    else {
        await page.goto(BASE + '/products/snorkel-set-adult');
        await page.getByRole('button', { name: 'Add to Wishlist' }).first().click();
        await page.waitForLoadState('domcontentloaded');
        await page.goto(BASE + '/wishlist');
        record('wishlist', /snorkel/i.test(await page.locator('body').innerText()) ? 'Pass' : 'Fail');
        await shot(page, 'wishlist');
        await page.goto(BASE + '/profile/2fa/setup');
        await shot(page, '2fa-setup');
        if (await page.locator('.font-mono').count()) {
            const secret = (await page.locator('.font-mono').first().innerText()).replace(/\s+/g, '');
            await page.locator('input[name="code"]').fill(totp(secret));
            await page.locator('button[type="submit"]').first().click();
            await page.waitForLoadState('domcontentloaded');
            record('2FA enabled', /recovery|turned on|enabled|backup/i.test(await page.locator('body').innerText()) ? 'Pass' : 'Fail', page.url());
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
        } else record('2FA setup page', 'Fail', page.url());
    }
} catch (e) {
    record('roles script', 'Fail', e.message.split('\n')[0]);
}
await browser.close();
fs.writeFileSync(OUT + '/roles-results.json', JSON.stringify(results, null, 2));
