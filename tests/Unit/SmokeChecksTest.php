<?php

namespace Tests\Unit;

use App\Support\SmokeChecks;
use Tests\Support\FakeSmokeFetcher;
use Tests\TestCase;

class SmokeChecksTest extends TestCase
{
    private const HTML = '<!DOCTYPE html><html><body><form><input type="hidden" name="_token" value="tok123" autocomplete="off"></form> agree_terms</body></html>';

    private function healthySite(): FakeSmokeFetcher
    {
        $sitemap = '<?xml version="1.0"?><urlset><url><loc>http://smoke.test/</loc></url>'
            .'<url><loc>http://smoke.test/products/blue-mug</loc></url>'
            .'<url><loc>http://smoke.test/categories/kitchen</loc></url></urlset>';

        return (new FakeSmokeFetcher)
            ->on('GET /', 200, self::HTML)
            ->on('GET /sitemap.xml', 200, $sitemap)
            ->on('GET /robots.txt', 200, "User-agent: *\nDisallow: /admin\n")
            ->on('GET /products/blue-mug', 200, self::HTML)
            ->on('GET /categories/kitchen', 200, self::HTML)
            ->on('GET /search', 200, self::HTML)
            ->on('GET /cart', 200, self::HTML)
            ->on('GET /login', 200, self::HTML)
            ->on('GET /up', 200, 'ok')
            ->on('GET /api/health', 200, json_encode(['status' => 'healthy', 'commit' => 'abc1234']));
    }

    private function withOrderFlow(FakeSmokeFetcher $site, int|string $cancelledPage = 'Cancelled'): FakeSmokeFetcher
    {
        return $site
            ->on('POST /login', 302, '', ['Location' => 'http://smoke.test/'])
            ->on('POST /cart/add', 302, '', ['Location' => 'http://smoke.test/products/blue-mug'])
            ->on('GET /checkout', 200, self::HTML)
            ->on('POST /orders', 302, '', ['Location' => 'https://pay.bml.test/txn_1'])
            ->on('GET https://pay.bml.test/txn_1', 200, '<html>BML Connect</html>')
            ->on('GET /orders', 200, '<html><a href="http://smoke.test/orders/42">Order</a> <a href="http://smoke.test/orders/42/receipt">r</a></html>')
            ->on('POST /orders/42/cancel', 302, '', ['Location' => 'http://smoke.test/orders/42'])
            ->on('GET /orders/42', 200, '<html>Status: '.$cancelledPage.'</html>');
    }

    private function statuses(array $results): array
    {
        return collect($results)->mapWithKeys(fn ($r) => [$r['name'] => $r['status']])->all();
    }

    public function test_all_pages_pass_on_a_healthy_site(): void
    {
        $site = $this->healthySite();
        $results = (new SmokeChecks($site, 'http://smoke.test/'))->run();

        $this->assertSame(0, SmokeChecks::failures($results));
        $this->assertSame([
            'Home' => 'pass', 'Sitemap' => 'pass', 'Robots' => 'pass', 'Product page' => 'pass', 'Category page' => 'pass',
            'Search' => 'pass', 'Cart' => 'pass', 'Login page' => 'pass', 'Laravel /up' => 'pass', 'API health' => 'pass',
        ], $this->statuses($results));
        $this->assertSame('commit abc1234', collect($results)->firstWhere('name', 'API health')['detail']);
        $this->assertNotNull($site->sent('GET', '/products/blue-mug'), 'the product page comes from the sitemap');
        $this->assertNull($site->sent('POST', '/login'), 'no order is placed without the option');
    }

    public function test_a_500_a_redirect_and_a_connection_error_are_failures(): void
    {
        $site = $this->healthySite()
            ->on('GET /', 500, 'Whoops')
            ->on('GET /cart', 302, '', ['Location' => 'http://smoke.test/login'])
            ->on('GET /api/health', 200, '{"status":"down"}');
        $site->failing('GET /search');
        $results = (new SmokeChecks($site, 'http://smoke.test'))->run();
        $statuses = $this->statuses($results);

        $this->assertSame('fail', $statuses['Home']);
        $this->assertSame('fail', $statuses['Cart']);
        $this->assertStringContainsString('HTTP 302 to http://smoke.test/login', collect($results)->firstWhere('name', 'Cart')['detail']);
        $this->assertSame('fail', $statuses['API health']);
        $this->assertSame('fail', $statuses['Search']);
        $this->assertSame(4, SmokeChecks::failures($results));
    }

    public function test_missing_product_or_category_in_the_sitemap_fails_those_rows(): void
    {
        $site = $this->healthySite()->on('GET /sitemap.xml', 200, '<urlset></urlset>');
        $statuses = $this->statuses((new SmokeChecks($site, 'http://smoke.test'))->run());

        $this->assertSame('pass', $statuses['Sitemap']);
        $this->assertSame('fail', $statuses['Product page']);
        $this->assertSame('fail', $statuses['Category page']);
    }

    public function test_place_order_signs_in_adds_to_cart_reaches_bml_cancels_and_checks_stock(): void
    {
        $site = $this->withOrderFlow($this->healthySite());
        $stock = 7;
        $results = (new SmokeChecks(
            $site,
            'http://smoke.test',
            ['email' => 'smoke@iruali.mv', 'password' => 'secret', 'product_id' => 5, 'variant_id' => 9],
            function (int $productId, ?int $variantId) use (&$stock) {
                return $stock;
            },
        ))->run();

        $this->assertSame(0, SmokeChecks::failures($results), json_encode($results));
        $statuses = $this->statuses($results);
        foreach (['Sign in as smoke user', 'Add cheapest product to cart', 'Checkout page', 'Place order', 'BML pay page loads', 'Order under My Orders', 'Cancel order', 'Stock restored'] as $step) {
            $this->assertSame('pass', $statuses[$step], $step);
        }

        $login = $site->sent('POST', '/login');
        $this->assertSame(['email' => 'smoke@iruali.mv', 'password' => 'secret', '_token' => 'tok123'], $login['data']);
        $add = $site->sent('POST', '/cart/add');
        $this->assertSame(5, $add['data']['product_id']);
        $this->assertSame(9, $add['data']['product_variant_id']);
        $this->assertSame(1, $add['data']['quantity']);
        $order = $site->sent('POST', '/orders');
        $this->assertSame('bml', $order['data']['payment_method']);
        $this->assertSame('1', $order['data']['agree_terms']);
        $this->assertSame('tok123', $order['data']['_token']);
        $this->assertNotNull($site->sent('GET', 'https://pay.bml.test/txn_1'), 'the BML page is loaded (never paid)');
        $this->assertNotNull($site->sent('POST', '/orders/42/cancel'));
    }

    public function test_a_fresh_csrf_token_is_taken_after_signing_in(): void
    {
        $site = $this->withOrderFlow($this->healthySite())
            ->on('GET /cart', 200, str_replace('tok123', 'tok-after-login', self::HTML));
        $results = (new SmokeChecks($site, 'http://smoke.test', ['email' => 'x', 'password' => 'y', 'product_id' => 1, 'variant_id' => null]))->run();

        $this->assertSame(0, SmokeChecks::failures($results), json_encode($results));
        $this->assertSame('tok123', $site->sent('POST', '/login')['data']['_token']);
        $this->assertSame('tok-after-login', $site->sent('POST', '/cart/add')['data']['_token']);
    }

    public function test_a_fake_bml_redirect_counts_as_the_pay_page(): void
    {
        $site = $this->withOrderFlow($this->healthySite())
            ->on('POST /orders', 302, '', ['Location' => 'http://smoke.test/payments/bml/return/42?transactionId=FAKE1&fake=1'])
            ->on('GET /payments/bml/return/42', 302, '', ['Location' => 'http://smoke.test/orders/42']);
        $results = (new SmokeChecks($site, 'http://smoke.test', ['email' => 'x', 'password' => 'y', 'product_id' => 1, 'variant_id' => null]))->run();
        $row = collect($results)->firstWhere('name', 'BML pay page loads');

        $this->assertSame('pass', $row['status']);
        $this->assertStringContainsString('BML_FAKE', $row['detail']);
        $this->assertSame(0, SmokeChecks::failures($results), json_encode($results));
    }

    public function test_a_failed_sign_in_fails_and_skips_the_rest(): void
    {
        $site = $this->withOrderFlow($this->healthySite())->on('POST /login', 302, '', ['Location' => 'http://smoke.test/login']);
        $results = (new SmokeChecks($site, 'http://smoke.test', ['email' => 'x', 'password' => 'y', 'product_id' => 1, 'variant_id' => null]))->run();
        $statuses = $this->statuses($results);

        $this->assertSame('fail', $statuses['Sign in as smoke user']);
        $this->assertSame('skip', $statuses['Cancel order']);
        $this->assertSame('skip', $statuses['Stock restored']);
        $this->assertNull($site->sent('POST', '/cart/add'));
        $this->assertSame(1, SmokeChecks::failures($results));
    }

    public function test_landing_on_the_order_page_instead_of_bml_is_a_failure(): void
    {
        $site = $this->withOrderFlow($this->healthySite())
            ->on('POST /orders', 302, '', ['Location' => 'http://smoke.test/orders/42'])
            ->on('GET /orders/42', 200, '<html><form action="http://smoke.test/orders/42/pay"></form> Cancelled</html>');
        $results = (new SmokeChecks($site, 'http://smoke.test', ['email' => 'x', 'password' => 'y', 'product_id' => 1, 'variant_id' => null]))->run();
        $row = collect($results)->firstWhere('name', 'BML pay page loads');

        $this->assertSame('fail', $row['status']);
        $this->assertStringContainsString('Pay now', $row['detail']);
        $this->assertSame('pass', $this->statuses($results)['Cancel order'], 'the order is still cancelled');
    }

    public function test_stock_not_restored_and_order_not_cancelled_are_failures(): void
    {
        $site = $this->withOrderFlow($this->healthySite(), 'Pending');
        $calls = 0;
        $results = (new SmokeChecks(
            $site,
            'http://smoke.test',
            ['email' => 'x', 'password' => 'y', 'product_id' => 1, 'variant_id' => null],
            function () use (&$calls) {
                return $calls++ === 0 ? 3 : 2;
            },
        ))->run();
        $statuses = $this->statuses($results);

        $this->assertSame('fail', $statuses['Cancel order']);
        $this->assertSame('fail', $statuses['Stock restored']);
        $this->assertStringContainsString('before 3, after 2', collect($results)->firstWhere('name', 'Stock restored')['detail']);
    }
}
