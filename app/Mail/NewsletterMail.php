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
            'paragraphs' => self::paragraphs($this->issue->introFor($locale)),
            'sections' => $this->sections,
            'unsubscribeUrl' => $this->unsubscribeUrl(),
        ]);
    }

    /**
     * The admin's text as paragraphs: a blank line (spaces allowed) starts a new one.
     *
     * @return list<string>
     */
    public static function paragraphs(string $text): array
    {
        $parts = preg_split('/\R[ \t]*\R/u', trim($text)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn (string $paragraph) => $paragraph !== ''));
    }

    /**
     * The signed one-click opt-out link, made in the reader's language (the email is rendered in it).
     */
    public function unsubscribeUrl(): string
    {
        return $this->unsubscribe ? URL::signedRoute($this->unsubscribe['route'], $this->unsubscribe['params']) : '#';
    }
}
