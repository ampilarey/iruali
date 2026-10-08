<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandFollow;
use App\Services\BrandService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * Following brands: the Follow / Following button on a brand page, and My Account → Brands you
 * follow. Followers get one email a day about what those brands put on sale
 * (brands:notify-followers). Guests pressing Follow sign in first and come back to the brand page.
 */
class BrandFollowController extends Controller
{
    public function __construct(protected BrandService $brands) {}

    public function index(Request $request)
    {
        $user = $request->user();

        return view('account.brands', [
            'user' => $user,
            'brands' => $user->followedBrands()->withActiveProductCount()->orderBy('brands.name')->get(),
        ]);
    }

    public function store(Request $request, string $brand)
    {
        $model = $this->find($brand);
        BrandFollow::firstOrCreate(['user_id' => $request->user()->id, 'brand_id' => $model->id]);

        NotificationService::success(__('You follow :brand now. We will email you when it puts products on sale.', ['brand' => $model->localizedName()]));

        return redirect()->back(fallback: route('brands.show', $model));
    }

    public function destroy(Request $request, string $brand)
    {
        $model = $this->find($brand);
        BrandFollow::where('user_id', $request->user()->id)->where('brand_id', $model->id)->delete();

        NotificationService::success(__('You no longer follow :brand.', ['brand' => $model->localizedName()]));

        return redirect()->back(fallback: route('account.brands'));
    }

    /** The brand by its address, an old address kept after a rename, or its name. */
    protected function find(string $brand): Brand
    {
        $model = $this->brands->findForUrl($brand);
        abort_if($model === null, 404);

        return $model;
    }
}
