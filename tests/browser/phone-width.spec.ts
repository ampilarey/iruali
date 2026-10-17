import { expect, test } from '@playwright/test';

// Pages fit a phone screen: nothing makes the page wider than the screen, which on a phone makes the
// browser zoom the whole page out (the policy pages once did, through their sideways-scrolling menu).
// Signed out shop pages in both languages, and the admin area signed in as the seeded admin.

const PHONE = { width: 390, height: 844 };

const SHOP_PAGES = [
    '/', '/products', '/brands', '/deals', '/cart', '/login', '/register', '/help',
    '/terms', '/refund-policy', '/delivery-policy', '/privacy-policy', '/payment-security', '/about', '/seller-terms',
    '/dv', '/dv/products', '/dv/terms',
];
const ADMIN_PAGES = [
    '/admin/dashboard', '/admin/orders', '/admin/products', '/admin/brands', '/admin/sellers', '/admin/users',
    '/admin/settings', '/admin/legal', '/admin/delivery', '/admin/analytics', '/admin/audit', '/admin/tax',
];

async function pageWidth(page: import('@playwright/test').Page) {
    return page.evaluate(() => document.documentElement.scrollWidth);
}

test.use({ viewport: PHONE, isMobile: true, hasTouch: true });

test('shop pages fit a phone screen', async ({ page }) => {
    for (const path of SHOP_PAGES) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBeLessThan(400);
        expect(await pageWidth(page), `${path} is wider than the phone`).toBeLessThanOrEqual(PHONE.width);
    }
});

test('admin pages fit a phone screen', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill(process.env.BROWSER_TEST_ADMIN_EMAIL || 'admin@example.com');
    await page.locator('#password').fill(process.env.BROWSER_TEST_ADMIN_PASSWORD || 'password');
    await page.locator('form[action$="/login"] button[type="submit"]').click();
    await expect(page).not.toHaveURL(/\/login$/);

    for (const path of ADMIN_PAGES) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        expect(await pageWidth(page), `${path} is wider than the phone`).toBeLessThanOrEqual(PHONE.width);
    }

    // Sections fold to their headings; a tap opens one
    await page.goto('/admin/settings');
    const fold = page.locator('details[data-fold="payment-methods"]');
    await expect(fold).not.toHaveAttribute('open', '');
    await fold.locator('summary').click();
    await expect(fold).toHaveAttribute('open', '');
});
