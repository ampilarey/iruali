import { chromium } from '@playwright/test';
import { execSync } from 'node:child_process';
import fs from 'node:fs';

const BASE = 'http://127.0.0.1:8020';
const OUT = '/workspace/audit-evidence-2026-10-19';
const SHOTS = OUT + '/screenshots';
const results = [];
const record = (feature, status, detail = '') => {
    results.push({ feature, status, detail: String(detail).replace(/\s+/g, ' ').slice(0, 300) });
    console.log(`${status}\t${feature}\t${results.at(-1).detail}`);
};
const sql = (q) => execSync('sqlite3 /workspace/database/audit.sqlite ' + JSON.stringify(q), { encoding: 'utf8' }).trim();
const otpFor = (f) => sql(`SELECT code FROM otps WHERE is_used=0 AND (IFNULL(phone,'') LIKE '%${f}%' OR IFNULL(email,'') LIKE '%${f}%') ORDER BY id DESC LIMIT 1;`);
const totp = (secret) => execSync('php -r ' + JSON.stringify(`require '/workspace/vendor/autoload.php'; echo (new PragmaRX\\Google2FA\\Google2FA)->getCurrentOtp(${JSON.stringify(secret.replace(/\s+/g, ''))});`), { encoding: 'utf8' }).trim();
const shot = (page, name) => page.screenshot({ path: `${SHOTS}/${name}.png` });
async function dismiss(page) {
    const d = page.locator('dialog[data-island-dialog][open]');
    if (await d.waitFor({ state: 'visible', timeout: 1200 }).then(() => true, () => false)) {
        const b = d.getByRole('button', { name: /not now/i });
        if (await b.count()) await b.first().click();
    }
}
async function section(name, fn) { try { await fn(); } catch (e) { record(name, 'Fail', e.message.split('\n')[0]); } }
const browser = await chromium.launch({ executablePath: '/usr/bin/google-chrome', headless: true });
async function open(opts = {}) {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, acceptDownloads: true, ...opts });
    return { ctx, page: await ctx.newPage() };
}

await section('password at next sign-in', async () => {
    const { ctx, page } = await open();
    await page.goto(BASE + '/login');
    await page.fill('#login', '7911103');
    await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
    await page.waitForTimeout(700);
    let text = await page.locator('[role="alert"], .text-red-700, .text-red-600').allInnerTexts().catch(() => []);
    if (/wait a minute/i.test(text.join(' ') + await page.locator('body').innerText())) {
        record('rate limit still active, waiting', 'Pass', '60s');
        await page.waitForTimeout(61000);
        await page.goto(BASE + '/login');
        await page.fill('#login', '7911103');
        await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
        await page.waitForTimeout(700);
    }
    if (await page.locator('#code').count()) {
        await page.fill('#code', otpFor('7911103'));
        await page.locator('form[action$="/login/phone/verify"] button[type="submit"]').click();
        await page.waitForLoadState('domcontentloaded');
    }
    await shot(page, 'next-signin-password');
    const setup = (await page.locator('[data-account-setup]').count()) > 0;
    record('password is asked at the next sign-in', setup ? 'Pass' : 'Fail', page.url());
    if (setup) {
        await page.fill('#password', 'Audit-pass-1!');
        await page.fill('#password_confirmation', 'Audit-pass-1!');
        await page.locator('button[value="save"]').click();
        await page.waitForLoadState('domcontentloaded');
        record('password saved', sql("SELECT has_password FROM users WHERE phone LIKE '%7911103%';") === '1' ? 'Pass' : 'Fail', page.url());
    }
    await ctx.close();
});

await section('checkout SMS for a verified phone', async () => {
    const { ctx, page } = await open();
    await page.goto(BASE + '/products/rihaakuru-250-g');
    await dismiss(page);
    await page.locator('[data-add-to-cart]').first().click();
    await page.waitForTimeout(400);
    await page.goto(BASE + '/checkout');
    await dismiss(page);
    await page.fill('#login', '7911103');
    await page.locator('[data-checkout-sign-in] form[action$="/login/phone"] button[type="submit"]').click();
    await page.waitForSelector('#password', { timeout: 8000 });
    record('Text me a code instead at checkout for a verified number', (await page.locator('[data-sms-code]').count()) > 0 ? 'Pass' : 'Fail');
    await page.locator('[data-sms-code] button').click();
    await page.waitForTimeout(800);
    await shot(page, 'checkout-sms-verified');
    const code = otpFor('7911103');
    record('checkout SMS code is sent when the phone is verified', (await page.locator('#code').count()) && /^\d{6}$/.test(code) ? 'Pass' : 'Fail', (await page.locator('body').innerText()).slice(0, 160));
    await ctx.close();
});

await section('wishlist and 2FA', async () => {
    const { ctx, page } = await open();
    await page.goto(BASE + '/login');
    await page.fill('#login', '7911103');
    await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
    await page.waitForSelector('#password');
    await page.fill('#password', 'Audit-pass-1!');
    await page.locator('form[action$="/login/phone/password"] button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
    record('buyer password sign-in', page.url().includes('/login') ? 'Fail' : 'Pass', page.url());
    await page.goto(BASE + '/products/snorkel-set-adult');
    await dismiss(page);
    const wish = page.locator('form[action*="wishlist"] button, button[aria-label*="wishlist" i], a[href*="wishlist"]').first();
    if (await wish.count()) await wish.click();
    await page.goto(BASE + '/wishlist');
    await dismiss(page);
    const text = await page.locator('body').innerText();
    record('wishlist', /snorkel/i.test(text) ? 'Pass' : 'Fail', text.slice(0, 120));
    await shot(page, 'wishlist');
    await page.goto(BASE + '/profile/2fa/setup');
    await shot(page, '2fa-setup');
    const mono = page.locator('.font-mono');
    if (await mono.count()) {
        const secret = (await mono.first().innerText()).replace(/\s+/g, '');
        await page.fill('input[name="code"]', totp(secret));
        await page.locator('button[type="submit"]').first().click();
        await page.waitForLoadState('domcontentloaded');
        record('2FA enabled', /recovery|turned on|enabled/i.test(await page.locator('body').innerText()) ? 'Pass' : 'Fail', page.url());
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
            const field = c2.page.locator('input[name="code"], input[name="otp"], input[name="one_time_password"]').first();
            await field.fill(totp(secret));
            await c2.page.locator('button[type="submit"]').first().click();
            await c2.page.waitForLoadState('domcontentloaded');
            record('2FA code accepted', c2.page.url().includes('/2fa') ? 'Fail' : 'Pass', c2.page.url());
        }
        await c2.ctx.close();
    } else record('2FA enabled', 'Fail', page.url() + ' no secret');
});

await section('settings and payment link', async () => {
    const { ctx, page } = await open();
    await page.goto(BASE + '/login/email');
    await page.fill('#email', 'admin@example.com');
    await page.fill('#password', 'password');
    await page.locator('form[action$="/login"] button[type="submit"]').click();
    await page.waitForURL(/\/admin/);
    await page.goto(BASE + '/admin/settings');
    await page.getByRole('button', { name: 'Storefront' }).click().catch(() => page.getByText('Storefront', { exact: true }).click());
    await page.fill('#contact_phone', '+9607770001');
    await page.getByText('Payment methods', { exact: true }).click();
    await page.locator('#payment_transfer_enabled, input[name="payment_transfer_enabled"][value="1"]').first().check();
    await page.fill('#transfer_bank_name', 'Bank of Maldives');
    await page.fill('#transfer_account_name', 'Iruali audit');
    await page.fill('#transfer_account_number', '7770001234567');
    await page.getByRole('button', { name: 'Save settings' }).click();
    await page.waitForLoadState('domcontentloaded');
    await shot(page, 'admin-settings-saved');
    const phone = sql("SELECT value FROM settings WHERE key='contact_phone';");
    const acct = sql("SELECT value FROM settings WHERE key='transfer_account_number';");
    record('settings save', phone.includes('7770001') && acct.includes('7770001234567') ? 'Pass' : 'Fail', `phone=${phone} acct=${acct}`);
    const link = execSync('/tmp/iruali-audit-env.sh php artisan tinker --execute=' + JSON.stringify("echo \\App\\Support\\PayLink::url(\\App\\Models\\Order::find(1));"), { encoding: 'utf8' }).trim().split('\n').pop();
    fs.writeFileSync(OUT + '/payment-link.txt', link + '\n');
    const guest = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const gp = await guest.newPage();
    const res = await gp.goto(link);
    record('payment link opens signed out on a phone', (res?.status() === 200 && (await gp.locator('[data-pay-page]').count())) ? 'Pass' : 'Fail', `HTTP ${res?.status()}`);
    await shot(gp, 'pay-link-phone');
    await gp.locator('[data-pay-card] button').click();
    await gp.waitForLoadState('domcontentloaded');
    record('pay by card follows the local fake', gp.url().includes('fake=1') ? 'Pass' : 'Fail', gp.url());
    await shot(gp, 'pay-card-fake');
    await gp.goto(link);
    if (await gp.locator('#slip').count()) {
        await gp.locator('#slip').setInputFiles({ name: 'slip.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64') });
        await gp.getByRole('button', { name: 'Send transfer slip' }).click();
        await gp.waitForLoadState('domcontentloaded');
        const body = await gp.locator('body').innerText();
        record('upload transfer slip', /thank you|checking|slip/i.test(body) ? 'Pass' : 'Fail', body.slice(0, 180));
        await shot(gp, 'pay-slip');
    } else record('upload transfer slip', 'Fail', 'no slip form');
    await guest.close();
    await ctx.close();
});

await section('seller export and staff roles', async () => {
    const seller = await open();
    await seller.page.goto(BASE + '/login/email');
    await seller.page.fill('#email', 'islandcrafts@example.com');
    await seller.page.fill('#password', 'password');
    await seller.page.locator('form[action$="/login"] button[type="submit"]').click();
    await seller.page.waitForLoadState('domcontentloaded');
    const [download] = await Promise.all([
        seller.page.waitForEvent('download', { timeout: 8000 }),
        seller.page.goto(BASE + '/seller/products/export.csv'),
    ]);
    const suggested = download.suggestedFilename();
    record('seller csv export', /csv/i.test(suggested) ? 'Pass' : 'Fail', suggested);
    const imp = await seller.page.goto(BASE + '/seller/products/import');
    record('seller csv import page', (imp?.status() ?? 500) < 400 ? 'Pass' : 'Fail', `HTTP ${imp?.status()}`);
    await shot(seller.page, 'seller-import');
    const hol = await seller.page.goto(BASE + '/seller/settings/holiday');
    record('seller holiday page', (hol?.status() ?? 500) < 400 ? 'Pass' : 'Fail', seller.page.url());
    await seller.ctx.close();

    const roles = [
        ['thulhaadhoo@example.com', 'support', '/admin/orders', '/admin/settings'],
        ['maafushifresh@example.com', 'finance', '/admin/payouts', '/admin/products'],
        ['reefline@example.com', 'catalogue', '/admin/brands', '/admin/orders'],
    ];
    const admin = await open();
    await admin.page.goto(BASE + '/login/email');
    await admin.page.fill('#email', 'admin@example.com');
    await admin.page.fill('#password', 'password');
    await admin.page.locator('form[action$="/login"] button[type="submit"]').click();
    await admin.page.waitForURL(/\/admin/);
    for (const [email, role] of roles) {
        await admin.page.goto(BASE + '/admin/users');
        const row = admin.page.locator('tr', { hasText: email });
        await row.locator('select[name="role"]').selectOption(role);
        await row.getByRole('button', { name: 'Save' }).click();
        await admin.page.waitForLoadState('domcontentloaded');
        const has = sql(`SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id JOIN users u ON u.id=ur.user_id WHERE u.email='${email}' AND r.name='${role}';`);
        record(`assign ${role}`, has === role ? 'Pass' : 'Fail', has || 'not saved');
    }
    await admin.ctx.close();
    for (const [email, role, allow, deny] of roles) {
        const { ctx, page } = await open();
        await page.goto(BASE + '/login/email');
        await page.fill('#email', email);
        await page.fill('#password', 'password');
        await page.locator('form[action$="/login"] button[type="submit"]').click();
        await page.waitForLoadState('domcontentloaded');
        const loginUrl = page.url();
        const a = await page.goto(BASE + allow);
        const allowUrl = page.url();
        const d = await page.goto(BASE + deny);
        record(`staff role ${role}`, (a?.status() ?? 500) < 400 && allowUrl.includes(allow) && d?.status() === 403 ? 'Pass' : 'Fail', `after login ${loginUrl}; ${allow}=${a?.status()}@${allowUrl}; ${deny}=${d?.status()}@${page.url()}`);
        await shot(page, `role-${role}-denied`);
        await ctx.close();
    }
});

await browser.close();
fs.writeFileSync(OUT + '/walk4-results.json', JSON.stringify(results, null, 2));
console.log('DONE', results.length);
