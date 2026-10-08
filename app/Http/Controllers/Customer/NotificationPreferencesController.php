<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Sms\SmsManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * My Account → Notifications: email, SMS or both for each kind of message, and email or off for
 * the optional ones (User::OPTIONAL_EMAIL_TYPES, e.g. the daily email about brands you follow).
 */
class NotificationPreferencesController extends Controller
{
    public function edit(Request $request, SmsManager $sms)
    {
        $user = $request->user();

        return view('account.notifications', [
            'user' => $user,
            'types' => $this->types(),
            'choices' => $this->choices(),
            'current' => collect(User::NOTIFICATION_TYPES)->mapWithKeys(fn ($t) => [$t => $user->notificationPreference($t)])
                ->merge(collect(User::OPTIONAL_EMAIL_TYPES)->mapWithKeys(fn ($t) => [$t => $user->emailPreference($t)])),
            'smsAvailable' => $user->isPhoneVerified(),
            'smsLive' => $sms->isLive(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $allowed = $user->isPhoneVerified() ? ['email', 'sms', 'both'] : ['email'];

        $data = $request->validate(
            collect(User::NOTIFICATION_TYPES)->mapWithKeys(fn ($t) => [$t => ['required', Rule::in($allowed)]])
                // Email or off; a form that does not send one leaves it as it was
                ->merge(collect(User::OPTIONAL_EMAIL_TYPES)->mapWithKeys(fn ($t) => [$t => ['sometimes', Rule::in(['email', 'off'])]]))
                ->all(),
            ['*.in' => __('Verify your phone number before choosing SMS.')]
        );

        $preferences = (array) $user->notification_preferences;
        $preferences['customer'] = array_merge((array) ($preferences['customer'] ?? []), $data);
        $user->forceFill(['notification_preferences' => $preferences])->save();

        NotificationService::success(__('Your notification settings have been saved.'));

        return redirect()->route('account.notifications');
    }

    /**
     * @return array<string, array{0: string, 1: string}> type => [label, description]
     */
    protected function types(): array
    {
        return [
            'order_updates' => [__('Order updates'), __('Order received, payment confirmed, cancellations and refunds.')],
            'delivery_updates' => [__('Delivery updates'), __('When your order is on its way, out for delivery and delivered.')],
            'marketing' => [__('Offers and news'), __('Deals, new arrivals and the occasional newsletter.')],
            'security' => [__('Security'), __('Sign-in codes, password changes and other account alerts.')],
            'brand_updates' => [__('Brands you follow'), __('One email a day when brands you follow put products on sale or join a sale.')],
        ];
    }

    /**
     * The choices offered for each kind of message.
     *
     * @return array<string, array<string, string>> type => [value => label]
     */
    protected function choices(): array
    {
        $routed = ['email' => __('Email'), 'sms' => __('SMS'), 'both' => __('Both')];

        return collect(User::NOTIFICATION_TYPES)->mapWithKeys(fn ($t) => [$t => $routed])
            ->merge(collect(User::OPTIONAL_EMAIL_TYPES)->mapWithKeys(fn ($t) => [$t => ['email' => __('Email'), 'off' => __('Off')]]))
            ->all();
    }
}
