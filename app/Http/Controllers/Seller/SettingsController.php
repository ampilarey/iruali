<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerBankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Seller Centre → Settings: the shop's bank account and notification preferences.
 */
class SettingsController extends Controller
{
    public function bank()
    {
        $user = Auth::user();

        $user->load('bankAccount');

        return view('seller.settings.bank', ['user' => $user, 'account' => $user->bankAccount]);
    }

    public function updateBank(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate(SellerBankAccount::rules((string) $request->input('bank')));
        $data['account_number'] = preg_replace('/\s+/', '', $data['account_number']);
        if ($data['bank'] !== 'other') {
            $data['bank_name_other'] = null;
        }

        $account = $user->bankAccount()->first() ?: new SellerBankAccount(['user_id' => $user->id]);
        $fields = ['bank', 'bank_name_other', 'account_name', 'account_number'];
        $before = $account->only($fields);
        $account->fill($data + ['currency' => 'MVR']);
        $changed = $account->isDirty($fields);
        if ($changed) {
            // A changed account has to be checked again before the next payout.
            $account->verified_at = null;
        }
        $account->save();

        if ($changed) {
            // Audit trail: who changed which fields. Numbers are masked so the log holds no full account numbers.
            Log::info('Seller bank account changed', [
                'user_id' => $user->id,
                'by' => $request->user()->id,
                'ip' => $request->ip(),
                'before' => $this->masked($before),
                'after' => $this->masked($account->only($fields)),
            ]);
        }

        return redirect()->route('seller.settings.bank')->with('success', __('Bank details saved. Payouts go to this account from now on.'));
    }

    protected function masked(array $fields): array
    {
        if (! empty($fields['account_number'])) {
            $fields['account_number'] = SellerBankAccount::mask((string) $fields['account_number']);
        }

        return $fields;
    }
}
