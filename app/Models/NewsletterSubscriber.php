<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class NewsletterSubscriber extends Model
{
    protected $fillable = ['email', 'locale'];

    /**
     * One-click opt-out link for the footer of every newsletter (signed, so it cannot be guessed).
     */
    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $this->id]);
    }
}
