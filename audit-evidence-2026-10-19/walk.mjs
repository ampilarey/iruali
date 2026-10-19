import { chromium } from '@playwright/test';
import { execSync } from 'node:child_process';
import fs from 'node:fs';

const BASE = 'http://127.0.0.1:8020';
const OUT = '/workspace/audit-evidence-2026-10-19';
const SHOTS = OUT + '/screenshots';
fs.mkdirSync(SHOTS, { recursive: true });

const results = [];
const consoleLog = [];

function record(feature, status, detail = '') {
    results.push({ feature, status, detail });
    console.log(`${status}\t${feature}${detail ? '\t' + detail : ''}`);
}

function sql(q) {
    return execSync('sqlite3 /workspace/database/audit.sqlite ' + JSON.stringify(q), { encoding: 'utf8' }).trim();
}

function otpFor(fragment) {
    const safe = String(fragment).replace(/'/g, '');
    return sql(`SELECT code FROM otps WHERE is_used=0 AND (IFNULL(phone,'') LIKE '%${safe}%' OR IFNULL(email,'') LIKE '%${safe}%') ORDER BY id DESC LIMIT 1;`);
}

function totp(secret) {
    const clean = secret.replace(/\s+/g, '');
    return execSync('php -r ' + JSON.stringify(
        `require '/workspace/vendor/autoload.php'; echo (new PragmaRX\\Google2FA\\Google2FA)->getCurrentOtp(${JSON.stringify(clean)});`
    ), { encoding: 'utf8' }).trim();
}

async function shot(page, name) {
    const file = `${SHOTS}/${name}.png`;
    await page.screenshot({ path: file, fullPage: false });
    return file;
}

async function dismissIsland(page) {
    const dialog = page.locator('dialog[data-island-dialog][open]');
    const opened = await dialog.waitFor({ state: 'visible', timeout: 2500 }).then(() => true, () => false);
    if (opened) {
        const btn = dialog.getByRole('button', { name: /not now|ނުވާނެ/i });
        if (await btn.count()) await btn.first().click();
    }
}

function watch(page, label) {
    page.on('console', (msg) => {
        if (msg.type() === 'error') consoleLog.push({ label, text: msg.text() });
    });
    page.on('pageerror', (err) => consoleLog.push({ label, text: 'pageerror: ' + err.message }));
}

async function newPage(browser, viewport, label) {
    const context = await browser.newContext({ viewport, locale: 'en-US' });
    const page = await context.newPage();
    watch(page, label);
    return { context, page };
}

async function bodyText(page) {
    return page.locator('body').innerText();
}

const browser = await chromium.launch({
    executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || '/usr/bin/google-chrome',
    headless: true,
});

const desktop = { width: 1280, height: 800 };
const phone = { width: 390, height: 844 };

try {
    // --- Public pages, both widths, EN and a Dhivehi sample ---
    const publicPaths = [
        ['home', '/'],
        ['products', '/products'],
        ['brands', '/brands'],
        ['search', '/search?q=coir'],
        ['product', '/products/coir-rope-10-m'],
        ['cart', '/cart'],
        ['login', '/login'],
        ['login-email', '/login/email'],
        ['checkout', '/checkout'],
        ['compare', '/compare'],
        ['wishlist', '/wishlist'],
        ['help', '/help'],
    ];
    for (const [name, path] of publicPaths) {
        for (const [widthName, viewport] of [['desktop', desktop], ['phone', phone]]) {
            const { context, page } = await newPage(browser, viewport, `${name}-${widthName}`);
            const res = await page.goto(BASE + path, { waitUntil: 'domcontentloaded' });
            await dismissIsland(page);
            await shot(page, `${widthName}-en-${name}`);
            record(`page ${path} ${widthName}`, (res?.status() ?? 0) < 400 ? 'Pass' : 'Fail', `HTTP ${res?.status()}`);
            await context.close();
        }
    }
    for (const [name, path] of [['home', '/dv'], ['login', '/dv/login'], ['product', '/dv/products/coir-rope-10-m'], ['cart', '/dv/cart'], ['checkout', '/dv/checkout']]) {
        for (const [widthName, viewport] of [['desktop', desktop], ['phone', phone]]) {
            const { context, page } = await newPage(browser, viewport, `dv-${name}-${widthName}`);
            const res = await page.goto(BASE + path, { waitUntil: 'domcontentloaded' });
            await dismissIsland(page);
            await shot(page, `${widthName}-dv-${name}`);
            const text = await bodyText(page);
            const hasThaana = /[\u0780-\u07BF]/.test(text);
            record(`dhivehi ${path} ${widthName}`, (res?.status() ?? 0) < 400 && hasThaana ? 'Pass' : 'Fail', `HTTP ${res?.status()} thaana=${hasThaana}`);
            await context.close();
        }
    }

    // Guest checkout must be 404
    {
        const { context, page } = await newPage(browser, desktop, 'guest-checkout');
        const res = await page.goto(BASE + '/checkout/guest');
        record('GET /checkout/guest is 404', res?.status() === 404 ? 'Pass' : 'Fail', `HTTP ${res?.status()}`);
        await shot(page, 'guest-checkout-404');
        await context.close();
    }

    // Social buttons hidden
    {
        const { context, page } = await newPage(browser, desktop, 'social');
        await page.goto(BASE + '/login');
        const text = await bodyText(page);
        const shown = /Continue with (Google|Facebook|Apple)/.test(text);
        record('social buttons hidden without keys', shown ? 'Fail' : 'Pass', shown ? 'a provider button is visible' : 'no provider buttons');
        await context.close();
    }

    // Unknown email and staff redirect
    {
        const { context, page } = await newPage(browser, desktop, 'unknown-email');
        await page.goto(BASE + '/login');
        await page.fill('#login', 'nobody-audit@example.com');
        await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
        await page.waitForTimeout(400);
        const text = await bodyText(page);
        const ok = text.includes('No account uses that email');
        record('unknown email told to use a mobile number', ok ? 'Pass' : 'Fail', text.slice(0, 240).replace(/\s+/g, ' '));
        await shot(page, 'login-unknown-email');
        await context.close();
    }
    {
        const { context, page } = await newPage(browser, desktop, 'staff-redirect');
        await page.goto(BASE + '/login');
        await page.fill('#login', 'admin@example.com');
        await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
        await page.waitForLoadState('domcontentloaded');
        const url = page.url();
        record('staff email sent to /login/email', url.includes('/login/email') ? 'Pass' : 'Fail', url);
        await shot(page, 'login-staff-redirect');
        await context.close();
    }

    // SMS option must not be on /login for a password account
    {
        const { context, page } = await newPage(browser, desktop, 'login-no-sms');
        await page.goto(BASE + '/login');
        await page.fill('#login', '7771001');
        await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
        await page.waitForSelector('#password', { timeout: 8000 });
        const sms = await page.locator('[data-sms-code]').count();
        const email = await page.locator('[data-email-code]').count();
        const forgot = await page.getByRole('button', { name: 'Forgot password?' }).count();
        record('password account shows password step', 'Pass', 'password field visible');
        record('Email me a code instead on /login', email ? 'Pass' : 'Fail', `count ${email}`);
        record('Text me a code instead absent on /login', sms === 0 ? 'Pass' : 'Fail', `count ${sms}`);
        record('Forgot password button on password step', forgot ? 'Pass' : 'Fail', `count ${forgot}`);
        await shot(page, 'login-password-step');
        await context.close();
    }

    // Email code for a password account (seller phone has email)
    {
        const { context, page } = await newPage(browser, desktop, 'email-code');
        await page.goto(BASE + '/login');
        await page.fill('#login', '7771001');
        await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
        await page.waitForSelector('#password');
        await page.locator('[data-email-code] button').click();
        await page.waitForSelector('#code', { timeout: 8000 });
        const code = otpFor('islandcrafts@example.com');
        record('email code stored', code.length === 6 ? 'Pass' : 'Fail', code ? '6 digits in otps' : 'no otp row');
        if (code) {
            await page.fill('#code', code);
            await page.locator('form[action$="/login/phone/verify"] button[type="submit"]').click();
            await page.waitForLoadState('domcontentloaded');
            record('email code signs the seller in', !page.url().includes('/login') ? 'Pass' : 'Fail', page.url());
            await shot(page, 'login-email-code-done');
        }
        await context.close();
    }

    // Forgot password sends an SMS
    {
        const { context, page } = await newPage(browser, desktop, 'forgot');
        await page.goto(BASE + '/login');
        await page.fill('#login', '7771002');
        await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
        await page.waitForSelector('#password');
        await page.getByRole('button', { name: 'Forgot password?' }).click();
        await page.waitForSelector('#code', { timeout: 8000 });
        const text = await bodyText(page);
        const code = otpFor('7771002');
        const smsRow = sql("SELECT status FROM sms_messages ORDER BY id DESC LIMIT 1;");
        record('forgot password asks for a code', text.toLowerCase().includes('texted') || text.toLowerCase().includes('code') ? 'Pass' : 'Fail', text.slice(0, 200).replace(/\s+/g, ' '));
        record('forgot password code stored', code.length === 6 ? 'Pass' : 'Fail', `sms status ${smsRow}`);
        await shot(page, 'login-forgot-code');
        await context.close();
    }

    // New number: SMS, account, set password later
    {
        const phoneNo = '7911101';
        const { context, page } = await newPage(browser, desktop, 'new-number');
        await page.goto(BASE + '/login');
        await page.fill('#login', phoneNo);
        await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
        await page.waitForTimeout(800);
        const text1 = await bodyText(page);
        if (await page.locator('#code').count() === 0) {
            record('new number gets an SMS code', 'Fail', text1.slice(0, 300).replace(/\s+/g, ' '));
        } else {
            const code = otpFor(phoneNo);
            record('new number gets an SMS code', code.length === 6 ? 'Pass' : 'Fail', code ? 'otp stored' : 'no otp');
            await page.fill('#code', code);
            await page.locator('form[action$="/login/phone/verify"] button[type="submit"]').click();
            await page.waitForLoadState('domcontentloaded');
            const onSetup = page.url().includes('/account/setup') || (await page.locator('[data-account-setup]').count()) > 0;
            record('new account reaches set your name and password', onSetup ? 'Pass' : 'Fail', page.url());
            await shot(page, 'account-setup');
            if (onSetup) {
                await page.locator('[data-setup-later]').click();
                await page.waitForLoadState('domcontentloaded');
                record('Set a password later continues', !page.url().includes('/account/setup') ? 'Pass' : 'Fail', page.url());
            }
        }
        await context.close();
    }

    // Account without a password gets SMS (the one we just created)
    {
        const phoneNo = '7911101';
        const { context, page } = await newPage(browser, desktop, 'no-password');
        await page.goto(BASE + '/login');
        await page.fill('#login', phoneNo);
        await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
        await page.waitForTimeout(800);
        const hasCode = await page.locator('#code').count();
        const hasPassword = await page.locator('#password').count();
        record('account without a password gets a code not a password', hasCode === 1 && hasPassword === 0 ? 'Pass' : 'Fail', `code=${hasCode} password=${hasPassword}`);
        await context.close();
    }

    // Rate limit: 1 a minute
    {
        const phoneNo = '7911102';
        const { context, page } = await newPage(browser, desktop, 'rate');
        await page.goto(BASE + '/login');
        await page.fill('#login', phoneNo);
        await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
        await page.waitForSelector('#code', { timeout: 8000 });
        await page.goto(BASE + '/login?change=1');
        await page.fill('#login', phoneNo);
        await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
        await page.waitForTimeout(500);
        const text = await bodyText(page);
        record('second code within a minute is refused', /wait a minute/i.test(text) ? 'Pass' : 'Fail', text.slice(0, 220).replace(/\s+/g, ' '));
        await shot(page, 'login-rate-limit');
        await context.close();
    }

    // 5 wrong password tries
    {
        const { context, page } = await newPage(browser, desktop, 'wrong');
        await page.goto(BASE + '/login');
        await page.fill('#login', '7771003');
        await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
        await page.waitForSelector('#password');
        let locked = false;
        for (let i = 0; i < 6; i++) {
            await page.fill('#password', 'wrong-pass-' + i);
            await page.locator('form[action$="/login/phone/password"] button[type="submit"]').click();
            await page.waitForTimeout(300);
            const text = await bodyText(page);
            if (/Too many wrong tries/i.test(text)) { locked = true; break; }
        }
        record('five wrong passwords lock the attempt', locked ? 'Pass' : 'Fail', locked ? 'lock message shown' : 'no lock message after 6 tries');
        await shot(page, 'login-wrong-tries');
        await context.close();
    }

    // Checkout signed out: sign-in box beside summary. SMS option for a registered customer.
    {
        const { context, page } = await newPage(browser, desktop, 'checkout-signed-out');
        await page.goto(BASE + '/products/coir-rope-10-m');
        await dismissIsland(page);
        await page.locator('[data-add-to-cart]').first().click();
        await page.waitForTimeout(600);
        await page.goto(BASE + '/checkout');
        await dismissIsland(page);
        const text = await bodyText(page);
        const box = await page.locator('[data-checkout-sign-in]').count();
        record('signed-out checkout shows the sign-in box', box > 0 ? 'Pass' : 'Fail', text.slice(0, 180).replace(/\s+/g, ' '));
        await shot(page, 'checkout-signed-out');
        await page.fill('#login', '7771004');
        await page.locator('[data-checkout-sign-in] form[action$="/login/phone"] button[type="submit"]').click();
        await page.waitForSelector('#password', { timeout: 8000 });
        const sms = await page.locator('[data-sms-code]').count();
        record('Text me a code instead appears at checkout', sms > 0 ? 'Pass' : 'Fail', `count ${sms}`);
        await shot(page, 'checkout-sms-option');
        await page.locator('[data-sms-code] button').click();
        await page.waitForSelector('#code', { timeout: 8000 });
        const code = otpFor('7771004');
        record('checkout SMS code stored', code.length === 6 ? 'Pass' : 'Fail');
        await context.close();
    }

    // First-time number buys, name asked, cart follows, password at next sign-in
    let buyerPhone = '7911103';
    {
        const { context, page } = await newPage(browser, desktop, 'first-buy');
        await page.goto(BASE + '/products/cotton-sarong');
        await dismissIsland(page);
        await page.locator('[data-add-to-cart]').first().click();
        await page.waitForTimeout(500);
        await page.goto(BASE + '/checkout');
        await dismissIsland(page);
        await page.fill('#login', buyerPhone);
        await page.locator('[data-checkout-sign-in] form[action$="/login/phone"] button[type="submit"]').click();
        await page.waitForSelector('#code', { timeout: 8000 });
        const code = otpFor(buyerPhone);
        await page.fill('#code', code);
        await page.locator('form[action$="/login/phone/verify"] button[type="submit"]').click();
        await page.waitForLoadState('domcontentloaded');
        await dismissIsland(page);
        const nameField = page.locator('#customer_name');
        record('checkout asks Your name for a first-time number', await nameField.count() ? 'Pass' : 'Fail', page.url());
        await shot(page, 'checkout-your-name');
        if (await nameField.count()) {
            await nameField.fill('Audit Buyer');
            await page.fill('#shipping_address', 'Audit house');
            await page.fill('#shipping_phone', buyerPhone);
            const island = page.locator('select[name="island_id"], #ship-island, [name="island_id"]').first();
            if (await island.count()) {
                const male = island.locator('option', { hasText: /Malé|Male/ }).first();
                if (await male.count()) await island.selectOption({ label: await male.innerText() }).catch(async () => {
                    await island.selectOption({ index: 1 });
                });
            }
            const pay = page.locator('input[name="payment_method"]').first();
            if (await pay.count()) await pay.check();
            const terms = page.locator('input[name="agree_terms"]');
            if (await terms.count()) await terms.check();
            await shot(page, 'checkout-filled');
            const place = page.getByRole('button', { name: /place order|pay now|confirm/i }).first();
            if (await place.count()) {
                await place.click();
                await page.waitForLoadState('domcontentloaded');
                await page.waitForTimeout(800);
            }
            const after = page.url();
            const orderCount = sql(`SELECT COUNT(*) FROM orders o JOIN users u ON u.id=o.user_id WHERE u.phone LIKE '%${buyerPhone}%';`);
            record('first-time number places an order', Number(orderCount) > 0 ? 'Pass' : 'Fail', `${after} orders=${orderCount}`);
            await shot(page, 'checkout-after-place');
        }
        await context.close();
    }
    {
        const { context, page } = await newPage(browser, desktop, 'next-signin');
        await page.goto(BASE + '/login');
        await page.fill('#login', buyerPhone);
        await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
        await page.waitForTimeout(600);
        if (await page.locator('#code').count()) {
            const code = otpFor(buyerPhone);
            await page.fill('#code', code);
            await page.locator('form[action$="/login/phone/verify"] button[type="submit"]').click();
            await page.waitForLoadState('domcontentloaded');
        }
        const onSetup = page.url().includes('setup') || (await page.locator('#password').count()) > 0 && (await page.locator('[data-account-setup]').count()) > 0;
        record('password is asked at the next sign-in', onSetup ? 'Pass' : 'Fail', page.url());
        await shot(page, 'next-signin-password');
        if (onSetup) {
            const name = page.locator('#name');
            if (await name.count() && !(await name.inputValue())) await name.fill('Audit Buyer');
            await page.fill('#password', 'Audit-pass-1!');
            await page.fill('#password_confirmation', 'Audit-pass-1!');
            await page.locator('button[value="save"]').click();
            await page.waitForLoadState('domcontentloaded');
            record('name and password saved', !page.url().includes('setup') ? 'Pass' : 'Fail', page.url());
        }
        await context.close();
    }

    // Cart follows the customer
    {
        const { context, page } = await newPage(browser, desktop, 'cart-merge');
        await page.goto(BASE + '/products/dry-bag-20-l');
        await dismissIsland(page);
        await page.locator('[data-add-to-cart]').first().click();
        await page.waitForTimeout(400);
        await page.goto(BASE + '/login/email');
        await page.fill('#email', 'admin@example.com');
        await page.fill('#password', 'password');
        await page.locator('form[action$="/login"] button[type="submit"]').click();
        await page.waitForLoadState('domcontentloaded');
        await page.goto(BASE + '/cart');
        await dismissIsland(page);
        const text = await bodyText(page);
        record('cart follows the customer after sign-in', /dry bag/i.test(text) ? 'Pass' : 'Fail', text.slice(0, 180).replace(/\s+/g, ' '));
        await shot(page, 'cart-after-signin');
        await context.close();
    }

    // Catalogue actions as the buyer with a password
    {
        const { context, page } = await newPage(browser, desktop, 'catalogue');
        await page.goto(BASE + '/login');
        await page.fill('#login', buyerPhone);
        await page.locator('form[action$="/login/phone"] button[type="submit"]').click();
        await page.waitForSelector('#password', { timeout: 8000 }).catch(() => {});
        if (await page.locator('#password').count()) {
            await page.fill('#password', 'Audit-pass-1!');
            await page.locator('form[action$="/login/phone/password"] button[type="submit"]').click();
            await page.waitForLoadState('domcontentloaded');
        }
        await page.goto(BASE + '/search?q=tuna');
        await dismissIsland(page);
        record('search', /tuna|valhoamas|no results/i.test(await bodyText(page)) ? 'Pass' : 'Fail', page.url());
        await shot(page, 'search-tuna');
        await page.goto(BASE + '/products?sort=price_asc');
        record('product filters page', page.url().includes('/products') ? 'Pass' : 'Fail', `HTTP page ${page.url()}`);
        await page.goto(BASE + '/brands');
        await shot(page, 'brands');
        record('brands index', (await bodyText(page)).length > 20 ? 'Pass' : 'Fail');
        await page.goto(BASE + '/products/coir-rope-10-m');
        await dismissIsland(page);
        const compare = page.locator('form[action*="/compare/"] button, button:has-text("Compare")').first();
        if (await compare.count()) {
            await compare.click();
            await page.waitForTimeout(400);
            await page.goto(BASE + '/compare');
            record('compare', /coir|compare/i.test(await bodyText(page)) ? 'Pass' : 'Fail');
            await shot(page, 'compare');
        } else record('compare', 'Not tested', 'no Compare control on the product page');
        await page.goto(BASE + '/products/snorkel-set-adult');
        await dismissIsland(page);
        const wish = page.locator('form[action*="/wishlist/"] button').first();
        if (await wish.count()) {
            await wish.click();
            await page.waitForTimeout(400);
            await page.goto(BASE + '/wishlist');
            record('wishlist', /snorkel/i.test(await bodyText(page)) ? 'Pass' : 'Fail');
            await shot(page, 'wishlist');
        } else record('wishlist', 'Not tested', 'no wishlist form');
        await page.goto(BASE + '/products/power-bank-20000-mah');
        await dismissIsland(page);
        await page.locator('[data-add-to-cart]').first().click();
        await page.waitForTimeout(400);
        await page.goto(BASE + '/cart');
        const save = page.getByRole('button', { name: 'Save for later' }).first();
        if (await save.count()) {
            await save.click();
            await page.waitForTimeout(500);
            record('saved for later', /saved|later/i.test(await bodyText(page)) ? 'Pass' : 'Fail');
            await shot(page, 'saved-for-later');
        } else record('saved for later', 'Not tested', 'button not on the cart');
        const video = sql("SELECT COUNT(*) FROM products WHERE video_id IS NOT NULL AND video_id != '';");
        record('product videos', Number(video) > 0 ? 'Pass' : 'Not tested', Number(video) > 0 ? `${video} products have a video` : 'seeded products have no video id');
        const pre = sql('SELECT COUNT(*) FROM products WHERE preorder_enabled=1;');
        record('pre-orders in seed', Number(pre) > 0 ? 'Pass' : 'Not tested', Number(pre) > 0 ? `${pre} pre-order products` : 'no seeded pre-order product; seller form not submitted to avoid changing catalogue rules');
        await page.goto(BASE + '/products/coir-rope-10-m');
        const q = page.locator('textarea[name="question"], input[name="question"]').first();
        if (await q.count()) {
            await q.fill('Is this rope for a dhoni?');
            await page.locator('form[action*="/questions"] button[type="submit"]').click();
            await page.waitForTimeout(500);
            record('Q&A question submitted', /question|thanks|sent|review/i.test(await bodyText(page)) ? 'Pass' : 'Fail', (await bodyText(page)).slice(0, 160).replace(/\s+/g, ' '));
        } else record('Q&A', 'Not tested', 'question field not shown (may need a delivered order)');
        const review = page.locator('textarea[name="comment"], textarea[name="review"], input[name="rating"]').first();
        record('reviews with photos', await review.count() ? 'Not tested' : 'Not tested', await review.count() ? 'review form is present but a photo upload was not completed in this pass' : 'review form not shown for this product');
        const alert = page.locator('form[action*="stock-alert"] button, button:has-text("Notify")').first();
        record('stock alerts', await alert.count() ? 'Pass' : 'Not tested', await alert.count() ? 'stock alert control is on the page' : 'product is in stock so the alert control is hidden');
        await context.close();
    }

    // Admin: sign in, every menu item, settings, payment link later
    const adminLinks = [];
    {
        const { context, page } = await newPage(browser, desktop, 'admin');
        await page.goto(BASE + '/login/email');
        await page.fill('#email', 'admin@example.com');
        await page.fill('#password', 'password');
        await page.locator('form[action$="/login"] button[type="submit"]').click();
        await page.waitForURL(/\/admin/, { timeout: 10000 }).catch(() => {});
        record('admin sign-in', page.url().includes('/admin') ? 'Pass' : 'Fail', page.url());
        await shot(page, 'admin-dashboard');
        const hrefs = await page.locator('aside a[href]').evaluateAll((as) => as.map((a) => ({ href: a.getAttribute('href'), text: a.textContent.trim() })));
        for (const link of hrefs) {
            if (!link.href || link.href.startsWith('#')) continue;
            adminLinks.push(link);
        }
        let failed = [];
        for (const link of adminLinks) {
            const res = await page.goto(link.href.startsWith('http') ? link.href : BASE + link.href, { waitUntil: 'domcontentloaded' });
            const status = res?.status() ?? 0;
            if (status >= 400) failed.push(`${link.text} ${status}`);
        }
        record('every admin menu item opens', failed.length === 0 ? 'Pass' : 'Fail', failed.length ? failed.join('; ') : `${adminLinks.length} links`);
        await shot(page, 'admin-last-menu');
        await page.goto(BASE + '/admin/settings');
        await page.fill('#contact_phone', '7770001');
        await page.locator('input[name="payment_transfer_enabled"][value="1"]').check();
        await page.fill('#transfer_bank_name', 'Bank of Maldives');
        await page.fill('#transfer_account_name', 'Iruali audit');
        await page.fill('#transfer_account_number', '7770001234567');
        await page.getByRole('button', { name: 'Save settings' }).click();
        await page.waitForLoadState('domcontentloaded');
        const saved = /saved|updated|settings/i.test(await bodyText(page));
        const phone = sql("SELECT value FROM settings WHERE key='contact_phone';");
        record('settings save', phone.includes('7770001') ? 'Pass' : 'Fail', `contact_phone=${phone} flash=${saved}`);
        await shot(page, 'admin-settings-saved');
        await context.close();
    }

    // Seller centre
    {
        const { context, page } = await newPage(browser, desktop, 'seller');
        await page.goto(BASE + '/login/email');
        await page.fill('#email', 'islandcrafts@example.com');
        await page.fill('#password', 'password');
        await page.locator('form[action$="/login"] button[type="submit"]').click();
        await page.waitForLoadState('domcontentloaded');
        await page.goto(BASE + '/seller');
        record('seller dashboard', page.url().includes('/seller') && (await page.content()).length > 500 ? 'Pass' : 'Fail', page.url());
        await shot(page, 'seller-dashboard');
        const paths = [
            ['/seller/products', 'products'],
            ['/seller/products/create', 'product create'],
            ['/seller/products/export', 'csv export'],
            ['/seller/stock', 'stock'],
            ['/seller/orders', 'orders'],
            ['/seller/earnings', 'payouts'],
            ['/seller/staff', 'staff'],
        ];
        for (const [path, label] of paths) {
            const res = await page.goto(BASE + path);
            const status = res?.status() ?? 0;
            record(`seller ${label}`, status < 400 ? 'Pass' : 'Fail', `HTTP ${status} ${page.url()}`);
        }
        await shot(page, 'seller-staff');
        const orderId = sql('SELECT id FROM orders ORDER BY id DESC LIMIT 1;');
        if (orderId) {
            const res = await page.goto(BASE + '/orders/' + orderId);
            record('seller cannot open customer /orders/{id}', res?.status() === 403 ? 'Pass' : 'Fail', `HTTP ${res?.status()} order ${orderId}`);
            await shot(page, 'seller-order-403');
        } else record('seller cannot open customer /orders/{id}', 'Not tested', 'no order was placed');
        await context.close();
    }

    // Staff roles: flip three existing customers via the admin UI
    {
        const { context, page } = await newPage(browser, desktop, 'roles');
        await page.goto(BASE + '/login/email');
        await page.fill('#email', 'admin@example.com');
        await page.fill('#password', 'password');
        await page.locator('form[action$="/login"] button[type="submit"]').click();
        await page.waitForLoadState('domcontentloaded');
        await page.goto(BASE + '/admin/users');
        await shot(page, 'admin-users');
        const roles = [
            ['thulhaadhoo@example.com', 'support', '/admin/orders', '/admin/settings'],
            ['maafushifresh@example.com', 'finance', '/admin/payouts', '/admin/products'],
            ['reefline@example.com', 'catalogue', '/admin/brands', '/admin/orders'],
        ];
        for (const [email, role, allow, deny] of roles) {
            await page.goto(BASE + '/admin/users');
            const row = page.locator('tr', { hasText: email });
            const select = row.locator('select[name="role"]');
            if (!(await select.count())) {
                record(`staff role ${role} assigned`, 'Not tested', `no role select for ${email}`);
                continue;
            }
            await select.selectOption(role);
            await row.locator('form[action*="/role"] button, form button').first().click().catch(async () => {
                await row.locator('form[action*="role"]').evaluate((f) => f.submit());
            });
            await page.waitForTimeout(500);
        }
        await context.close();
        for (const [email, role, allow, deny] of roles) {
            const { context: c2, page: p2 } = await newPage(browser, desktop, 'role-' + role);
            await p2.goto(BASE + '/login/email');
            await p2.fill('#email', email);
            await p2.fill('#password', 'password');
            await p2.locator('form[action$="/login"] button[type="submit"]').click();
            await p2.waitForLoadState('domcontentloaded');
            const a = await p2.goto(BASE + allow);
            const d = await p2.goto(BASE + deny);
            const ok = (a?.status() ?? 500) < 400 && (d?.status() === 403);
            record(`staff role ${role}`, ok ? 'Pass' : 'Fail', `${allow}=${a?.status()} ${deny}=${d?.status()} landed ${p2.url()}`);
            await shot(p2, `role-${role}-denied`);
            await c2.close();
        }
    }

    fs.writeFileSync(OUT + '/admin-links.json', JSON.stringify(adminLinks, null, 2));
} catch (err) {
    record('walk script', 'Fail', err.stack || String(err));
} finally {
    await browser.close();
    fs.writeFileSync(OUT + '/walk-results.json', JSON.stringify(results, null, 2));
    fs.writeFileSync(OUT + '/console-errors.json', JSON.stringify(consoleLog, null, 2));
    console.log('WROTE', results.length, 'results', consoleLog.length, 'console errors');
}
