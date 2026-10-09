<?php

namespace App\Mail;

use App\Models\NewsletterIssue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/**
 * One newsletter email (see NewsletterService). Rendered in the reader's language (sent with
 * ->locale()), so the subject, the text, product names, prices and links are all in it. Sent
 * from a queued batch, so this mailable itself is not queued.
 */
class NewsletterMail extends Mailable
{
    /**
     * @param  array<string, mixed>  $sections  NewsletterService::sections()
     * @param  array{route: string, params: array<string, int>}|null  $unsubscribe  the signed opt-out route; null in previews
     */
    public function __construct(public NewsletterIssue $issue, public array $sections, public ?string $name, public ?array $unsubscribe, public bool $test = false) {}

    public function envelope(): Envelope
    {
        $subject = $this->issue->subjectFor($this->locale ?: app()->getLocale());

        return new Envelope(subject: $this->test ? '[Test] '.$subject : $subject);
    }

    /**
     * Mail apps show their own "Unsubscribe" button from this header.
     */
    public function headers(): Headers
    {
        return new Headers(text: $this->unsubscribe ? ['List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>'] : []);
    }

    public function content(): Content
    {
        $locale = $this->locale ?: app()->getLocale();

        return new Content(markdown: 'mail.newsletter', with: [
            'name' => $this->name,
            'paragraphs' => array_values(array_filter(preg_split('/\R{2,}/u', trim($this->issue->introFor($locale))) ?: [], fn (string $p) => trim($p) !== '')),
            'sections' => $this->sections,
            'unsubscribeUrl' => $this->unsubscribeUrl(),
        ]);
    }

    /**
     * The signed one-click opt-out link, made in the reader's language (the email is rendered in it).
     */
    public function unsubscribeUrl(): string
    {
        return $this->unsubscribe ? URL::signedRoute($this->unsubscribe['route'], $this->unsubscribe['params']) : '#';
    }
}
