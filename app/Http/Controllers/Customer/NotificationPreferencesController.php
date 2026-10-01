<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Sms\SmsManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * My Account → Notifications: email, SMS or both for each kind of message.
 */
class NotificationPreferencesController extends Controller
{
    public function edit(Request $request, SmsManager $sms)
    {
        $user = $request->user();

        return view('account.notifications', [
            'user' => $user,
            'types' => $this->types(),
            'current' => collect(User::NOTIFICATION_TYPES)->mapWithKeys(fn ($t) => [$t => $user->notificationPreference($t)]),
            'smsAvailable' => $user->isPhoneVerified(),
            'smsLive' => $sms->isLive(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $allowed = $user->isPhoneVerified() ? ['email', 'sms', 'both'] : ['email'];

        $data = $request->validate(
            collect(User::NOTIFICATION_TYPES)->mapWithKeys(fn ($t) => [$t => ['required', Rule::in($allowed)]])->all(),
            ['*.in' => __('Verify your phone number before choosing SMS.')]
        );

        $preferences = (array) $user->notification_preferences;
        $preferences['customer'] = $data;
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
        ];
    }
}
