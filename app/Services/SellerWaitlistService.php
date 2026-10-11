<?php

namespace App\Services;

use App\Models\SellerWaitlist;
use App\Models\User;
use App\Notifications\SellerApplicationsReopened;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * "Tell me when you reopen": the people waiting for new shop applications to open again, and the
 * one email each of them gets when they do (App\Support\SellerApplications).
 */
class SellerWaitlistService
{
    /**
     * Put an address on the list, in the visitor's language. Someone who was told before and is
     * waiting again (applications closed since) is told again next time.
     */
    public function join(string $email, ?User $user = null): SellerWaitlist
    {
        $entry = SellerWaitlist::firstOrNew(['email' => mb_strtolower(trim($email))]);
        $entry->fill([
            'user_id' => $user->id ?? $entry->user_id,
            'locale' => app()->getLocale() === 'dv' ? 'dv' : 'en',
        ]);
        $entry->notified_at = null;
        $entry->save();

        return $entry;
    }

    /**
     * Applications are open: email everyone still waiting, once each. Returns how many were told.
     */
    public function notifyAll(): int
    {
        $told = 0;
        SellerWaitlist::waiting()->chunkById(200, function ($entries) use (&$told) {
            foreach ($entries as $entry) {
                try {
                    Notification::route('mail', $entry->email)
                        ->notify((new SellerApplicationsReopened)->locale($entry->locale === 'dv' ? 'dv' : 'en'));
                    $entry->forceFill(['notified_at' => now()])->save();
                    $told++;
                } catch (Throwable $e) {
                    report($e); // stays on the list; the next run tries again
                }
            }
        });

        return $told;
    }
}
