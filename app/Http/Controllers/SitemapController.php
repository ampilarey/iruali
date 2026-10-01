<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
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
            $urls = [
                [route('home'), now(), 'daily', '1.0'],
                [route('shop'), now(), 'daily', '0.9'],
                [route('products.index'), now(), 'daily', '0.8'],
                [route('categories.index'), now(), 'weekly', '0.7'],
                [route('deals'), now(), 'daily', '0.7'],
                [route('help'), now(), 'monthly', '0.4'],
            ];
            foreach (['terms', 'refunds', 'delivery', 'privacy', 'security', 'about'] as $policy) {
                $urls[] = [route('policies.'.$policy), now(), 'monthly', '0.3'];
            }
            Product::query()->where('is_active', true)->select(['slug', 'updated_at'])->orderBy('id')->lazy()
                ->each(function ($p) use (&$urls) {
                    $urls[] = [route('products.show', $p->slug), $p->updated_at, 'weekly', '0.7'];
                });
            Category::query()->where('status', 'active')->select(['slug', 'updated_at'])->get()
                ->each(function ($c) use (&$urls) {
                    $urls[] = [route('categories.show', $c->slug), $c->updated_at, 'weekly', '0.6'];
                });
            User::query()->where('is_seller', true)->where('seller_approved', true)->select(['id', 'updated_at'])->get()
                ->each(function ($s) use (&$urls) {
                    $urls[] = [route('sellers.show', $s), $s->updated_at, 'weekly', '0.5'];
                });

            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($urls as [$loc, $mod, $freq, $prio]) {
                $out .= '  <url><loc>'.htmlspecialchars($loc, ENT_XML1).'</loc><lastmod>'.($mod ?? now())->toDateString()."</lastmod><changefreq>{$freq}</changefreq><priority>{$prio}</priority></url>\n";
            }

            return $out.'</urlset>'."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
