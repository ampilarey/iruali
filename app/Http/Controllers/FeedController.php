<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\FeedToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Product feeds for Google Merchant Center (RSS 2.0) and the Facebook / Instagram catalogue (CSV).
 * Both need the feed token from Settings in the query string and are cached for an hour.
 */
class FeedController extends Controller
{
    public const CACHE_SECONDS = 3600;

    public function googleMerchant(Request $request)
    {
        $this->authorizeFeed($request);

        $xml = Cache::remember('feeds.google-merchant', self::CACHE_SECONDS, fn () => $this->inEnglish(fn () => $this->buildRss()));

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
            'Vary' => 'Accept-Encoding',
        ]);
    }

    public function facebookCatalog(Request $request)
    {
        $this->authorizeFeed($request);

        $csv = Cache::remember('feeds.facebook-catalog', self::CACHE_SECONDS, fn () => $this->inEnglish(fn () => $this->buildCsv()));

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="facebook-catalog.csv"',
            'Cache-Control' => 'public, max-age=3600',
            'Vary' => 'Accept-Encoding',
        ]);
    }

    /**
     * Every sellable listing as feed items: one per product, or one per variant (sharing an
     * item_group_id) when the product is sold in variants with attributes.
     */
    public static function items(): array
    {
        $items = [];
        $base = 'P';

        Product::query()->active()
            ->with(['images', 'seller', 'category.parent', 'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->orderBy('id')
            ->lazy()
            ->each(function (Product $product) use (&$items, $base) {
                $images = $product->getRelation('images')->sortByDesc('is_main')->pluck('url')->map(fn ($u) => self::absolute($u))->values();
                if ($images->isEmpty() && $product->main_image) {
                    $images = collect([self::absolute($product->main_image)]);
                }
                $categoryPath = collect([$product->category?->parent, $product->category])->filter()
                    ->map(fn ($c) => $c->getTranslation('name', 'en', false) ?: $c->getTranslation('name', 'dv', false))->implode(' > ');
                $common = [
                    'link' => route('products.show', $product->slug),
                    'description' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($product->getTranslation('description', 'en', false) ?: $product->getTranslation('description', 'dv', false))))), 5000, ''),
                    'brand' => (string) $product->brand,
                    'product_type' => $categoryPath,
                    'additional_images' => $images->slice(1, 10)->values()->all(),
                ];
                $name = $product->getTranslation('name', 'en', false) ?: $product->getTranslation('name', 'dv', false);
                // A shop on holiday takes no orders: its products are listed as out of stock until it is back
                $onHoliday = (bool) $product->seller?->isOnHoliday();

                $variants = $product->has_variants ? $product->variants->filter(fn ($v) => $v->attributes_list !== []) : collect();
                if ($variants->isNotEmpty()) {
                    foreach ($variants as $variant) {
                        $variant->setRelation('product', $product);
                        $preorderDate = $onHoliday ? null : self::preorderDate($product, $variant); // sold out but open for pre-orders
                        $items[] = $common + [
                            'id' => $base.$product->id.'-V'.$variant->id,
                            'item_group_id' => $base.$product->id,
                            'title' => Str::limit($name.' - '.$variant->displayName(), 150, ''),
                            'price' => number_format($variant->effectivePrice(), 2, '.', '').' MVR',
                            'availability' => $variant->stock_quantity > 0 && ! $onHoliday ? 'in_stock' : ($preorderDate ? 'preorder' : 'out_of_stock'),
                            'availability_date' => $preorderDate,
                            'image_link' => $variant->image ? self::absolute($variant->image) : ($images->first() ?? asset('images/product-placeholder.svg')),
                            'attributes' => $variant->attributes_list,
                        ];
                    }

                    return;
                }

                $preorderDate = $onHoliday ? null : self::preorderDate($product); // sold out but open for pre-orders
                $items[] = $common + [
                    'id' => $base.$product->id,
                    'item_group_id' => null,
                    'title' => Str::limit($name, 150, ''),
                    'price' => number_format((float) $product->final_price, 2, '.', '').' MVR',
                    'availability' => $product->effectiveStock() > 0 && ! $onHoliday ? 'in_stock' : ($preorderDate ? 'preorder' : 'out_of_stock'),
                    'availability_date' => $preorderDate,
                    'image_link' => $images->first() ?? asset('images/product-placeholder.svg'),
                    'attributes' => [],
                ];
            });

        return $items;
    }

    protected function buildRss(): string
    {
        $x = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $out .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">'."\n<channel>\n";
        $out .= '<title>'.$x(config('app.name')).'</title>'."\n";
        $out .= '<link>'.$x(config('app.url')).'</link>'."\n";
        $out .= '<description>'.$x('Products on '.config('app.name')).'</description>'."\n";

        foreach (self::items() as $item) {
            $out .= "<item>\n";
            $out .= '<g:id>'.$x($item['id']).'</g:id>'."\n";
            $out .= '<title>'.$x($item['title']).'</title>'."\n";
            $out .= '<description>'.$x($item['description'] !== '' ? $item['description'] : $item['title']).'</description>'."\n";
            $out .= '<link>'.$x($item['link']).'</link>'."\n";
            $out .= '<g:image_link>'.$x($item['image_link']).'</g:image_link>'."\n";
            foreach ($item['additional_images'] as $image) {
                $out .= '<g:additional_image_link>'.$x($image).'</g:additional_image_link>'."\n";
            }
            $out .= '<g:availability>'.$item['availability'].'</g:availability>'."\n";
            if (! empty($item['availability_date'])) {
                $out .= '<g:availability_date>'.$x($item['availability_date']).'</g:availability_date>'."\n"; // required with preorder
            }
            $out .= '<g:price>'.$x($item['price']).'</g:price>'."\n";
            if ($item['brand'] !== '') {
                $out .= '<g:brand>'.$x($item['brand']).'</g:brand>'."\n";
            } else {
                $out .= "<g:identifier_exists>no</g:identifier_exists>\n";
            }
            $out .= "<g:condition>new</g:condition>\n";
            if ($item['item_group_id']) {
                $out .= '<g:item_group_id>'.$x($item['item_group_id']).'</g:item_group_id>'."\n";
            }
            foreach ($item['attributes'] as $key => $value) {
                $tag = match (strtolower($key)) {
                    'size' => 'g:size', 'colour', 'color' => 'g:color', 'material' => 'g:material', 'pattern' => 'g:pattern',
                    default => null,
                };
                if ($tag) {
                    $out .= '<'.$tag.'>'.$x($value).'</'.$tag.'>'."\n";
                }
            }
            if ($item['product_type'] !== '') {
                $out .= '<g:product_type>'.$x($item['product_type']).'</g:product_type>'."\n";
            }
            $out .= "<g:shipping><g:country>MV</g:country></g:shipping>\n";
            $out .= "</item>\n";
        }

        return $out."</channel>\n</rss>\n";
    }

    protected function buildCsv(): string
    {
        $columns = ['id', 'title', 'description', 'availability', 'condition', 'price', 'link', 'image_link', 'brand', 'item_group_id', 'additional_image_link', 'product_type', 'size', 'color'];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $columns, ',', '"', '\\', "\n");
        foreach (self::items() as $item) {
            $attributes = array_change_key_case($item['attributes'], CASE_LOWER);
            fputcsv($handle, [
                $item['id'],
                $item['title'],
                $item['description'] !== '' ? $item['description'] : $item['title'],
                // Meta's catalogue takes only "in stock" / "out of stock": an open pre-order can be bought now
                in_array($item['availability'], ['in_stock', 'preorder'], true) ? 'in stock' : 'out of stock',
                'new',
                $item['price'],
                $item['link'],
                $item['image_link'],
                $item['brand'],
                (string) $item['item_group_id'],
                implode(',', $item['additional_images']),
                $item['product_type'],
                (string) ($attributes['size'] ?? ''),
                (string) ($attributes['colour'] ?? $attributes['color'] ?? ''),
            ], ',', '"', '\\', "\n");
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    protected function authorizeFeed(Request $request): void
    {
        abort_unless(FeedToken::valid($request->query('token')), 403, 'A valid feed token is required.');
    }

    /**
     * Feeds are built in English whatever the visitor's language.
     */
    protected function inEnglish(callable $build): string
    {
        $locale = app()->getLocale();
        app()->setLocale('en');
        try {
            return $build();
        } finally {
            app()->setLocale($locale);
        }
    }

    protected static function absolute(string $url): string
    {
        return str_starts_with($url, 'http') ? $url : asset(ltrim($url, '/'));
    }

    /**
     * When a sold-out product (or option) open for pre-orders is expected to ship, as Google's
     * availability_date wants it (ISO 8601 with the Maldives offset), or null when it is not one.
     */
    protected static function preorderDate(Product $product, ?\App\Models\ProductVariant $variant = null): ?string
    {
        $date = $product->preorder_ship_date;
        if ($date === null || ! app(\App\Services\PreorderService::class)->isPreorderable($product, $variant)) {
            return null;
        }

        return $date->copy()->startOfDay()->format('Y-m-d\TH:iO');
    }
}
