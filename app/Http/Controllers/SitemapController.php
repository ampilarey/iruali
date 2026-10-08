<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\LocaleUrl;
use Illuminate\Support\Facades\Cache;

/**
 * robots.txt and sitemap.xml, built from the database so they are always current and need no file
 * in the docroot (the deploy copies only a few public files).
 */
class SitemapController extends Controller
{
    public function robots()
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin', 'Disallow: /seller', 'Disallow: /account', 'Disallow: /cart', 'Disallow: /checkout',
            'Disallow: /orders', 'Disallow: /wishlist', 'Disallow: /compare', 'Disallow: /saved', 'Disallow: /search',
            'Disallow: /login', 'Disallow: /register', 'Disallow: /forgot-password', 'Disallow: /reset-password',
            'Disallow: /verification', 'Disallow: /2fa', 'Disallow: /profile', 'Disallow: /track', 'Disallow: /api',
            'Disallow: /payments', 'Disallow: /returns',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap()
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function () {
            // [route name, parameters, last modified, change frequency, priority]; every page is
            // listed in English and Dhivehi (/dv/...), each pointing at the other with hreflang.
            $pages = [
                ['home', [], now(), 'daily', '1.0'],
                ['shop', [], now(), 'daily', '0.9'],
                ['products.index', [], now(), 'daily', '0.8'],
                ['categories.index', [], now(), 'weekly', '0.7'],
                ['deals', [], now(), 'daily', '0.7'],
                ['help', [], now(), 'monthly', '0.4'],
            ];
            foreach (['terms', 'refunds', 'delivery', 'privacy', 'security', 'about'] as $policy) {
                $pages[] = ['policies.'.$policy, [], now(), 'monthly', '0.3'];
            }
            Product::query()->where('is_active', true)->select(['slug', 'updated_at'])->orderBy('id')->lazy()
                ->each(function ($p) use (&$pages) {
                    $pages[] = ['products.show', $p->slug, $p->updated_at, 'weekly', '0.7'];
                });
            Category::query()->where('status', 'active')->select(['slug', 'updated_at'])->get()
                ->each(function ($c) use (&$pages) {
                    $pages[] = ['categories.show', $c->slug, $c->updated_at, 'weekly', '0.6'];
                });
            // Brand pages, once a brand has enough products on sale to be worth a search result
            $pages[] = ['brands.index', [], now(), 'weekly', '0.6'];
            Brand::query()->listed()->withActiveProductCount()->get()
                ->filter(fn (Brand $b) => $b->isIndexable($b->activeProductCount()))
                ->each(function (Brand $b) use (&$pages) {
                    $pages[] = ['brands.show', $b->slug, $b->updated_at, 'weekly', '0.5'];
                });
            User::query()->where('is_seller', true)->where('seller_approved', true)->select(['id', 'updated_at'])->get()
                ->each(function ($s) use (&$pages) {
                    $pages[] = ['sellers.show', $s->id, $s->updated_at, 'weekly', '0.5'];
                });

            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
            foreach ($pages as [$name, $params, $mod, $freq, $prio]) {
                $locs = ['en' => LocaleUrl::route($name, $params, 'en'), 'dv' => LocaleUrl::route($name, $params, 'dv')];
                $links = '';
                foreach ($locs + ['x-default' => $locs['en']] as $hreflang => $href) {
                    $links .= '<xhtml:link rel="alternate" hreflang="'.$hreflang.'" href="'.htmlspecialchars($href, ENT_XML1).'"/>';
                }
                foreach ($locs as $loc) {
                    $out .= '  <url><loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>'.$links.'<lastmod>'.($mod ?? now())->toDateString()."</lastmod><changefreq>{$freq}</changefreq><priority>{$prio}</priority></url>\n";
                }
            }

            return $out.'</urlset>'."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
