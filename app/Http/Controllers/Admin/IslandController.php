<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Island;
use App\Models\User;
use App\Services\IslandDirectory;
use App\Services\ShopCoverage;
use App\Support\Audit;
use App\Support\IslandList;
use Illuminate\Http\Request;

/**
 * Admin → Islands: every island in the list with its on/off switch, the shops based there and how
 * many shops deliver there. Admins add an island the list is missing (a new or reclaimed island)
 * and correct one (names in English and Dhivehi, atoll, map position for "Use my location"). A switched-off island leaves the pickers (customers, shops,
 * addresses); addresses and orders already on it are untouched. Also a shop's home island and
 * delivery area, from Admin → Sellers → the shop's page.
 */
class IslandController extends Controller
{
    public function index(Request $request, ShopCoverage $coverage)
    {
        $q = trim((string) $request->query('q', ''));
        $atoll = trim((string) $request->query('atoll', ''));
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : '';

        $islands = Island::query()
            ->withCount(['homeShops as shops_count' => fn ($s) => $s->where('is_seller', true)->where('seller_approved', true)])
            ->when($atoll !== '', fn ($query) => $query->where('atoll', $atoll))
            ->when($status !== '', fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('atoll')->orderBy('id')
            ->get()
            ->when($q !== '', fn ($list) => $list->filter(fn (Island $island) => str_contains(mb_strtolower($island->englishName().' '.$island->getTranslation('name', 'dv', false).' '.$island->atoll), mb_strtolower($q))))
            ->values();

        // Shops that deliver everywhere count for every island; the others are checked one by one
        $shops = User::query()->where('is_seller', true)->where('seller_approved', true)->with(['homeIsland', 'deliveryIslands:islands.id'])->get();
        [$everywhere, $restricted] = $shops->partition(fn (User $shop) => $shop->deliveryCoverage() === User::COVERAGE_ALL || ! $shop->home_island_id);
        foreach ($islands as $island) {
            $island->setAttribute('delivering_count', $everywhere->count() + $restricted->filter(fn (User $shop) => $coverage->canDeliverTo($shop, $island))->count());
        }

        return view('admin.islands.index', [
            'islands' => $islands,
            'atolls' => Island::query()->whereNotNull('atoll')->distinct()->orderBy('atoll')->pluck('atoll'),
            'q' => $q,
            'atoll' => $atoll,
            'status' => $status,
            'totals' => ['all' => Island::count(), 'active' => Island::where('is_active', true)->count()],
        ]);
    }

    public function store(Request $request, IslandDirectory $directory)
    {
        $data = $this->validated($request);
        $code = IslandList::code($data['atoll'], $data['name_en']);
        if (Island::where('code', $code)->exists() || $this->sameName($data['name_en'], $data['atoll'])) {
            return back()->withInput()->withErrors(['name_en' => 'That island is already in the list for '.$data['atoll'].'.']);
        }

        $island = new Island;
        $this->fill($island, $data);
        $island->forceFill(['code' => $code, 'is_active' => true])->save();
        $directory->forget();
        Audit::record('island.created', $island, ['island' => $island->englishName(), 'atoll' => $island->atoll]);

        return redirect()->route('admin.islands', ['q' => $island->englishName()])->with('success', $island->englishName().' was added and switched on.');
    }

    public function edit(Island $island)
    {
        return view('admin.islands.edit', ['island' => $island, 'atolls' => $this->atolls()]);
    }

    public function update(Request $request, Island $island, IslandDirectory $directory)
    {
        $data = $this->validated($request);
        if ($this->sameName($data['name_en'], $data['atoll'], $island->id)) {
            return back()->withInput()->withErrors(['name_en' => 'Another island in '.$data['atoll'].' already has that name.']);
        }

        $before = ['name' => $island->englishName(), 'name_dv' => $island->getTranslation('name', 'dv', false), 'atoll' => $island->atoll, 'latitude' => $island->latitude, 'longitude' => $island->longitude];
        $this->fill($island, $data);
        $island->save();
        $directory->forget();
        $after = ['name' => $island->englishName(), 'name_dv' => $island->getTranslation('name', 'dv', false), 'atoll' => $island->atoll, 'latitude' => $island->latitude, 'longitude' => $island->longitude];
        $changes = [];
        foreach ($after as $key => $value) {
            if ($before[$key] != $value) {
                $changes[$key] = ['from' => $before[$key], 'to' => $value];
            }
        }
        if ($changes) {
            Audit::record('island.updated', $island, $changes);
        }

        return redirect()->route('admin.islands', ['q' => $island->englishName()])->with('success', $island->englishName().' was saved.');
    }

    /**
     * @return array{name_en: string, name_dv: ?string, atoll: string, latitude: ?float, longitude: ?float}
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name_en' => 'required|string|max:100',
            'name_dv' => 'nullable|string|max:100',
            'atoll' => ['required', 'string', \Illuminate\Validation\Rule::in($this->atolls()->all())],
            // The Maldives lie between about 0.7°S and 7.1°N, 72.6°E and 73.8°E
            'latitude' => 'nullable|numeric|between:-1.5,8|required_with:longitude',
            'longitude' => 'nullable|numeric|between:72,74.5|required_with:latitude',
        ], [
            'latitude.between' => 'The latitude must be inside the Maldives (between -1.5 and 8).',
            'longitude.between' => 'The longitude must be inside the Maldives (between 72 and 74.5).',
        ]);

        return [
            'name_en' => trim($data['name_en']),
            'name_dv' => filled($data['name_dv'] ?? null) ? trim((string) $data['name_dv']) : null,
            'atoll' => $data['atoll'],
            'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
        ];
    }

    /**
     * @param  array{name_en: string, name_dv: ?string, atoll: string, latitude: ?float, longitude: ?float}  $data
     */
    protected function fill(Island $island, array $data): void
    {
        $island->setTranslation('name', 'en', $data['name_en']);
        if ($data['name_dv'] !== null) {
            $island->setTranslation('name', 'dv', $data['name_dv']);
        } else {
            $island->forgetTranslation('name', 'dv');
        }
        // The atoll's Dhivehi name comes from the other islands in it
        $atollDv = Island::where('atoll', $data['atoll'])->whereNotNull('atoll_dv')->value('atoll_dv');
        $island->forceFill(['atoll' => $data['atoll'], 'atoll_dv' => $atollDv, 'latitude' => $data['latitude'], 'longitude' => $data['longitude']]);
    }

    protected function sameName(string $name, string $atoll, ?int $except = null): bool
    {
        $normal = IslandList::normalize($name);

        return Island::where('atoll', $atoll)->when($except, fn ($q) => $q->whereKeyNot($except))->get()
            ->contains(fn (Island $other) => IslandList::normalize($other->englishName()) === $normal);
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function atolls(): \Illuminate\Support\Collection
    {
        return Island::query()->whereNotNull('atoll')->distinct()->orderBy('atoll')->pluck('atoll');
    }

    public function toggle(Request $request, Island $island, IslandDirectory $directory)
    {
        $on = $request->boolean('active');
        if ($on !== (bool) $island->is_active) {
            $island->forceFill(['is_active' => $on])->save();
            $directory->forget();
            Audit::record($on ? 'island.activated' : 'island.deactivated', $island, ['island' => $island->englishName(), 'atoll' => $island->atoll]);
        }

        return back()->with('success', $on ? __(':island is switched on.', ['island' => $island->englishName()]) : __(':island is switched off. It no longer shows in island lists.', ['island' => $island->englishName()]));
    }

    public function updateShop(Request $request, User $seller, ShopCoverage $coverage)
    {
        abort_unless($seller->is_seller, 404);

        $data = $request->validate(ShopCoverage::rules(false), ShopCoverage::messages());
        $before = ['home_island_id' => $seller->home_island_id, 'delivery_coverage' => $seller->deliveryCoverage(), 'delivers_to_home_atoll' => (bool) $seller->delivers_to_home_atoll, 'extra_islands' => $seller->deliveryIslands()->pluck('islands.id')->all()];
        $coverage->save($seller, $data);
        $after = ['home_island_id' => $seller->home_island_id, 'delivery_coverage' => $seller->deliveryCoverage(), 'delivers_to_home_atoll' => (bool) $seller->delivers_to_home_atoll, 'extra_islands' => $seller->deliveryIslands()->pluck('islands.id')->all()];

        $changes = [];
        foreach ($after as $key => $value) {
            if ($before[$key] != $value) {
                $changes[$key] = ['from' => $before[$key], 'to' => $value];
            }
        }
        if ($changes) {
            Audit::record('seller.coverage', $seller, $changes + ['business_name' => $seller->business_name]);
        }

        return back()->with('success', __('Delivery area saved.'));
    }
}
