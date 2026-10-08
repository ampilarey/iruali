<?php

use App\Models\Product;
use App\Services\BrandService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brands become one shared list. Products keep the brand name in products.brand (search, feeds and
 * filters read it) and now also point at the brand through products.brand_id. Every brand name
 * already in use becomes a brand; the most common spelling of a name gives the brand its name.
 */
return new class extends Migration
{
    /** The demo shops' own labels (MarketplaceDemoSeeder), matched by the demo account's email. */
    private const DEMO_SHOP_BRANDS = [
        'islandcrafts@example.com' => 'Island Crafts',
        'thulhaadhoo@example.com' => 'Thulhaadhoo Lacquer',
        'maafushifresh@example.com' => 'Maafushi Fresh',
        'reefline@example.com' => 'Reefline',
        'hulhumalestyle@example.com' => 'Hulhumalé Style',
        'solarsouth@example.com' => 'Solar South',
    ];

    /**
     * The early generic demo products (listed under the admin account): [sku, slug, brand]. The
     * brand is simply what the product is, so these are matched on SKU or slug alone.
     */
    private const DEMO_PRODUCT_BRANDS = [
        ['IPHONE15PRO', 'iphone-15-pro', 'Apple'],
        ['MACBOOKAIRM2', 'macbook-air-m2', 'Apple'],
        ['NIKEAIRMAX270', 'nike-air-max-270', 'Nike'],
        ['SAMSUNG4KTV', 'samsung-4k-smart-tv', 'Samsung'],
    ];

    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            // The name in lower case without spaces or punctuation: how typed names are matched
            $table->string('key', 120)->unique();
            $table->string('logo')->nullable();
            $table->json('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index('name');
        });

        // Old names and old web addresses of a brand, kept after a rename or a merge
        Schema::create('brand_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('key', 120)->nullable()->unique();
            $table->string('slug', 140)->nullable()->unique();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('brand')->constrained()->nullOnDelete();
        });

        $this->brandDemoProducts();
        app(BrandService::class)->linkProducts();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });
        Schema::dropIfExists('brand_aliases');
        Schema::dropIfExists('brands');
    }

    /**
     * Demo products only (the test site and local installs): give them the brand on the box so the
     * brand pages have something to show. Only products with no brand are touched: the demo shops'
     * products (matched by the demo accounts' emails) and the early generic demo products.
     */
    private function brandDemoProducts(): void
    {
        $sellers = DB::table('users')->whereIn('email', array_keys(self::DEMO_SHOP_BRANDS))->pluck('id', 'email');
        $unbranded = fn () => DB::table('products')->where(fn ($q) => $q->whereNull('brand')->orWhere('brand', ''));
        $ids = [];

        foreach (self::DEMO_SHOP_BRANDS as $email => $brand) {
            if (isset($sellers[$email])) {
                $ids = array_merge($ids, $unbranded()->where('seller_id', $sellers[$email])->pluck('id')->all());
                $unbranded()->where('seller_id', $sellers[$email])->update(['brand' => $brand]);
            }
        }

        foreach (self::DEMO_PRODUCT_BRANDS as [$sku, $slug, $brand]) {
            $match = fn () => $unbranded()->where(fn ($q) => $q->where('sku', $sku)->orWhere('slug', $slug));
            $ids = array_merge($ids, $match()->pluck('id')->all());
            $match()->update(['brand' => $brand]);
        }

        // The brand is part of what search matches
        Product::withTrashed()->whereIn('id', $ids)->lazyById(200)->each(function (Product $product) {
            $product->timestamps = false;
            $product->forceFill(['search_text' => $product->buildSearchText()])->saveQuietly();
        });
    }
};
