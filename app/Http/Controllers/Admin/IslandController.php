<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Island;
use App\Models\User;
use App\Services\IslandDirectory;
use App\Services\ShopCoverage;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Admin → Islands: every island in the list with its on/off switch, the shops based there and how
 * many shops deliver there. A switched-off island leaves the pickers (customers, shops,
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
