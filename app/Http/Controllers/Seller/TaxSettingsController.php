<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\ShopTaxProfile;
use App\Rules\MiraTin;
use App\Services\GstService;
use App\Support\Audit;
use App\Support\CurrentShop;
use Illuminate\Http\Request;

/**
 * Seller Centre → Settings → Tax: whether the shop is GST-registered, its TIN, registered name and
 * business address. Orders keep the details they were placed with.
 */
class TaxSettingsController extends Controller
{
    public function edit(Request $request)
    {
        $user = CurrentShop::get();

        return view('seller.settings.tax', ['user' => $user, 'profile' => ShopTaxProfile::forUser($user->id), 'rate' => app(GstService::class)->rate()]);
    }

    public function update(Request $request)
    {
        $user = CurrentShop::get();
        $request->merge(['tin' => GstService::normaliseTin($request->input('tin')) ?: null]);

        $data = $request->validate([
            'gst_registered' => 'required|boolean',
            'tin' => ['nullable', 'required_if:gst_registered,1', 'string', 'max:30', new MiraTin],
            'registered_name' => 'nullable|required_if:gst_registered,1|string|max:150',
            'business_address' => 'nullable|required_if:gst_registered,1|string|max:300',
        ], [], [
            'gst_registered' => __('GST registered'),
            'tin' => __('TIN'),
            'registered_name' => __('registered business name'),
            'business_address' => __('business address'),
        ]);

        $profile = ShopTaxProfile::firstOrNew(['user_id' => $user->id]);
        $before = $profile->exists ? $profile->only(['gst_registered', 'tin', 'registered_name', 'business_address']) : [];
        $profile->fill([
            'gst_registered' => (bool) $data['gst_registered'],
            'tin' => $data['tin'] ?? null,
            'registered_name' => filled($data['registered_name'] ?? null) ? trim($data['registered_name']) : null,
            'business_address' => filled($data['business_address'] ?? null) ? trim($data['business_address']) : null,
        ]);

        if ($profile->isDirty()) {
            $profile->save();
            Audit::record('shop.tax_details_saved', $user, [
                'shop' => $user->shopName(),
                'before' => $before,
                'after' => $profile->only(['gst_registered', 'tin', 'registered_name', 'business_address']),
            ]);
        }

        return redirect()->route('seller.settings.tax')->with('success', __('Tax details saved. They apply to orders placed from now on.'));
    }
}
