<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin → Brands → edit → Authorised sellers: the shops iruali has confirmed as authorised
 * sellers of a brand. Their products of that brand show an "Authorised seller" badge and they
 * come first on the brand page. Every change goes in the audit log.
 */
class BrandSellerController extends Controller
{
    public function store(Request $request, Brand $brand): RedirectResponse
    {
        $data = $request->validate(['seller_id' => ['required', 'integer']], ['seller_id.required' => __('Choose a shop to authorise.')]);

        $seller = User::query()->whereKey($data['seller_id'])
            ->where('is_seller', true)->where('seller_approved', true)->where('status', '!=', 'suspended')
            ->first();
        if (! $seller) {
            return $this->toPanel($brand)->withErrors(['seller_id' => __('Only approved shops can be authorised sellers.')]);
        }

        $names = ['shop' => $seller->shopName(), 'brand' => $brand->name];
        if ($brand->isAuthorisedSeller($seller->id)) {
            return $this->toPanel($brand)->with('success', __(':shop is already an authorised seller of :brand.', $names));
        }

        try {
            $brand->authorisedSellers()->attach($seller->id, ['authorised_by' => $request->user()?->id]);
        } catch (UniqueConstraintViolationException) {
            // Added a moment ago (a double click)
            return $this->toPanel($brand)->with('success', __(':shop is already an authorised seller of :brand.', $names));
        }
        Audit::record('brand.seller_authorised', $brand, ['brand' => $brand->name, 'seller' => $seller->shopName(), 'seller_id' => $seller->id]);

        return $this->toPanel($brand)->with('success', __(':shop is now an authorised seller of :brand.', $names));
    }

    public function destroy(Brand $brand, User $seller): RedirectResponse
    {
        if ($brand->authorisedSellers()->detach($seller->id) > 0) {
            Audit::record('brand.seller_unauthorised', $brand, ['brand' => $brand->name, 'seller' => $seller->shopName(), 'seller_id' => $seller->id]);
        }

        return $this->toPanel($brand)->with('success', __(':shop is no longer an authorised seller of :brand.', ['shop' => $seller->shopName(), 'brand' => $brand->name]));
    }

    /** Back to the brand's edit page, scrolled to the panel. */
    protected function toPanel(Brand $brand): RedirectResponse
    {
        return redirect()->to(route('admin.brands.edit', $brand).'#authorised-sellers');
    }
}
