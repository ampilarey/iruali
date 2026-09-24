<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * One product listing for the whole storefront: shop, departments, search, deals and seller shops
 * all share the same filters, facet counts and sort order.
 */
class CatalogService
{
    public const SORTS = [
        'featured' => 'Featured',
        'newest' => 'Newest',
        'price_low' => 'Price: low to high',
        'price_high' => 'Price: high to low',
        'discount' => 'Biggest savings',
        'rating' => 'Top rated',
    ];

    public const PER_PAGE = [24, 48, 96];

    /**
     * Filters the page itself pins (a department page always filters by its department, and so on).
     *
     * @var array{category?: Category, seller?: User, brand?: string, deals?: bool}
     */
    protected array $locked = [];

    protected Request $request;

    public function listing(Request $request, array $locked = []): array
    {
        $this->request = $request;
        $this->locked = $locked;

        $q = trim((string) $request->query('q', ''));
        $sort = array_key_exists($request->query('sort'), self::SORTS) ? $request->query('sort') : ($q !== '' ? 'relevance' : 'featured');
        $perPage = in_array((int) $request->query('per_page'), self::PER_PAGE, true) ? (int) $request->query('per_page') : self::PER_PAGE[0];
        $view = $request->query('view') === 'list' ? 'list' : 'grid';

        $query = $this->filtered()
            ->with(['category', 'mainImage', 'seller'])
            ->withCount(['reviews as rating_count' => fn ($r) => $r->where('is_approved', true)])
            ->withAvg(['reviews as rating_avg' => fn ($r) => $r->where('is_approved', true)], 'rating');

        $this->sort($query, $sort, $q);

        return [
            'products' => $query->paginate($perPage)->withQueryString(),
            'facets' => $this->facets(),
            'active' => $this->activeFilters(),
            'sort' => $sort,
            'sorts' => ($q !== '' ? ['relevance' => 'Best match'] : []) + self::SORTS,
            'perPage' => $perPage,
            'view' => $view,
            'q' => $q,
        ];
    }

    /**
     * Active, visible products with every filter applied, except the one named in $except
     * (so each facet can count what picking one of its options would return).
     */
    public function filtered(?string $except = null): Builder
    {
        $r = $this->request;
        $query = Product::query()->active();

        if (($q = trim((string) $r->query('q', ''))) !== '') {
            // Names and descriptions are JSON (one value per language); JSON compares case-sensitively on MySQL.
            $like = '%'.mb_strtolower(str_replace(['%', '_'], ['\%', '\_'], $q)).'%';
            $query->where(fn ($w) => $w->whereRaw('LOWER(CAST(name AS CHAR)) LIKE ?', [$like])
                ->orWhereRaw('LOWER(CAST(description AS CHAR)) LIKE ?', [$like])
                ->orWhere('sku', 'like', $like)
                ->orWhere('brand', 'like', $like)
                ->orWhere('model', 'like', $like)
                ->orWhereHas('category', fn ($c) => $c->whereRaw('LOWER(CAST(name AS CHAR)) LIKE ?', [$like])));
        }

        if ($except !== 'category' && ($ids = $this->categoryIds())) {
            $query->whereIn('category_id', $ids);
        }

        if (isset($this->locked['seller'])) {
            $query->where('seller_id', $this->locked['seller']->id);
        } elseif ($except !== 'seller' && ($sellers = $this->ids('seller'))) {
            $query->whereIn('seller_id', $sellers);
        }

        if (isset($this->locked['brand'])) {
            $query->where('brand', $this->locked['brand']);
        } elseif ($except !== 'brand' && ($brands = $this->strings('brand'))) {
            $query->whereIn('brand', $brands);
        }

        if ($except !== 'price') {
            if (is_numeric($min = $r->query('min_price'))) {
                $query->where('price', '>=', (float) $min);
            }
            if (is_numeric($max = $r->query('max_price'))) {
                $query->where('price', '<=', (float) $max);
            }
        }

        if ($except !== 'deals' && $this->dealsOnly()) {
            $query->onSale();
        }

        if ($except !== 'in_stock' && $r->boolean('in_stock')) {
            $query->inStock();
        }

        if ($except !== 'rating' && in_array((int) $r->query('rating'), [3, 4], true)) {
            $min = (int) $r->query('rating');
            $query->whereHas('reviews', fn ($rv) => $rv->where('is_approved', true), '>=', 1)
                ->whereRaw('(select avg(rating) from product_reviews where product_reviews.product_id = products.id and is_approved = 1) >= ?', [$min]);
        }

        return $query;
    }

    protected function sort(Builder $query, string $sort, string $q): void
    {
        // Things you can buy come first, whatever the sort.
        $query->orderByRaw('CASE WHEN stock_quantity > 0 THEN 0 ELSE 1 END');

        match ($sort) {
            'relevance' => $query->orderByRaw('CASE WHEN LOWER(CAST(name AS CHAR)) LIKE ? THEN 0 WHEN LOWER(brand) LIKE ? THEN 1 ELSE 2 END', array_fill(0, 2, '%'.mb_strtolower($q).'%'))->latest(),
            'newest' => $query->latest(),
            'price_low' => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            'discount' => $query->orderByRaw('CASE WHEN compare_price > price THEN (compare_price - price) / compare_price ELSE 0 END DESC'),
            'rating' => $query->orderByDesc('rating_avg')->orderByDesc('rating_count'),
            default => $query->orderByDesc('is_featured')->latest(),
        };

        $query->orderBy('products.id');
    }

    protected function facets(): array
    {
        $categoryCounts = $this->filtered('category')->toBase()
            ->selectRaw('category_id, count(*) as aggregate')->groupBy('category_id')->pluck('aggregate', 'category_id');

        $categories = Category::active()->root()->with(['children' => fn ($c) => $c->active()])->get()
            ->map(function (Category $category) use ($categoryCounts) {
                $ids = $category->children->pluck('id')->push($category->id);
                $category->facet_count = (int) $ids->sum(fn ($id) => $categoryCounts[$id] ?? 0);

                return $category;
            })
            ->filter(fn ($c) => $c->facet_count > 0 || $this->selectedCategory()?->id === $c->id)
            ->sortBy(fn ($c) => $c->localized_name)
            ->values();

        $brands = isset($this->locked['brand']) ? collect() : $this->filtered('brand')->toBase()->whereNotNull('brand')->where('brand', '!=', '')
            ->selectRaw('brand, count(*) as aggregate')->groupBy('brand')->orderBy('brand')->pluck('aggregate', 'brand');

        $sellers = collect();
        if (! isset($this->locked['seller'])) {
            $sellerCounts = $this->filtered('seller')->toBase()->whereNotNull('seller_id')
                ->selectRaw('seller_id, count(*) as aggregate')->groupBy('seller_id')->pluck('aggregate', 'seller_id');
            $sellers = User::whereIn('id', $sellerCounts->keys())->get(['id', 'name', 'business_name', 'city'])
                ->each(fn ($s) => $s->facet_count = (int) $sellerCounts[$s->id])
                ->sortBy(fn ($s) => $s->business_name ?: $s->name)->values();
        }

        $prices = $this->filtered('price')->toBase()->selectRaw('min(price) as min, max(price) as max')->first();

        return [
            'categories' => $categories,
            'brands' => $brands,
            'sellers' => $sellers,
            'price_min' => (int) floor((float) ($prices->min ?? 0)),
            'price_max' => (int) ceil((float) ($prices->max ?? 0)),
            'deals_count' => $this->filtered('deals')->onSale()->count(),
            'in_stock_count' => $this->filtered('in_stock')->inStock()->count(),
            'category_locked' => isset($this->locked['category']),
            'deals_locked' => ! empty($this->locked['deals']),
        ];
    }

    /**
     * Chips for the filters the shopper picked, each with the URL that removes it.
     */
    protected function activeFilters(): Collection
    {
        $r = $this->request;
        $chips = collect();

        if (! isset($this->locked['category']) && ($category = $this->selectedCategory())) {
            $chips->push(['label' => $category->localized_name, 'url' => $this->urlWithout('category')]);
        }
        foreach (isset($this->locked['brand']) ? [] : $this->strings('brand') as $brand) {
            $chips->push(['label' => $brand, 'url' => $this->urlWithout('brand', $brand)]);
        }
        if (! isset($this->locked['seller'])) {
            foreach (User::whereIn('id', $this->ids('seller'))->get() as $seller) {
                $chips->push(['label' => $seller->business_name ?: $seller->name, 'url' => $this->urlWithout('seller', (string) $seller->id)]);
            }
        }
        if (is_numeric($r->query('min_price')) || is_numeric($r->query('max_price'))) {
            $chips->push([
                'label' => \App\Support\Money::format((float) ($r->query('min_price') ?: 0)).' – '.(is_numeric($r->query('max_price')) ? \App\Support\Money::format((float) $r->query('max_price')) : '∞'),
                'url' => $this->urlWithout(['min_price', 'max_price']),
            ]);
        }
        if (empty($this->locked['deals']) && $r->boolean('deals')) {
            $chips->push(['label' => __('On sale'), 'url' => $this->urlWithout('deals')]);
        }
        if ($r->boolean('in_stock')) {
            $chips->push(['label' => __('In stock'), 'url' => $this->urlWithout('in_stock')]);
        }
        if (in_array((int) $r->query('rating'), [3, 4], true)) {
            $chips->push(['label' => __(':stars stars & up', ['stars' => (int) $r->query('rating')]), 'url' => $this->urlWithout('rating')]);
        }

        return $chips;
    }

    public function selectedCategory(): ?Category
    {
        if (isset($this->locked['category'])) {
            return $this->locked['category'];
        }

        $slug = $this->request->query('category');

        return is_string($slug) && $slug !== '' ? Category::active()->where('slug', $slug)->first() : null;
    }

    protected function categoryIds(): array
    {
        $category = $this->selectedCategory();

        return $category ? $category->children()->active()->pluck('id')->push($category->id)->all() : [];
    }

    protected function dealsOnly(): bool
    {
        return ! empty($this->locked['deals']) || $this->request->boolean('deals');
    }

    protected function ids(string $key): array
    {
        return collect((array) $this->request->query($key, []))->filter(fn ($v) => ctype_digit((string) $v))->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    protected function strings(string $key): array
    {
        return collect((array) $this->request->query($key, []))->filter(fn ($v) => is_string($v) && $v !== '')->unique()->values()->all();
    }

    /**
     * The current URL with one filter (or one value of a multi-value filter) removed.
     */
    protected function urlWithout(string|array $keys, ?string $value = null): string
    {
        $query = $this->request->query();
        unset($query['page']);

        foreach ((array) $keys as $key) {
            if ($value !== null && is_array($query[$key] ?? null)) {
                $query[$key] = array_values(array_filter($query[$key], fn ($v) => (string) $v !== $value));
                if ($query[$key] === []) {
                    unset($query[$key]);
                }
            } else {
                unset($query[$key]);
            }
        }

        return $this->request->url().($query ? '?'.http_build_query($query) : '');
    }
}
