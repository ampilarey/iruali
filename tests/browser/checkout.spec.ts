import { expect, test, type Page } from '@playwright/test';

// The customer checkout, end to end in a real browser: sign in, search, open the product, pick a
// variant when there is one, add to cart, place the order with card payment (BML faked by
// setup.sh with BML_FAKE=1, so nothing leaves the machine), land on the "Pay now" page and find
// the order under My Orders. Data comes from the seeders (MarketplaceDemoSeeder); the customer
// is the one setup.sh creates with `php artisan iruali:smoke --setup`.

const EMAIL = process.env.BROWSER_TEST_EMAIL || 'browser@iruali.test';
const PASSWORD = process.env.BROWSER_TEST_PASSWORD || 'browser-pass-123';
const SEARCH = process.env.BROWSER_TEST_SEARCH || 'Coir rope';

async function signIn(page: Page) {
    await page.goto('/login');
    await page.locator('#email').fill(EMAIL);
    await page.locator('#password').fill(PASSWORD);
    await page.locator('form[action$="/login"] button[type="submit"]').click();
    await expect(page).not.toHaveURL(/\/login$/);
}

test.describe('checkout', () => {
    test('search → product → cart → checkout → pay now → my orders', async ({ page }) => {
        await signIn(page);

        // Search from the header
        await page.locator('#search-q').fill(SEARCH);
        await page.locator('#search-q').press('Enter');
        await expect(page).toHaveURL(/\/search\?.*q=/);
        const result = page.locator('a[href*="/products/"]').filter({ hasText: new RegExp(SEARCH, 'i') }).first();
        await expect(result).toBeVisible();
        await result.click();
        await expect(page).toHaveURL(/\/products\//);

        // Product page: pick a variant when the product has them, then add to cart
        const productName = (await page.locator('h1').first().textContent())?.trim() || SEARCH;
        const variantSelect = page.locator('select[name="product_variant_id"]');
        if (await variantSelect.count()) {
            const value = await variantSelect.evaluate((el) =>
                Array.from((el as HTMLSelectElement).options).find((o) => o.value !== '' && !o.disabled)?.value ?? '');
            expect(value, 'an in-stock variant to choose').not.toBe('');
            await variantSelect.selectOption(value);
        }
        await page.locator('[data-add-to-cart]').click();
        await expect(page).toHaveURL(/\/cart$/);
        await expect(page.locator('#main')).toContainText(productName.split(',')[0]);

        // Checkout: address with an island from the picker, phone, terms; card is the only method
        await page.goto('/checkout');
        await expect(page).toHaveURL(/\/checkout$/);
        await page.locator('#shipping_address').fill('H. Browser Test House');
        await page.locator('#ship-island-select').selectOption({ index: 1 });
        await page.locator('#shipping_phone').fill('7771234');
        const bml = page.locator('input[name="payment_method"][value="bml"]');
        await expect(bml).toBeVisible();
        await bml.check();
        await page.locator('input[name="agree_terms"]').check();
        await page.getByRole('button', { name: /place order/i }).click();

        // With BML faked the "payment page" comes straight back to the order, unpaid, with Pay now
        await expect(page).toHaveURL(/\/orders\/(\d+)$/);
        const orderId = page.url().match(/\/orders\/(\d+)$/)?.[1];
        expect(orderId).toBeTruthy();
        const payNow = page.locator(`form[action$="/orders/${orderId}/pay"] button[type="submit"]`);
        await expect(payNow).toBeVisible();
        await expect(payNow).toContainText(/now/i);
        const orderNumber = (await page.locator('h1, h2').filter({ hasText: /#/ }).first().textContent())?.match(/#\s*([A-Z0-9-]+)/)?.[1];
        expect(orderNumber, 'the order number is shown on the order page').toBeTruthy();

        // My Orders lists it
        await page.goto('/orders');
        await expect(page.locator('body')).toContainText(`#${orderNumber}`);
        await expect(page.locator(`a[href$="/orders/${orderId}"]`).first()).toBeVisible();
    });
});
