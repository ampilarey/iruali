<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use App\Rules\MiraTin;
use App\Services\GstService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * My account → Edit profile → Business details: the company name, TIN and address offered at
 * checkout for the invoice ("Buying for a business?").
 */
class BusinessDetailsController extends Controller
{
    public function update(Request $request)
    {
        $request->merge(['tin' => GstService::normaliseTin($request->input('tin')) ?: null]);

        $data = $request->validateWithBag('business', [
            'company_name' => 'required|string|max:150',
            'tin' => ['nullable', 'string', 'max:30', new MiraTin],
            'business_address' => 'required|string|max:300',
        ], [], [
            'company_name' => __('company name'),
            'tin' => __('TIN'),
            'business_address' => __('business address'),
        ]);

        BusinessProfile::updateOrCreate(['user_id' => $request->user()->id], [
            'company_name' => trim($data['company_name']),
            'tin' => $data['tin'] ?? null,
            'business_address' => trim($data['business_address']),
        ]);

        NotificationService::success(__('Business details saved. They are offered at checkout for your invoices.'));

        return redirect()->to(route('account.edit').'#business');
    }

    public function destroy(Request $request)
    {
        BusinessProfile::where('user_id', $request->user()->id)->delete();

        NotificationService::success(__('Business details removed.'));

        return redirect()->to(route('account.edit').'#business');
    }
}
