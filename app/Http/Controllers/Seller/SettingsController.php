<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerBankAccount;
use App\Services\OnboardingService;
use App\Support\CurrentShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Seller Centre → Settings: the shop's bank account and notification preferences.
 */
class SettingsController extends Controller
{
    public function bank()
    {
        $user = CurrentShop::get();

        $user->load('bankAccount');

        return view('seller.settings.bank', ['user' => $user, 'account' => $user->bankAccount]);
    }

    public function updateBank(Request $request)
    {
        $user = CurrentShop::get();
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

        app(OnboardingService::class)->refresh($user->fresh());

        return redirect()->route('seller.settings.bank')->with('success', __('Bank details saved. Payouts go to this account from now on.'));
    }

    public function notifications()
    {
        $user = CurrentShop::get()->fresh();

        return view('seller.settings.notifications', ['user' => $user, 'preferences' => $user->notificationPreferences(), 'types' => $this->editableTypes()]);
    }

    public function updateNotifications(Request $request)
    {
        $user = CurrentShop::get();
        // Everything else saved on the account stays as it is (the owner's own customer emails; the payout email when staff save)
        $preferences = is_array($user->notification_preferences) ? $user->notification_preferences : [];
        foreach (array_keys($this->editableTypes()) as $type) {
            $preferences[$type] = $request->boolean($type);
        }

        $user->forceFill(['notification_preferences' => $preferences])->save();

        return redirect()->route('seller.settings.notifications')->with('success', __('Notification settings saved.'));
    }

    /**
     * @return array<string, array{label: string, hint: string}>
     */
    public static function notificationTypes(): array
    {
        return [
            'new_order' => ['label' => __('New order'), 'hint' => __('When a customer orders something from your shop, with the items to pack.')],
            'return' => ['label' => __('Return requested'), 'hint' => __('When a customer asks to return items from one of your orders.')],
            'payout' => ['label' => __('Payout sent'), 'hint' => __('When iruali transfers your earnings to your bank account.')],
            'low_stock' => ['label' => __('Low stock (daily)'), 'hint' => __('One email each morning listing products at or below their low-stock level. Not sent when nothing is low.')],
            'quotes' => ['label' => __('Bulk quote requests'), 'hint' => __('When a business asks you for a quote, accepts or declines one, or writes in a request\'s messages.')],
        ];
    }

    /**
     * The shop emails the signed-in person may switch on or off: shop staff leave the payout email to the owner.
     *
     * @return array<string, array{label: string, hint: string}>
     */
    protected function editableTypes(): array
    {
        $types = self::notificationTypes();

        return CurrentShop::can('seller.earnings') ? $types : array_diff_key($types, ['payout' => true]);
    }

    protected function masked(array $fields): array
    {
        if (! empty($fields['account_number'])) {
            $fields['account_number'] = SellerBankAccount::mask((string) $fields['account_number']);
        }

        return $fields;
    }
}
