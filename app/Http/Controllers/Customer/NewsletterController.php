<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    /**
     * The footer signup. The address gets the newsletter once it is confirmed: by the link in the
     * email this sends, or straight away for a signed-in customer's own verified address.
     */
    public function store(Request $request)
    {
        $data = $request->validate(['email' => 'required|email:rfc|max:255']);
        $email = mb_strtolower($data['email']);

        $subscriber = NewsletterSubscriber::firstOrCreate(['email' => $email], ['locale' => app()->getLocale()]);

        $user = $request->user();
        if (! $subscriber->isConfirmed() && $user && mb_strtolower((string) $user->email) === $email && $user->isEmailVerified()) {
            $subscriber->forceFill(['confirmed_at' => now(), 'locale' => app()->getLocale()])->save();
        }

        if ($subscriber->isConfirmed()) {
            NotificationService::success(__('You are subscribed. Watch your inbox for deals.'));

            return back();
        }

        // Not confirmed yet: the newsletter comes in the language of the latest signup
        $subscriber->forceFill(['locale' => app()->getLocale()])->save();
        $subscriber->sendConfirmation();

        NotificationService::success(__('Almost done: we have emailed you a link to confirm your subscription.'));

        return back();
    }

    /**
     * The signed link in the confirmation email.
     */
    public function confirm(Request $request, int $subscriber)
    {
        $row = NewsletterSubscriber::find($subscriber);
        if ($row && ! $row->isConfirmed()) {
            $row->forceFill(['confirmed_at' => now()])->save();
        }

        return view('newsletter.confirmed', ['subscriber' => $row]);
    }

    /**
     * Signed one-click opt-out from a newsletter footer. A link that was already used still
     * shows the confirmation, so clicking it twice is not an error.
     */
    public function unsubscribe(Request $request, int $subscriber)
    {
        $row = NewsletterSubscriber::find($subscriber);
        $email = $row->email ?? $request->query('email', '');
        $row?->delete();

        return view('newsletter.unsubscribed', ['email' => $email]);
    }
}
