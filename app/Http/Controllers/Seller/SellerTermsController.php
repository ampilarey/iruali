<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Support\CurrentShop;
use App\Support\SellerTerms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Agree to the new terms": where App\Http\Middleware\ShopAccess sends a shop that has not agreed to
 * the current Seller Terms. The owner reads what changed and agrees; a staff member sees the same page
 * and waits for the owner.
 */
class SellerTermsController extends Controller
{
    public function show(): View
    {
        $shop = CurrentShop::get();

        return view('seller.terms', [
            'shop' => $shop,
            'agreed' => SellerTerms::agreed($shop),
            'isOwner' => ! CurrentShop::isStaff(),
            'version' => SellerTerms::versionLabel(),
            'changes' => SellerTerms::changes(),
            'previous' => $shop->seller_terms_version ? SellerTerms::versionLabel($shop->seller_terms_version) : null,
        ]);
    }

    public function agree(Request $request): RedirectResponse
    {
        abort_if(CurrentShop::isStaff(), 403, __('Only the shop owner can agree to the Seller Terms.'));
        $request->validate(['agree' => 'accepted'], ['agree.accepted' => __('Tick the box to agree to the Seller Terms.')]);

        $shop = CurrentShop::get();
        if (! SellerTerms::agreed($shop)) {
            SellerTerms::recordAgreement($shop, $request->user(), $request);
        }

        $intended = (string) $request->session()->pull('seller_terms_intended', '');

        return redirect()->to($intended !== '' && str_starts_with($intended, url('/')) ? $intended : route('seller.dashboard'))
            ->with('success', __('Thank you. You agreed to the Seller Terms of :date.', ['date' => SellerTerms::versionLabel()]));
    }
}
