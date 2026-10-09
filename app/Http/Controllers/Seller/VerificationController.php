<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerVerification;
use App\Services\SellerVerificationService;
use App\Support\CurrentShop;
use Illuminate\Http\Request;

/**
 * Seller Centre → Settings → Business verification: send or replace the business registration
 * certificate and the owner's ID card, see where the check is, and view what was sent.
 */
class VerificationController extends Controller
{
    public function __construct(protected SellerVerificationService $verifications) {}

    public function show(Request $request)
    {
        $user = CurrentShop::get();

        return view('seller.settings.verification', ['user' => $user, 'verification' => $user->businessVerification]);
    }

    public function update(Request $request)
    {
        $user = CurrentShop::get();
        $data = $request->validate(
            $this->verifications->rules($user->businessVerification),
            $this->verifications->messages(),
            $this->verifications->attributes(),
        );

        $saved = $this->verifications->submit($user, $data);

        return redirect()->route('seller.settings.verification')->with('success', $saved
            ? __('Thank you. iruali will check your documents, usually within two working days, and email you the result.')
            : __('Nothing changed: your documents are as before.'));
    }

    /**
     * One of the shop's own documents. There is no id in the address: a shop can only ever open its own.
     */
    public function document(Request $request, string $document)
    {
        abort_unless(array_key_exists($document, SellerVerification::DOCUMENTS), 404);
        $verification = CurrentShop::get()->businessVerification;
        abort_unless($verification !== null, 404);

        return $this->verifications->documentResponse($verification, $document);
    }
}
