<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\SafeHttp;
use App\Traits\SecureFileUpload;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * "Import from a link" (Seller Centre → Products, for shops allowed pre-orders): a shop pastes the
 * address of a product on an online shop abroad, and the product form is filled from it, for the shop
 * to check and finish. No outside service: online shops put their product details in the page for
 * search engines, and this reads them.
 *
 *  1. The page is fetched safely (App\Support\SafeHttp: public addresses only, size and time limits).
 *  2. Its details are read (parse()): the schema.org Product / ProductGroup data (JSON-LD), then the
 *     share tags (og:title, og:image, product:price...), then the page title and description. A Shopify
 *     shop also gives every option and photo at /products/{handle}.js.
 *  3. Up to MAX_IMAGES photos are downloaded and stored like uploads (checked to be real pictures).
 *  4. A price in MVR is suggested from Admin → Settings → Pre-order imports (suggestPrice()): the
 *     price × the exchange rate, plus shipping per kg when the weight is known, plus the margin.
 *  5. The draft is kept in the session and the product form opens filled with it (Seller\ProductController
 *     ::create()), stock 0 and pre-orders ticked; the link is saved on the product (products.source_url).
 *     Nothing is listed until the shop saves it and an admin approves it, as for any new product.
 */
class ProductImportService
{
    use SecureFileUpload;

    public const MAX_PAGE_BYTES = 3 * 1024 * 1024;

    public const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    public const MAX_IMAGES = 6;

    public const MAX_VARIANTS = 100;

    /** Session: the last import, until the product is saved. */
    public const SESSION = 'product_import';

    /** Image types kept (the form's own uploads take jpeg, png and gif). */
    protected const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    /**
     * Read the product at this address and keep it as the draft for the product form.
     *
     * @return array<string, mixed> the draft (see draft())
     *
     * @throws RuntimeException a message for the shop
     */
    public function import(string $url): array
    {
        $page = SafeHttp::get($url, self::MAX_PAGE_BYTES);
        if ($page === null || ! str_contains($page['type'], 'html')) {
            throw new RuntimeException(__('We couldn\'t read that page. Some shops block this; please fill in the product yourself.'));
        }

        $details = $this->parse($page['body'], $page['url']);
        if ($shopify = $this->shopify($page['url'], $page['body'])) {
            $details = $this->merge($shopify, $details);
        }
        if (($details['name'] ?? '') === '') {
            throw new RuntimeException(__('We couldn\'t find a product on that page. Please check the link, or fill in the product yourself.'));
        }

        $images = [];
        foreach (array_slice($details['images'], 0, self::MAX_IMAGES * 2) as $imageUrl) {
            if (count($images) >= self::MAX_IMAGES) {
                break;
            }
            if ($path = $this->downloadImage($imageUrl)) {
                $images[] = $path;
            }
        }

        $this->forget(); // a previous import's pictures go
        $draft = [
            'source_url' => $url,
            'name' => $details['name'],
            'description' => $details['description'],
            'brand' => $details['brand'],
            'price' => $details['price'],
            'currency' => $details['currency'],
            'weight' => $details['weight'],
            'variants' => $details['variants'],
            'images' => $images,
        ];
        Session::put(self::SESSION, $draft);

        return $draft;
    }

    /**
     * The draft from the last import, or null.
     *
     * @return array{source_url: string, name: string, description: ?string, brand: ?string, price: ?float, currency: ?string, weight: ?float, variants: list<array{attributes: array<string, string>, price: ?float}>, images: list<string>}|null
     */
    public function draft(): ?array
    {
        $draft = Session::get(self::SESSION);

        return is_array($draft) && isset($draft['source_url'], $draft['name']) ? $draft : null;
    }

    /**
     * The draft is done with: its pictures that were not used are deleted.
     *
     * @param  list<string>  $used  stored paths now on a product
     */
    public function forget(array $used = []): void
    {
        foreach ($this->draft()['images'] ?? [] as $path) {
            if (! in_array($path, $used, true)) {
                $this->deleteFile($path);
            }
        }
        Session::forget(self::SESSION);
    }

    // ---- Reading a page ---------------------------------------------------------------------------

    /**
     * The product details a page gives: schema.org data first, then share tags, then the page's title.
     *
     * @return array{name: string, description: ?string, brand: ?string, price: ?float, currency: ?string, weight: ?float, images: list<string>, variants: list<array{attributes: array<string, string>, price: ?float}>}
     */
    public function parse(string $html, string $pageUrl): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($doc);

        $result = ['name' => '', 'description' => null, 'brand' => null, 'price' => null, 'currency' => null, 'weight' => null, 'images' => [], 'variants' => []];

        // 1. schema.org Product (or a ProductGroup with its variants) in JSON-LD
        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
            $data = json_decode(trim((string) $script->textContent), true);
            $product = is_array($data) ? $this->findProduct($data) : null;
            if ($product !== null) {
                $result = $this->merge($this->fromSchema($product, $pageUrl), $result);
                break;
            }
        }

        // 2. Share tags
        $meta = function (string $name) use ($xpath): array {
            $values = [];
            foreach ($xpath->query('//meta[@property="'.$name.'" or @name="'.$name.'" or @itemprop="'.$name.'"]') ?: [] as $node) {
                $value = $node instanceof \DOMElement ? trim($node->getAttribute('content')) : '';
                if ($value !== '') {
                    $values[] = $value;
                }
            }

            return $values;
        };
        $images = array_map(fn ($src) => SafeHttp::absolute($pageUrl, $src), array_merge($meta('og:image'), $meta('og:image:secure_url'), $meta('twitter:image')));
        $result = $this->merge($result, [
            'name' => $meta('og:title')[0] ?? $meta('twitter:title')[0] ?? trim((string) (($titles = $xpath->query('//title')) ? $titles->item(0)?->textContent : '')),
            'description' => $meta('og:description')[0] ?? $meta('description')[0] ?? null,
            'brand' => $meta('product:brand')[0] ?? $meta('og:brand')[0] ?? null,
            'price' => $this->number($meta('product:price:amount')[0] ?? $meta('og:price:amount')[0] ?? $meta('price')[0] ?? null),
            'currency' => $meta('product:price:currency')[0] ?? $meta('og:price:currency')[0] ?? $meta('priceCurrency')[0] ?? null,
            'weight' => null,
            'images' => $images,
            'variants' => [],
        ]);

        return $this->clean($result);
    }

    /**
     * A Shopify shop's own product data (every option and photo), or null for any other shop.
     *
     * @return array{name: string, description: ?string, brand: ?string, price: ?float, currency: ?string, weight: ?float, images: list<string>, variants: list<array{attributes: array<string, string>, price: ?float}>}|null
     */
    public function shopify(string $pageUrl, string $html): ?array
    {
        $path = (string) parse_url($pageUrl, PHP_URL_PATH);
        if (! preg_match('#/products/([^/?\#]+)#', $path, $match) || ! (str_contains($html, 'cdn.shopify.com') || str_contains($html, 'Shopify.shop'))) {
            return null;
        }
        $parts = parse_url($pageUrl);
        $response = SafeHttp::get(($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').'/products/'.rawurlencode(preg_replace('/\.(js|json)$/', '', $match[1]) ?? '').'.js', self::MAX_PAGE_BYTES);
        $data = $response ? json_decode($response['body'], true) : null;
        if (! is_array($data) || ! isset($data['title'])) {
            return null;
        }

        $names = array_map(fn ($option) => is_array($option) ? (string) ($option['name'] ?? '') : (string) $option, (array) ($data['options'] ?? []));
        $variants = [];
        foreach (array_slice((array) ($data['variants'] ?? []), 0, self::MAX_VARIANTS) as $variant) {
            $attributes = [];
            foreach ($names as $i => $name) {
                $value = trim((string) ($variant['option'.($i + 1)] ?? ''));
                if ($name !== '' && $value !== '' && ! ($name === 'Title' && $value === 'Default Title')) {
                    $attributes[$name] = $value;
                }
            }
            if ($attributes !== []) {
                $variants[] = ['attributes' => $attributes, 'price' => isset($variant['price']) ? round((float) $variant['price'] / 100, 2) : null];
            }
        }
        $grams = (float) ($data['variants'][0]['grams'] ?? $data['variants'][0]['weight'] ?? 0);
        preg_match('/Shopify\.currency\s*=\s*\{[^}]*"active"\s*:\s*"([A-Z]{3})"/', $html, $currency);

        return $this->clean([
            'name' => (string) $data['title'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'brand' => isset($data['vendor']) ? (string) $data['vendor'] : null,
            'price' => isset($data['price']) ? round((float) $data['price'] / 100, 2) : null,
            'currency' => $currency[1] ?? null,
            'weight' => $grams > 0 ? $grams / 1000 : null,
            'images' => array_map(fn ($src) => SafeHttp::absolute($pageUrl, is_array($src) ? (string) ($src['src'] ?? '') : (string) $src), (array) ($data['images'] ?? [])),
            'variants' => count($variants) > 1 ? $variants : [],
        ]);
    }

    /**
     * The first schema.org Product or ProductGroup in some JSON-LD (a list, an @graph, or nested).
     *
     * @param  array<mixed>  $data
     * @return array<string, mixed>|null
     */
    protected function findProduct(array $data, int $depth = 0): ?array
    {
        if ($depth > 6) {
            return null;
        }
        $types = array_map('strval', (array) ($data['@type'] ?? []));
        if (array_intersect($types, ['Product', 'ProductGroup', 'IndividualProduct', 'ProductModel']) !== []) {
            return $data;
        }
        foreach ($data as $value) {
            if (is_array($value) && ($found = $this->findProduct($value, $depth + 1))) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array{name: string, description: ?string, brand: ?string, price: ?float, currency: ?string, weight: ?float, images: list<string>, variants: list<array{attributes: array<string, string>, price: ?float}>}
     */
    protected function fromSchema(array $product, string $pageUrl): array
    {
        [$price, $currency] = $this->offer($product['offers'] ?? null);
        $variants = [];
        $variesBy = array_map(fn ($property) => strtolower((string) preg_replace('#^.*/#', '', (string) $property)), (array) ($product['variesBy'] ?? []));
        foreach (array_slice((array) ($product['hasVariant'] ?? []), 0, self::MAX_VARIANTS) as $variant) {
            if (! is_array($variant)) {
                continue;
            }
            $attributes = [];
            foreach (array_unique(array_merge($variesBy, ['size', 'color', 'colour', 'material', 'pattern'])) as $property) {
                $value = $variant[$property] ?? null;
                $value = is_array($value) ? ($value['name'] ?? null) : $value;
                if (is_scalar($value) && trim((string) $value) !== '') {
                    $attributes[ucfirst($property === 'color' ? 'colour' : $property)] = trim((string) $value);
                }
            }
            if ($attributes !== []) {
                $variants[] = ['attributes' => $attributes, 'price' => $this->offer($variant['offers'] ?? null)[0]];
                $price ??= $variants[array_key_last($variants)]['price'];
                $currency ??= $this->offer($variant['offers'] ?? null)[1];
            }
        }

        $images = [];
        foreach ((array) ($product['image'] ?? []) as $key => $image) {
            $src = is_array($image) ? ($image['url'] ?? $image['contentUrl'] ?? null) : ($key === 'url' || is_int($key) ? $image : null);
            if (is_string($src) && $src !== '') {
                $images[] = SafeHttp::absolute($pageUrl, $src);
            }
        }
        $brand = $product['brand'] ?? null;

        return [
            'name' => is_scalar($product['name'] ?? null) ? (string) $product['name'] : '',
            'description' => is_scalar($product['description'] ?? null) ? (string) $product['description'] : null,
            'brand' => is_array($brand) ? (isset($brand['name']) && is_scalar($brand['name']) ? (string) $brand['name'] : null) : (is_scalar($brand) ? (string) $brand : null),
            'price' => $price,
            'currency' => $currency,
            'weight' => $this->weight($product['weight'] ?? null),
            'images' => $images,
            'variants' => count($variants) > 1 ? $variants : [],
        ];
    }

    /**
     * The price and currency of a schema.org offer (an Offer, an AggregateOffer or a list).
     *
     * @return array{0: ?float, 1: ?string}
     */
    protected function offer(mixed $offers): array
    {
        if (! is_array($offers)) {
            return [null, null];
        }
        if (array_is_list($offers)) {
            $offers = $offers[0] ?? [];
            if (! is_array($offers)) {
                return [null, null];
            }
        }
        $price = $this->number($offers['price'] ?? $offers['lowPrice'] ?? ($offers['priceSpecification']['price'] ?? null));
        $currency = $offers['priceCurrency'] ?? ($offers['priceSpecification']['priceCurrency'] ?? null);

        return [$price, is_string($currency) ? $currency : null];
    }

    /** A schema.org weight ({value, unitCode}) in kg, or null. */
    protected function weight(mixed $weight): ?float
    {
        $value = $this->number(is_array($weight) ? ($weight['value'] ?? null) : $weight);
        if ($value === null || $value <= 0) {
            return null;
        }
        $unit = strtolower(is_array($weight) ? (string) ($weight['unitCode'] ?? $weight['unitText'] ?? 'kg') : (string) preg_replace('/[0-9.,\s]/', '', (string) $weight));

        return match (true) {
            in_array($unit, ['grm', 'g', 'gram', 'grams'], true) => $value / 1000,
            in_array($unit, ['lbr', 'lb', 'lbs', 'pound', 'pounds'], true) => $value * 0.4536,
            in_array($unit, ['onz', 'oz', 'ounce', 'ounces'], true) => $value * 0.02835,
            default => $value,
        };
    }

    protected function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (! is_string($value)) {
            return null;
        }
        $clean = preg_replace('/[^0-9.,]/', '', $value) ?? '';
        // "1.299,00" and "1,299.00": the last separator is the decimal one
        if (preg_match('/[.,](\d{1,2})$/', $clean, $decimals)) {
            $whole = preg_replace('/[.,]/', '', substr($clean, 0, -strlen($decimals[0]))) ?? '';
            $clean = $whole.'.'.$decimals[1];
        } else {
            $clean = str_replace([',', '.'], '', $clean);
        }

        return $clean !== '' && is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * $first's details, with $second filling what $first lacks (pictures: $first's, then $second's).
     *
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     * @return array<string, mixed>
     */
    protected function merge(array $first, array $second): array
    {
        $merged = $first;
        foreach ($second as $key => $value) {
            if ($key === 'images') {
                $merged['images'] = array_values(array_unique(array_merge($first['images'] ?? [], $value)));
            } elseif (($merged[$key] ?? null) === null || $merged[$key] === '' || $merged[$key] === []) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Plain text, sensible lengths, http(s) pictures only.
     *
     * @param  array<string, mixed>  $details
     * @return array{name: string, description: ?string, brand: ?string, price: ?float, currency: ?string, weight: ?float, images: list<string>, variants: list<array{attributes: array<string, string>, price: ?float}>}
     */
    protected function clean(array $details): array
    {
        $text = function (mixed $value, int $max): ?string {
            if (! is_scalar($value)) {
                return null;
            }
            $plain = html_entity_decode(strip_tags(preg_replace('#<(br|/p|/li|/div)\b[^>]*>#i', "\n", (string) $value) ?? ''), ENT_QUOTES | ENT_HTML5);
            $plain = trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n\s*\n+/", "\n\n", $plain) ?? '') ?? '');

            return $plain === '' ? null : Str::limit($plain, $max, '');
        };
        $currency = strtoupper(trim((string) ($details['currency'] ?? '')));

        return [
            'name' => (string) $text($details['name'] ?? '', 255),
            'description' => $text($details['description'] ?? null, 5000),
            'brand' => $text($details['brand'] ?? null, 120),
            'price' => isset($details['price']) && $details['price'] > 0 ? round((float) $details['price'], 2) : null,
            'currency' => preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null,
            'weight' => isset($details['weight']) && $details['weight'] > 0 ? round((float) $details['weight'], 2) : null,
            'images' => array_values(array_unique(array_filter((array) ($details['images'] ?? []), fn ($src) => is_string($src) && preg_match('#^https?://#i', $src)))),
            'variants' => array_values((array) ($details['variants'] ?? [])),
        ];
    }

    /** Download a picture and store it like an upload: its path, or null when it is not a usable picture. */
    protected function downloadImage(string $url): ?string
    {
        $image = SafeHttp::get($url, self::MAX_IMAGE_BYTES);
        if ($image === null) {
            return null;
        }
        $temp = tempnam(sys_get_temp_dir(), 'imp');
        if ($temp === false) {
            return null;
        }
        try {
            file_put_contents($temp, $image['body']);
            $info = @getimagesize($temp);
            $extension = self::IMAGE_TYPES[$info['mime'] ?? ''] ?? null;
            if ($info === false || $extension === null) {
                return null;
            }

            return $this->storeFileSecurely(new UploadedFile($temp, 'import.'.$extension, $info['mime'], null, true), 'products', array_keys(self::IMAGE_TYPES), (int) (self::MAX_IMAGE_BYTES / 1024));
        } finally {
            @unlink($temp);
        }
    }

    // ---- The price in MVR ----------------------------------------------------------------------------

    /**
     * Exchange rates to MVR from Admin → Settings → Pre-order imports, one per line ("USD 15.42").
     *
     * @return array<string, float>
     */
    public static function rates(): array
    {
        $rates = ['MVR' => 1.0];
        foreach (preg_split('/\R/', (string) Setting::get('import_exchange_rates', '')) ?: [] as $line) {
            if (preg_match('/^\s*([A-Za-z]{3})\s*[=:\s]\s*([0-9]+(?:\.[0-9]+)?)\s*$/', $line, $match) && (float) $match[2] > 0) {
                $rates[strtoupper($match[1])] = (float) $match[2];
            }
        }

        return $rates;
    }

    /**
     * A suggested price in MVR: the price × the rate, plus shipping per kg when the weight is known,
     * plus the margin. Null when there is no price, or no rate for its currency.
     *
     * @return array{price: float, rate: float, shipping: float, margin: float}|null
     */
    public function suggestPrice(?float $price, ?string $currency, ?float $weightKg): ?array
    {
        $rate = self::rates()[strtoupper((string) $currency)] ?? null;
        if ($price === null || $price <= 0 || $rate === null) {
            return null;
        }
        $shipping = $weightKg !== null ? round($weightKg * max(0, (float) Setting::get('import_shipping_per_kg', 0)), 2) : 0.0;
        $margin = max(0, (float) Setting::get('import_margin_percent', 0));

        return ['price' => round(($price * $rate + $shipping) * (1 + $margin / 100), 2), 'rate' => $rate, 'shipping' => $shipping, 'margin' => $margin];
    }
}
