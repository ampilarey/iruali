<?php

namespace App\Support;

use App\Enums\OrderStatus;
use Closure;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * The post-deploy smoke test: a handful of GETs that must answer 200, and optionally one real
 * order placed as the smoke customer, taken to BML's "Pay now" page (never paid), cancelled, with
 * the stock checked back. Each step is one row; the command prints the rows and exits 1 on any
 * failure. The fetcher is injected so the steps can be tested without a running server.
 */
final class SmokeChecks
{
    public const PASS = 'pass';

    public const FAIL = 'fail';

    public const SKIP = 'skip';

    /** @var list<array{name: string, status: string, detail: string}> */
    private array $results = [];

    private ?string $productPath = null;

    private ?string $categoryPath = null;

    /** The order round trip's steps, in order (skipped together when there is nothing to order). */
    private const ORDER_STEPS = ['Sign in as smoke user', 'Add cheapest product to cart', 'Checkout page', 'Place order', 'BML pay page loads', 'Order under My Orders', 'Cancel order', 'Stock restored'];

    /**
     * @param  array{email: string, password: string, product_id: int, variant_id: int|null}|null  $order  sign in and place an order with this product
     * @param  Closure(int, int|null): int|null  $stockReader  current stock for (product id, variant id); defaults to nothing checked
     * @param  bool  $catalogEmpty  the database has no product on sale (a new site, or the sample data removed):
     *                              a sitemap without products is then expected, so those checks are skipped, not failed
     * @param  string|null  $orderSkipped  why the order round trip was asked for but cannot run (nothing in stock to order)
     */
    public function __construct(
        private readonly SmokeFetcher $fetcher,
        string $baseUrl,
        private readonly ?array $order = null,
        private readonly ?Closure $stockReader = null,
        private readonly bool $catalogEmpty = false,
        private readonly ?string $orderSkipped = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->baseHost = (string) parse_url($this->baseUrl, PHP_URL_HOST);
    }

    private readonly string $baseUrl;

    private readonly string $baseHost;

    /**
     * @return list<array{name: string, status: string, detail: string}>
     */
    public function run(): array
    {
        $this->results = [];

        $this->page('Home', '/', fn (Response $r) => $this->html($r));
        $this->page('Sitemap', '/sitemap.xml', function (Response $r) {
            if (! str_contains($r->body(), '<urlset')) {
                return 'no <urlset> in the body';
            }
            $this->productPath = $this->firstPath($r->body(), '/products/');
            $this->categoryPath = $this->firstPath($r->body(), '/categories/');

            return null;
        });
        $this->page('Robots', '/robots.txt', fn (Response $r) => str_contains($r->body(), 'User-agent') ? null : 'no User-agent line');

        if ($this->productPath) {
            $this->page('Product page', $this->productPath, fn (Response $r) => $this->html($r));
        } elseif ($this->catalogEmpty) {
            $this->result('Product page', self::SKIP, 'no product on sale yet');
        } else {
            $this->result('Product page', self::FAIL, 'sitemap lists no product, but products are on sale');
        }
        if ($this->categoryPath) {
            $this->page('Category page', $this->categoryPath, fn (Response $r) => $this->html($r));
        } elseif ($this->catalogEmpty) {
            $this->result('Category page', self::SKIP, 'no product on sale yet');
        } else {
            $this->result('Category page', self::FAIL, 'sitemap lists no category; is any category active?');
        }

        $this->page('Search', '/search?q=test', fn (Response $r) => $this->html($r)); // one letter redirects to /shop
        $this->page('Cart', '/cart', fn (Response $r) => $this->html($r));
        $this->page('Login page', '/login', fn (Response $r) => $this->csrfToken($r) ? null : 'no CSRF token in the form');
        $this->page('Laravel /up', '/up', fn () => null);
        $this->page('API health', '/api/health', function (Response $r) {
            $json = $r->json();

            return is_array($json) && ($json['status'] ?? null) === 'healthy'
                ? [self::PASS, 'commit '.($json['commit'] ?? 'unknown')]
                : 'body is not {"status":"healthy"}';
        });

        if ($this->order !== null) {
            $this->placeOrder();
        } elseif ($this->orderSkipped !== null) {
            foreach (self::ORDER_STEPS as $name) {
                $this->result($name, self::SKIP, $this->orderSkipped);
            }
        }

        return $this->results;
    }

    /**
     * @param  list<array{name: string, status: string, detail: string}>  $results
     */
    public static function failures(array $results): int
    {
        return count(array_filter($results, fn ($r) => $r['status'] === self::FAIL));
    }

    // --- the order round trip --------------------------------------------------------------

    private function placeOrder(): void
    {
        $o = $this->order;
        $stockBefore = $this->stock();

        $login = $this->fetch('GET', '/login');
        $token = $login ? $this->csrfToken($login) : null;
        if (! $token) {
            $this->result('Sign in as smoke user', self::FAIL, 'could not load the login form');
            $this->skipRest(array_slice(self::ORDER_STEPS, 1));

            return;
        }
        $signed = $this->fetch('POST', '/login', ['email' => $o['email'], 'password' => $o['password'], '_token' => $token]);
        if (! $signed || ! $this->redirectedAwayFrom($signed, '/login')) {
            $this->result('Sign in as smoke user', self::FAIL, $signed ? 'HTTP '.$signed->status().' to '.($signed->header('Location') ?: '(no redirect)').' — wrong SMOKE_USER_EMAIL / SMOKE_USER_PASSWORD?' : $this->noResponse());
            $this->skipRest(array_slice(self::ORDER_STEPS, 1));

            return;
        }
        $this->result('Sign in as smoke user', self::PASS, $o['email']);

        // Signing in regenerates the session, so the CSRF token from the login page is stale.
        $fresh = $this->fetch('GET', '/cart');
        $token = $fresh ? $this->csrfToken($fresh) : null;
        if (! $token) {
            $this->result('Add cheapest product to cart', self::FAIL, 'could not load the cart page for a fresh CSRF token');
            $this->skipRest(['Checkout page', 'Place order', 'BML pay page loads', 'Order under My Orders', 'Cancel order', 'Stock restored']);

            return;
        }

        $add = $this->fetch('POST', '/cart/add', array_filter([
            'product_id' => $o['product_id'],
            'quantity' => 1,
            'product_variant_id' => $o['variant_id'] ?? null,
            '_token' => $token,
        ], fn ($v) => $v !== null));
        if (! $add || ! $this->isRedirect($add)) {
            $this->result('Add cheapest product to cart', self::FAIL, $add ? 'HTTP '.$add->status() : $this->noResponse());
            $this->skipRest(['Checkout page', 'Place order', 'BML pay page loads', 'Order under My Orders', 'Cancel order', 'Stock restored']);

            return;
        }
        $this->result('Add cheapest product to cart', self::PASS, 'product #'.$o['product_id'].(isset($o['variant_id']) ? ' variant #'.$o['variant_id'] : ''));

        $checkout = $this->fetch('GET', '/checkout');
        $token = $checkout ? $this->csrfToken($checkout) : null;
        if (! $checkout || $checkout->status() !== 200 || ! $token || ! str_contains($checkout->body(), 'agree_terms')) {
            $this->result('Checkout page', self::FAIL, $checkout ? 'HTTP '.$checkout->status().($checkout->status() === 302 ? ' to '.$checkout->header('Location').' (cart empty? product out of stock?)' : '') : $this->noResponse());
            $this->skipRest(['Place order', 'BML pay page loads', 'Order under My Orders', 'Cancel order', 'Stock restored']);

            return;
        }
        $this->result('Checkout page', self::PASS, 'form loaded');

        $placed = $this->fetch('POST', '/orders', [
            '_token' => $token,
            'shipping_address' => 'Smoke test — do not ship',
            'shipping_city' => 'Malé',
            'shipping_state' => 'Kaafu',
            'shipping_country' => 'Maldives',
            'shipping_phone' => '7000000',
            'delivery_zone' => 'greater_male',
            'payment_method' => 'bml',
            'agree_terms' => '1',
            'notes' => 'Automated smoke test order; it is cancelled right away.',
        ]);
        $location = $placed?->header('Location') ?: '';
        if (! $placed || ! $this->isRedirect($placed) || str_contains($location, '/cart') || str_contains($location, '/checkout')) {
            $this->result('Place order', self::FAIL, $placed ? 'HTTP '.$placed->status().' to '.($location ?: '(no redirect)') : $this->noResponse());
            $this->skipRest(['BML pay page loads', 'Order under My Orders', 'Cancel order', 'Stock restored']);

            return;
        }
        $this->result('Place order', self::PASS, 'redirected to '.$location);

        // Card orders go straight to BML's page; an order page means BML could not be reached.
        // With BML_FAKE=1 (never production) the "page" is our own return URL marked fake=1.
        if (str_contains($location, 'fake=1')) {
            $back = $this->fetch('GET', $location);
            $this->result('BML pay page loads', $back && $this->isRedirect($back) ? self::PASS : self::FAIL, 'BML_FAKE=1: payment page skipped'.($back ? '' : ', '.$this->noResponse()));
        } elseif ($this->isExternal($location)) {
            $pay = $this->fetch('GET', $location);
            $this->result('BML pay page loads', $pay && $pay->status() < 400 ? self::PASS : self::FAIL, $pay ? 'HTTP '.$pay->status().' (not paid)' : $this->noResponse().' from '.$location);
        } else {
            $page = $this->fetch('GET', $location);
            $offersPay = $page && $page->status() === 200 && preg_match('#/orders/\d+/pay"#', $page->body());
            $this->result('BML pay page loads', self::FAIL, $offersPay ? 'BML did not answer; the order page offers "Pay now" instead' : 'landed on '.$location);
        }

        $list = $this->fetch('GET', '/orders');
        $orderId = null;
        if ($list && $list->status() === 200 && preg_match('#/orders/(\d+)"#', $list->body(), $m)) {
            $orderId = (int) $m[1];
        }
        if (! $orderId) {
            $this->result('Order under My Orders', self::FAIL, $list ? 'HTTP '.$list->status().', no order link found' : $this->noResponse());
            $this->skipRest(['Cancel order', 'Stock restored']);

            return;
        }
        $this->result('Order under My Orders', self::PASS, 'order #'.$orderId);

        $cancel = $this->fetch('POST', '/orders/'.$orderId.'/cancel', ['_token' => $token]);
        $after = $cancel && $this->isRedirect($cancel) ? $this->fetch('GET', '/orders/'.$orderId) : null;
        $cancelled = $after && $after->status() === 200
            && (str_contains($after->body(), OrderStatus::Cancelled->label()) || str_contains($after->body(), __('Cancelled', [], 'dv')));
        $this->result('Cancel order', $cancelled ? self::PASS : self::FAIL, $cancelled ? 'order #'.$orderId.' cancelled' : ($cancel ? 'HTTP '.$cancel->status().'; the order page does not say cancelled' : $this->noResponse()));

        if ($stockBefore === null) {
            $this->result('Stock restored', self::SKIP, 'no stock reader');
        } else {
            $stockAfter = $this->stock();
            $this->result('Stock restored', $stockAfter === $stockBefore ? self::PASS : self::FAIL, "before {$stockBefore}, after ".var_export($stockAfter, true));
        }
    }

    // --- helpers ----------------------------------------------------------------------------

    /**
     * @param  Closure(Response): (string|array{0: string, 1: string}|null)  $assert  null = pass, a string = why it failed, [status, detail] = both
     */
    private function page(string $name, string $path, Closure $assert): void
    {
        $response = $this->fetch('GET', $path);
        if (! $response) {
            $this->result($name, self::FAIL, $this->noResponse());

            return;
        }
        if ($response->status() !== 200) {
            $this->result($name, self::FAIL, 'HTTP '.$response->status().($this->isRedirect($response) ? ' to '.$response->header('Location') : ''));

            return;
        }
        try {
            $outcome = $assert($response);
        } catch (Throwable $e) {
            $outcome = $e->getMessage();
        }
        if (is_array($outcome)) {
            $this->result($name, $outcome[0], $outcome[1]);
        } else {
            $this->result($name, $outcome === null ? self::PASS : self::FAIL, $outcome ?? 'HTTP 200');
        }
    }

    private ?string $lastError = null;

    /**
     * One request; a transport failure is returned as null with the reason in $lastError.
     */
    private function fetch(string $method, string $path, array $data = []): ?Response
    {
        $url = $this->isAbsolute($path) ? $path : $this->baseUrl.'/'.ltrim($path, '/');
        $this->lastError = null;
        try {
            return $this->fetcher->request($method, $url, $data);
        } catch (Throwable $e) {
            $this->lastError = class_basename($e).': '.$e->getMessage();

            return null;
        }
    }

    private function noResponse(): string
    {
        return $this->lastError ?? 'no response';
    }

    private function result(string $name, string $status, string $detail): void
    {
        $this->results[] = ['name' => $name, 'status' => $status, 'detail' => $detail];
    }

    /**
     * @param  list<string>  $names
     */
    private function skipRest(array $names): void
    {
        foreach ($names as $name) {
            $this->result($name, self::SKIP, 'earlier step failed');
        }
    }

    private function html(Response $r): ?string
    {
        return str_contains($r->body(), '<html') || str_contains($r->body(), '<!DOCTYPE') ? null : 'body is not an HTML page';
    }

    private function csrfToken(Response $r): ?string
    {
        return preg_match('/name="_token"\s+value="([^"]+)"/', $r->body(), $m) ? $m[1] : null;
    }

    private function firstPath(string $xml, string $prefix): ?string
    {
        if (! preg_match('#<loc>([^<]*'.preg_quote($prefix, '#').'[^<]+)</loc>#', $xml, $m)) {
            return null;
        }
        $path = parse_url(html_entity_decode($m[1]), PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : null;
    }

    private function isRedirect(Response $r): bool
    {
        return $r->status() >= 300 && $r->status() < 400 && $r->header('Location') !== '';
    }

    private function redirectedAwayFrom(Response $r, string $path): bool
    {
        return $this->isRedirect($r) && ! str_contains($r->header('Location'), $path);
    }

    private function isAbsolute(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    /**
     * Another host than the site itself (BML's payment page), not just an absolute URL back to us.
     */
    private function isExternal(string $url): bool
    {
        return $this->isAbsolute($url) && strcasecmp((string) parse_url($url, PHP_URL_HOST), $this->baseHost) !== 0;
    }

    private function stock(): ?int
    {
        if (! $this->stockReader || $this->order === null) {
            return null;
        }

        return ($this->stockReader)((int) $this->order['product_id'], $this->order['variant_id'] ?? null);
    }
}
