<?php

namespace App\Services;

use App\Models\Island;
use App\Models\Product;
use App\Models\User;
use App\Support\IslandList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\Rule;

/**
 * Where each shop delivers. A shop has a home island and one of three coverage types:
 *  - all islands (the usual Malé shop, sending by boat, ferry or cargo): reaches everyone;
 *  - home island and Greater Malé (an island shop sending produce to Malé);
 *  - home island only.
 * The last two can add extra islands they reach and/or their whole atoll. Pickup from the shop
 * stays open whatever the coverage. canDeliverTo() is the one rule; listings, labels and checkout
 * all go through it.
 */
class ShopCoverage
{
    /**
     * Can this shop deliver to the island? A shop without a home island counts as delivering
     * everywhere (today's behaviour) until it sets one.
     */
    public function canDeliverTo(User $shop, Island $island): bool
    {
        $coverage = $shop->deliveryCoverage();
        if ($coverage === User::COVERAGE_ALL || ! $shop->home_island_id) {
            return true;
        }
        if ((int) $shop->home_island_id === (int) $island->id) {
            return true;
        }
        if ($coverage === User::COVERAGE_HOME_AND_MALE && $island->isGreaterMale()) {
            return true;
        }
        $home = $shop->homeIsland;
        if ($shop->delivers_to_home_atoll && $home && IslandList::normalizeAtoll($home->atoll) !== '' && IslandList::normalizeAtoll($home->atoll) === IslandList::normalizeAtoll($island->atoll)) {
            return true;
        }

        return $shop->deliveryIslands->contains('id', $island->id);
    }

    /**
     * Shops that cannot deliver to the island. Only shops that do not deliver everywhere are
     * checked, and the answer is kept for the rest of the request (one per island, so a page
     * that changes island never sees another island's answer).
     *
     * @return list<int>
     */
    public function blockedSellerIds(Island $island): array
    {
        $key = 'shop_coverage.blocked.'.$island->id;
        $attributes = request()->attributes;
        if (! $attributes->has($key)) {
            $attributes->set($key, User::query()
                ->where('delivery_coverage', '!=', User::COVERAGE_ALL)->whereNotNull('home_island_id')
                ->with(['homeIsland', 'deliveryIslands:islands.id'])
                ->get()
                ->reject(fn (User $shop) => $this->canDeliverTo($shop, $island))
                ->map(fn (User $shop) => (int) $shop->id)->values()->all());
        }

        return $attributes->get($key);
    }

    /** Forget the answers kept for this request (after a shop's coverage changes). */
    public function forget(): void
    {
        foreach (array_keys(request()->attributes->all()) as $key) {
            if (str_starts_with((string) $key, 'shop_coverage.')) {
                request()->attributes->remove($key);
            }
        }
    }

    /** Can this product be delivered to the island? Products of no shop are iruali's own. */
    public function deliversProduct(Product $product, Island $island): bool
    {
        return ! $product->seller_id || ! in_array((int) $product->seller_id, $this->blockedSellerIds($island), true);
    }

    /**
     * Narrow a products query to what the island's shopper sees:
     *  - "delivers": products whose shop delivers to the island (the default);
     *  - "local": products of shops based on the island;
     *  - "all" or no island: everything.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, ?Island $island, string $view = ShoppingIsland::VIEW_DELIVERS): Builder
    {
        if (! $island || $view === ShoppingIsland::VIEW_ALL) {
            return $query;
        }

        if ($view === ShoppingIsland::VIEW_LOCAL) {
            return $query->whereIn('products.seller_id', User::query()->select('id')->where('home_island_id', $island->id));
        }

        $blocked = $this->blockedSellerIds($island);

        return $blocked === [] ? $query : $query->where(fn (Builder $q) => $q->whereNull('products.seller_id')->orWhereNotIn('products.seller_id', $blocked));
    }

    /**
     * Approved shops based on the island with something on sale, most products first.
     *
     * @return EloquentCollection<int, User>
     */
    public function localShops(Island $island, int $limit = 8): EloquentCollection
    {
        return User::query()
            ->where('is_seller', true)->where('seller_approved', true)->where('home_island_id', $island->id)
            ->withCount(['products' => fn ($q) => $q->active()])
            ->whereHas('products', fn ($q) => $q->active())
            ->orderByDesc('products_count')
            ->take($limit)
            ->get();
    }

    public function hasLocalShops(Island $island): bool
    {
        return User::query()->where('is_seller', true)->where('seller_approved', true)->where('home_island_id', $island->id)
            ->whereHas('products', fn ($q) => $q->active())->exists();
    }

    /**
     * The three coverage types in plain words, for the seller profile, application and admin.
     *
     * @return array<string, array{label: string, hint: string}>
     */
    public static function options(): array
    {
        return [
            User::COVERAGE_ALL => ['label' => __('All islands'), 'hint' => __('You send orders anywhere in the Maldives by boat, ferry or cargo. Most Malé shops choose this.')],
            User::COVERAGE_HOME_AND_MALE => ['label' => __('My island and Greater Malé'), 'hint' => __('You sell on your own island and send orders to Malé, Hulhumalé and Villimalé.')],
            User::COVERAGE_HOME_ONLY => ['label' => __('My island only'), 'hint' => __('You only deliver on your own island.')],
        ];
    }

    /**
     * Where the shop delivers, in words: "All islands", or "Hithadhoo, Greater Malé, Seenu Atoll, Feydhoo".
     */
    public function summary(User $shop): string
    {
        $coverage = $shop->deliveryCoverage();
        $home = $shop->homeIsland;
        if ($coverage === User::COVERAGE_ALL || ! $home) {
            return __('All islands');
        }

        $parts = [$home->localized_name];
        if ($coverage === User::COVERAGE_HOME_AND_MALE && ! $home->isGreaterMale()) {
            $parts[] = __('Greater Malé');
        }
        if ($shop->delivers_to_home_atoll && filled($home->atoll)) {
            $parts[] = __('the whole of :atoll Atoll', ['atoll' => $home->localizedAtoll()]);
        }
        foreach ($shop->deliveryIslands->sortBy(fn (Island $island) => $island->localized_name) as $island) {
            $parts[] = $island->localized_name;
        }

        return collect($parts)->filter()->unique()->join(', ');
    }

    /**
     * Validation rules for the home island and coverage fields.
     *
     * @return array<string, mixed>
     */
    public static function rules(bool $homeRequired): array
    {
        $activeIsland = Rule::exists(Island::class, 'id')->where('is_active', true);

        return [
            'home_island_id' => [$homeRequired ? 'required' : 'required_unless:delivery_coverage,'.User::COVERAGE_ALL, 'nullable', 'integer', $activeIsland],
            'delivery_coverage' => ['required', Rule::in(User::COVERAGES)],
            'delivers_to_home_atoll' => ['nullable', 'boolean'],
            'delivery_island_ids' => ['nullable', 'array', 'max:60'],
            'delivery_island_ids.*' => ['integer', $activeIsland],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'home_island_id.required' => __('Choose the island your shop is on.'),
            'home_island_id.required_unless' => __('Choose the island your shop is on, so customers know where you deliver.'),
        ];
    }

    /**
     * Save validated coverage fields on the shop. Extra islands and the whole atoll only apply to
     * shops that do not deliver everywhere, so they are cleared for "all islands".
     *
     * @param  array<string, mixed>  $data
     */
    public function save(User $shop, array $data): void
    {
        $coverage = in_array($data['delivery_coverage'] ?? null, User::COVERAGES, true) ? $data['delivery_coverage'] : User::COVERAGE_ALL;
        $all = $coverage === User::COVERAGE_ALL;
        $homeId = ! empty($data['home_island_id']) ? (int) $data['home_island_id'] : null;

        $shop->forceFill([
            'home_island_id' => $homeId,
            'delivery_coverage' => $coverage,
            'delivers_to_home_atoll' => ! $all && filter_var($data['delivers_to_home_atoll'] ?? false, FILTER_VALIDATE_BOOLEAN),
            // Kept in step for anything still reading the old checkbox
            'ships_to_islands' => $coverage !== User::COVERAGE_HOME_ONLY,
        ])->save();

        $extra = $all ? [] : collect((array) ($data['delivery_island_ids'] ?? []))->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $homeId)->unique()->values()->all();
        $shop->deliveryIslands()->sync($extra);
        $shop->unsetRelation('deliveryIslands')->unsetRelation('homeIsland');

        $this->forget();
    }

    /** A shop that has not said where it is yet (asked on its dashboard and checklist). */
    public function needsHomeIsland(User $shop): bool
    {
        return $shop->is_seller && ! $shop->home_island_id;
    }
}
