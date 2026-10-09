<?php

namespace App\Models;

use App\Notifications\ConfirmNewsletterSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Throwable;

class NewsletterSubscriber extends Model
{
    protected $fillable = ['email', 'locale'];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'confirmation_sent_at' => 'datetime',
    ];

    /** A confirmation email goes to an address at most this often (the footer form can be sent again). */
    public const CONFIRMATION_RESEND_MINUTES = 10;

    /** How long the link in the confirmation email works. */
    public const CONFIRMATION_DAYS = 7;

    /**
     * One-click opt-out link for the footer of every newsletter (signed, so it cannot be guessed).
     */
    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $this->id]);
    }

    /** Addresses that clicked the link in the confirmation email: only these get the newsletter. */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereNotNull('confirmed_at');
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    /** The link in the confirmation email (signed, valid for CONFIRMATION_DAYS). */
    public function confirmUrl(): string
    {
        return URL::temporarySignedRoute('newsletter.confirm', now()->addDays(self::CONFIRMATION_DAYS), ['subscriber' => $this->id]);
    }

    /**
     * Email the confirmation link, in the subscriber's language, unless the address is confirmed
     * already or was sent one in the last few minutes. Returns whether one went out.
     */
    public function sendConfirmation(): bool
    {
        if ($this->isConfirmed() || ($this->confirmation_sent_at && $this->confirmation_sent_at->gt(now()->subMinutes(self::CONFIRMATION_RESEND_MINUTES)))) {
            return false;
        }

        $this->forceFill(['confirmation_sent_at' => now()])->save();

        try {
            Notification::route('mail', $this->email)->notify((new ConfirmNewsletterSubscription($this))->locale($this->locale === 'dv' ? 'dv' : 'en'));
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }
}
