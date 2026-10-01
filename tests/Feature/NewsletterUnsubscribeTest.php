<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_link_unsubscribes_and_shows_a_confirmation(): void
    {
        $subscriber = NewsletterSubscriber::create(['email' => 'fan@example.com', 'locale' => 'en']);
        $url = $subscriber->unsubscribeUrl();

        $this->assertStringContainsString('/newsletter/unsubscribe/'.$subscriber->id, $url);
        $this->assertStringContainsString('signature=', $url);

        $this->get($url)->assertOk()->assertSee('You are unsubscribed')->assertSee('fan@example.com');
        $this->assertSame(0, NewsletterSubscriber::count());

        // Clicking the link a second time is not an error
        $this->get($url)->assertOk()->assertSee('You are unsubscribed');
    }

    public function test_unsigned_or_tampered_links_are_refused(): void
    {
        $subscriber = NewsletterSubscriber::create(['email' => 'fan@example.com', 'locale' => 'en']);

        $this->get(route('newsletter.unsubscribe', ['subscriber' => $subscriber->id]))->assertForbidden();
        $this->get(str_replace('/'.$subscriber->id.'?', '/'.($subscriber->id + 1).'?', $subscriber->unsubscribeUrl()))->assertForbidden();
        $this->assertSame(1, NewsletterSubscriber::count());
    }

    public function test_privacy_policy_mentions_the_unsubscribe_link(): void
    {
        $this->get('/privacy-policy')->assertOk()->assertSee('use the unsubscribe link in any newsletter');
    }
}
