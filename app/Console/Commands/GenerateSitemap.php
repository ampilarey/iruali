<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\File;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate XML sitemap for SEO';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating sitemap...');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Add static pages
        $staticPages = [
            route('home') => '1.0',
            route('shop') => '0.9',
            route('products.index') => '0.8',
            route('categories.index') => '0.8',
        ];

        foreach ($staticPages as $url => $priority) {
            $xml .= $this->generateUrlElement($url, $priority, 'daily');
        }

        // Add product pages
        $products = Product::where('is_active', true)->get();
        $this->info("Adding {$products->count()} products to sitemap...");
        
        foreach ($products as $product) {
            $xml .= $this->generateUrlElement(
                route('products.show', $product->slug),
                '0.7',
                'weekly',
                $product->updated_at
            );
        }

        // Add category pages
        $categories = Category::where('status', 'active')->get();
        $this->info("Adding {$categories->count()} categories to sitemap...");
        
        foreach ($categories as $category) {
            $xml .= $this->generateUrlElement(
                route('categories.show', $category->slug),
                '0.6',
                'weekly',
                $category->updated_at
            );
        }

        // Add seller pages
        $sellers = User::where('is_seller', true)
            ->where('seller_approved', true)
            ->get();
        $this->info("Adding {$sellers->count()} sellers to sitemap...");
        
        foreach ($sellers as $seller) {
            $xml .= $this->generateUrlElement(
                route('users.show', $seller->id),
                '0.5',
                'weekly',
                $seller->updated_at
            );
        }

        $xml .= '</urlset>';

        // Save sitemap
        $path = public_path('sitemap.xml');
        File::put($path, $xml);

        $this->info("Sitemap generated successfully at: {$path}");
        $this->info("Sitemap URL: " . url('sitemap.xml'));
    }

    /**
     * Generate a URL element for the sitemap
     */
    private function generateUrlElement(string $url, string $priority, string $changefreq, $lastmod = null): string
    {
        $xml = "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($url) . "</loc>\n";
        
        if ($lastmod) {
            $xml .= "    <lastmod>" . $lastmod->toISOString() . "</lastmod>\n";
        }
        
        $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
        $xml .= "    <priority>{$priority}</priority>\n";
        $xml .= "  </url>\n";
        
        return $xml;
    }
} 