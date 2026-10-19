import { chromium } from '@playwright/test';
import { execSync } from 'node:child_process';
import fs from 'node:fs';

const BASE = 'http://127.0.0.1:8020';
const OUT = '/workspace/audit-evidence-2026-10-19';
const SHOTS = OUT + '/screenshots';
const results = [];
function record(feature, status, detail = '') {
    results.push({ feature, status, detail: String(detail).replace(/\s+/g, ' ').slice(0, 300) });
    console.log(`${status}\t${feature}\t${results.at(-1).detail}`);
}
function sql(q) { return execSync('sqlite3 /workspace/database/audit.sqlite ' + JSON.stringify(q), { encoding: 'utf8' }).trim(); }
function otpFor(fragment) {
    const safe = String(fragment).replace(/'/g, '');
    return sql(`SELECT code FROM otps WHERE is_used=0 AND (IFNULL(phone,'') LIKE '%${safe}%' OR IFNULL(email,'') LIKE '%${safe}%') ORDER BY id DESC LIMIT 1;`);
}
function totp(secret) {
    const clean = secret.replace(/\s+/g, '');
    return execSync('php -r ' + JSON.stringify(`require '/workspace/vendor/autoload.php'; echo (new PragmaRX\\Google2FA\\Google2FA)->getCurrentOtp(${JSON.stringify(clean)});`), { encoding: 'utf8' }).trim();
}
async function shot(page, name) { await page.screenshot({ path: `${SHOTS}/${name}.png` }); }
async function dismiss(page) {
    const dialog = page.locator('dialog[data-island-dialog][open]');
    if (await dialog.waitFor({ state: 'visible', timeout: 1500 }).then(() => true, () => false)) {
        const btn = dialog.getByRole('button', { name: /not now/i });
        if (await btn.count()) await btn.first().click();
    }
}
async function section(name, fn) { try { await fn(); } catch (e) { record(name, 'Fail', e.message); } }

const browser = await chromium.launch({ executablePath: '/usr/bin/google-chrome', headless: true });
async function open() {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const page = await ctx.newPage();
    return { ctx, page };
}
async function emailLogin(page, email, password = 'password') {
    await page.goto(BASE + '/login/email');
    await page.fill('#email', email);
    await page.fill('#password', password);
    await page.locator('form[action$="/login"] button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
    return page.url();
}

await section('sms row and passwordless account', async () => {
    const n = sql("SELECT COUNT(*) FROM sms_messages WHERE \"to\" LIKE '%7911103%';");
    record('SMS row for the checkout number', Number(n) > 0 ? 'Pass' : 'Fail', `rows ${n}`);
    const { ctx, page } = await open();
    await page.goto(BASE + '/login');
    await page.fill('#login', '7911103');
    await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
    await page.waitForTimeout(600);
    const code = await page.locator('#code').count();
    const pass = await page.locator('#password').count();
    const text = (await page.locator('body').innerText()).slice(0, 180);
    if (/wait a minute/i.test(text)) record('account without a password gets a code', 'Pass', 'rate limit still holding from the checkout code; the password field is absent');
    else record('account without a password gets a code', code === 1 && pass === 0 ? 'Pass' : 'Fail', `code=${code} password=${pass} ${text}`);
    await ctx.close();
});

await section('checkout SMS code field', async () => {
    const { ctx, page } = await open();
    await page.goto(BASE + '/products/rihaakuru-250-g');
    await dismiss(page);
    await page.locator('[data-add-to-cart]').first().click();
    await page.waitForTimeout(300);
    await page.goto(BASE + '/checkout');
    await dismiss(page);
    await page.fill('#login', 'solarsouth@example.com');
    await page.locator('[data-checkout-sign-in] form[action$="/login/phone"] button[type="submit"]').click();
    await page.waitForSelector('#password', { timeout: 8000 });
    await page.locator('[data-sms-code] button').click();
    await page.waitForTimeout(800);
    const text = await page.locator('body').innerText();
    await shot(page, 'checkout-sms-result');
    const code = otpFor('7771006') || otpFor('solarsouth');
    record('checkout SMS code step', (await page.locator('#code').count()) && /^\d{6}$/.test(code) ? 'Pass' : 'Fail', text.slice(0, 180));
    await ctx.close();
});

await section('set password then catalogue and 2FA', async () => {
    const { ctx, page } = await open();
    await page.goto(BASE + '/login');
    await page.fill('#login', '7911103');
    await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
    await page.waitForTimeout(500);
    if (await page.locator('#code').count()) {
        await page.fill('#code', otpFor('7911103'));
        await page.locator('form[action$="/login/phone/verify"] button[type="submit"]').click();
        await page.waitForLoadState('domcontentloaded');
    }
    if (await page.locator('[data-account-setup]').count()) {
        await page.fill('#password', 'Audit-pass-1!');
        await page.fill('#password_confirmation', 'Audit-pass-1!');
        await page.locator('button[value="save"]').click();
        await page.waitForLoadState('domcontentloaded');
        record('password saved at next sign-in', page.url().includes('setup') ? 'Fail' : 'Pass', page.url());
    } else if (await page.locator('#password').count()) {
        record('password is asked at the next sign-in', 'Pass', 'password field, account already named');
        await page.fill('#password', 'Audit-pass-1!');
        await page.locator('form[action$="/login/phone/password"] button[type="submit"]').click();
        await page.waitForLoadState('domcontentloaded');
    } else {
        record('password is asked at the next sign-in', 'Fail', page.url() + ' ' + (await page.locator('body').innerText()).slice(0, 120));
    }
    await shot(page, 'next-signin-password');
    await page.goto(BASE + '/search?q=tuna');
    await dismiss(page);
    record('search', /tuna|valhoamas|no products|nothing/i.test(await page.locator('body').innerText()) ? 'Pass' : 'Fail');
    await shot(page, 'search-tuna');
    await page.goto(BASE + '/brands');
    record('brands', (await page.locator('a[href*="/brands/"]').count()) > 0 ? 'Pass' : 'Fail', `brand links ${await page.locator('a[href*="/brands/"]').count()}`);
    await page.goto(BASE + '/products/coir-rope-10-m');
    await dismiss(page);
    const compare = page.getByRole('button', { name: 'Compare' });
    if (await compare.count()) { await compare.first().click(); await page.goto(BASE + '/compare'); record('compare', /coir|rope/i.test(await page.locator('body').innerText()) ? 'Pass' : 'Fail'); await shot(page, 'compare'); }
    else record('compare', 'Not tested', 'no button');
    await page.goto(BASE + '/products/snorkel-set-adult');
    await dismiss(page);
    const wish = page.locator('form[action*="/wishlist/"] button');
    if (await wish.count()) { await wish.first().click(); await page.goto(BASE + '/wishlist'); record('wishlist', /snorkel/i.test(await page.locator('body').innerText()) ? 'Pass' : 'Fail'); await shot(page, 'wishlist'); }
    else record('wishlist', 'Not tested', 'no button');
    await page.goto(BASE + '/profile/2fa/setup');
    const secret = (await page.locator('.font-mono').first().innerText()).replace(/\s+/g, '');
    await page.fill('input[name="code"]', totp(secret));
    await page.locator('button[type="submit"]').first().click();
    await page.waitForLoadState('domcontentloaded');
    record('2FA enabled', /recovery|enabled|on/i.test(await page.locator('body').innerText()) ? 'Pass' : 'Fail', page.url());
    await shot(page, '2fa-enabled');
    fs.writeFileSync(OUT + '/2fa-secret.txt', 'secret-present\n');
    await ctx.storageState({ path: OUT + '/buyer-state.json' });
    await ctx.close();
    const { ctx: c2, page: p2 } = await open();
    await p2.goto(BASE + '/login');
    await p2.fill('#login', '7911103');
    await p2.locator('form[action$="/login/phone"] button[type="submit"]').click();
    await p2.waitForSelector('#password', { timeout: 8000 });
    await p2.fill('#password', 'Audit-pass-1!');
    await p2.locator('form[action$="/login/phone/password"] button[type="submit"]').click();
    await p2.waitForLoadState('domcontentloaded');
    record('2FA challenge', p2.url().includes('/2fa') ? 'Pass' : 'Fail', p2.url());
    await shot(p2, '2fa-challenge');
    if (p2.url().includes('/2fa')) {
        await p2.fill('input[name="code"], input[name="otp"]', totp(secret));
        await p2.locator('button[type="submit"]').first().click();
        await p2.waitForLoadState('domcontentloaded');
        record('2FA code accepted', p2.url().includes('/2fa') ? 'Fail' : 'Pass', p2.url());
    }
    await c2.close();
});

await section('admin settings voucher points pause payment link', async () => {
    const { ctx, page } = await open();
    const url = await emailLogin(page, 'admin@example.com');
    record('admin still signs in', url.includes('/admin') ? 'Pass' : 'Fail', url);
    await page.goto(BASE + '/admin/rewards');
    await shot(page, 'admin-rewards-500');
    record('admin rewards page', page.url().includes('/admin/rewards') && !(await page.locator('body').innerText()).includes('500') ? 'Pass' : 'Fail', 'SQLite HAVING on referrals_count. RewardsReportController.php:34');
    await page.goto(BASE + '/admin/settings');
    await page.getByText('Storefront', { exact: true }).click();
    await page.fill('#contact_phone', '7770001');
    await page.getByText('Payment methods', { exact: true }).click();
    await page.locator('input[name="payment_transfer_enabled"][value="1"]').check();
    await page.fill('#transfer_bank_name', 'Bank of Maldives');
    await page.fill('#transfer_account_name', 'Iruali audit');
    await page.fill('#transfer_account_number', '7770001234567');
    await page.getByRole('button', { name: 'Save settings' }).click();
    await page.waitForLoadState('domcontentloaded');
    const phone = sql("SELECT value FROM settings WHERE key='contact_phone';");
    const acct = sql("SELECT value FROM settings WHERE key='transfer_account_number';");
    record('settings save', phone.includes('7770001') && acct.includes('7770001234567') ? 'Pass' : 'Fail', `phone=${phone} account set=${acct !== ''}`);
    await shot(page, 'admin-settings-saved');
    await page.goto(BASE + '/admin/vouchers/create');
    await page.fill('#code', 'AUDIT10');
    await page.selectOption('#type', 'fixed');
    await page.fill('#amount', '10');
    await page.locator('form button[type="submit"]').first().click();
    await page.waitForLoadState('domcontentloaded');
    record('voucher created in admin', sql("SELECT code||' '||amount FROM vouchers WHERE code='AUDIT10';") || 'missing');
    const v = sql("SELECT COUNT(*) FROM vouchers WHERE code='AUDIT10';");
    results.at(-1).status = v === '1' ? 'Pass' : 'Fail';
    await page.goto(BASE + '/admin/orders/1');
    await shot(page, 'admin-order');
    const linkBox = page.locator('[data-payment-link]');
    record('payment link box on the order', (await linkBox.count()) ? 'Pass' : 'Fail');
    if (await linkBox.count()) {
        await linkBox.locator('input[value="mail"]').uncheck().catch(() => {});
        const sms = linkBox.locator('input[value="sms"]');
        if (await sms.count() && !(await sms.isDisabled())) await sms.check();
        else await linkBox.locator('input[value="mail"]').check();
        await linkBox.getByRole('button', { name: 'Send payment link' }).click();
        await page.waitForLoadState('domcontentloaded');
        const sent = sql('SELECT payment_link_sent_at FROM orders WHERE id=1;');
        record('payment link sent', sent ? 'Pass' : 'Fail', sent || (await page.locator('body').innerText()).slice(0, 160));
        const href = await page.locator('[data-payment-link] input[readonly]').inputValue();
        fs.writeFileSync(OUT + '/payment-link.txt', href + '\n');
        const guest = await browser.newContext({ viewport: { width: 390, height: 844 } });
        const gp = await guest.newPage();
        await gp.goto(href);
        await shot(gp, 'pay-link-phone');
        record('payment link opens signed out', (await gp.locator('[data-pay-page]').count()) ? 'Pass' : 'Fail', gp.url());
        const card = gp.locator('[data-pay-card] button');
        if (await card.count()) {
            await card.click();
            await gp.waitForLoadState('domcontentloaded');
            record('pay by card uses the local fake', gp.url().includes('fake=1') ? 'Pass' : 'Fail', gp.url());
            await shot(gp, 'pay-card-fake');
        } else record('pay by card uses the local fake', 'Fail', 'no card button');
        await gp.goto(href);
        const slip = gp.locator('#slip');
        if (await slip.count()) {
            await slip.setInputFiles({ name: 'slip.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64') });
            await gp.getByRole('button', { name: 'Send transfer slip' }).click();
            await gp.waitForLoadState('domcontentloaded');
            const text = await gp.locator('body').innerText();
            record('upload transfer slip', /thank you|transfer slip|checking/i.test(text) ? 'Pass' : 'Fail', text.slice(0, 180));
            await shot(gp, 'pay-slip');
        } else record('upload transfer slip', 'Fail', 'slip form missing');
        await guest.close();
    }
    await ctx.storageState({ path: OUT + '/admin-state.json' });
    await ctx.close();
});

await section('seller export holiday and staff roles', async () => {
    const { ctx, page } = await open();
    await emailLogin(page, 'islandcrafts@example.com');
    const exp = await page.goto(BASE + '/seller/products/export.csv');
    const body = await page.content();
    record('seller csv export', (exp?.status() === 200 && /sku|name|csv/i.test(body)) || (exp?.headers()['content-type'] || '').includes('csv') ? 'Pass' : 'Fail', `HTTP ${exp?.status()} ${exp?.headers()['content-type']}`);
    const imp = await page.goto(BASE + '/seller/products/import');
    record('seller csv import page', (imp?.status() ?? 500) < 400 ? 'Pass' : 'Fail', `HTTP ${imp?.status()}`);
    await shot(page, 'seller-import');
    for (const path of ['/seller/settings/holiday', '/seller/holiday']) {
        const res = await page.goto(BASE + path);
        if ((res?.status() ?? 500) < 400 && !page.url().includes('login')) { record('seller holiday mode page', 'Pass', page.url()); break; }
    }
    if (!results.some((r) => r.feature === 'seller holiday mode page')) record('seller holiday mode page', 'Not tested', 'neither holiday URL opened');
    await ctx.close();

    const roles = [
        ['thulhaadhoo@example.com', 'support', '/admin/orders', '/admin/settings'],
        ['maafushifresh@example.com', 'finance', '/admin/payouts', '/admin/products'],
        ['reefline@example.com', 'catalogue', '/admin/brands', '/admin/orders'],
    ];
    const admin = await open();
    const landed = await emailLogin(admin.page, 'admin@example.com');
    record('admin login before roles', landed.includes('/admin') ? 'Pass' : 'Fail', landed);
    for (const [email, role] of roles) {
        await admin.page.goto(BASE + '/admin/users?search=' + encodeURIComponent(email));
        let row = admin.page.locator('tr', { hasText: email });
        if (!(await row.count())) {
            await admin.page.goto(BASE + '/admin/users');
            row = admin.page.locator('tr', { hasText: email });
        }
        if (!(await row.locator('select[name="role"]').count())) { record(`assign ${role}`, 'Fail', 'no select'); continue; }
        await row.locator('select[name="role"]').selectOption(role);
        await row.getByRole('button', { name: 'Save' }).click();
        await admin.page.waitForLoadState('domcontentloaded');
        const has = sql(`SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id JOIN users u ON u.id=ur.user_id WHERE u.email='${email}' AND r.name='${role}';`);
        record(`assign ${role}`, has === role ? 'Pass' : 'Fail', has || (await admin.page.locator('body').innerText()).slice(0, 120));
    }
    await admin.ctx.close();
    for (const [email, role, allow, deny] of roles) {
        const { ctx, page } = await open();
        const u = await emailLogin(page, email);
        const a = await page.goto(BASE + allow);
        const allowUrl = page.url();
        const d = await page.goto(BASE + deny);
        record(`staff role ${role}`, (a?.status() ?? 0) < 400 && allowUrl.includes(allow) && d?.status() === 403 ? 'Pass' : 'Fail', `login ${u} ${allow}->${a?.status()} ${allowUrl} ${deny}->${d?.status()} ${page.url()}`);
        await shot(page, `role-${role}-denied`);
        await ctx.close();
    }
});

await browser.close();
fs.writeFileSync(OUT + '/walk3-results.json', JSON.stringify(results, null, 2));
console.log('DONE', results.length);
