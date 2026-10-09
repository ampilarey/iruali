<?php

namespace Tests\Feature;

use App\Jobs\SendNewsletterBatch;
use App\Mail\NewsletterMail;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Category;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterIssue;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ConfirmNewsletterSubscription;
use App\Services\NewsletterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The newsletter sender (Admin → Moderation → Newsletter) and the confirmed footer signup it sends
 * to: writing, preview, test, sending in batches to every address once in its own language with
 * the signed unsubscribe link, and never twice.
 */
class NewsletterSenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-09 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function staff(string $role = 'admin'): User
    {
        $user = User::factory()->create(['name' => 'Office '.$role]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function issue(array $attributes = []): NewsletterIssue
    {
        $issue = new NewsletterIssue(array_merge([
            'subject_en' => 'Eid deals are here',
            'subject_dv' => 'ޢީދު ޑީލްތައް އައިއްސި',
            'intro_en' => "Big savings this week.\n\nFree delivery in Malé.",
            'intro_dv' => 'މި ހަފުތާގައި ބޮޑު ޑިސްކައުންޓް.',
            'sections' => ['new_arrivals_days' => null, 'deals' => false, 'campaign_id' => null, 'brands' => false],
        ], $attributes));
        $issue->save();

        return $issue;
    }

    protected function subscriber(string $email, string $locale = 'en', bool $confirmed = true): NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::create(['email' => $email, 'locale' => $locale]);
        $subscriber->forceFill(['confirmed_at' => $confirmed ? now() : null])->save();

        return $subscriber;
    }

    // ---- The footer signup ---------------------------------------------------------------------

    public function test_footer_signups_get_the_newsletter_only_after_confirming(): void
    {
        Notification::fake();

        $this->post(route('newsletter.store'), ['email' => 'Fan@Example.com'])->assertRedirect()
            ->assertSessionHas('notification', fn (array $flash) => str_contains($flash['message'], 'we have emailed you a link to confirm'));
        $subscriber = NewsletterSubscriber::sole();
        $this->assertSame('fan@example.com', $subscriber->email);
        $this->assertNull($subscriber->confirmed_at);

        $link = null;
        Notification::assertSentTo(new AnonymousNotifiable, ConfirmNewsletterSubscription::class, function (ConfirmNewsletterSubscription $notification, array $channels, AnonymousNotifiable $notifiable) use (&$link) {
            $link = $notification->toMail($notifiable)->actionUrl;

            return $notifiable->routes['mail'] === 'fan@example.com';
        });

        // Pressing subscribe again straight away sends no second email
        $this->post(route('newsletter.store'), ['email' => 'fan@example.com']);
        Notification::assertSentTimes(ConfirmNewsletterSubscription::class, 1);
        $this->assertSame(0, app(NewsletterService::class)->audience()['total'], 'not confirmed: nobody to send to');

        // A tampered link does nothing; the real one confirms
        $this->get(str_replace('signature=', 'signature=x', $link))->assertForbidden();
        $this->get($link)->assertOk()->assertSee('You are subscribed')->assertSee('fan@example.com');
        $this->assertNotNull($subscriber->fresh()->confirmed_at);
        $this->assertSame(1, app(NewsletterService::class)->audience()['total']);

        // Clicking it twice is fine; once unsubscribed, the old link says so
        $this->get($link)->assertOk()->assertSee('You are subscribed');
        $this->get($subscriber->unsubscribeUrl())->assertOk();
        $this->get($link)->assertOk()->assertSee('This link no longer works');
    }

    public function test_a_signed_in_customers_own_verified_address_needs_no_confirmation_email(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['email' => 'aisha@example.com']);

        $this->actingAs($customer)->post(route('newsletter.store'), ['email' => 'aisha@example.com'])
            ->assertSessionHas('notification', fn (array $flash) => str_contains($flash['message'], 'You are subscribed'));
        $this->assertNotNull(NewsletterSubscriber::sole()->confirmed_at);

        // Someone else's address still has to be confirmed by its owner
        $this->post(route('newsletter.store'), ['email' => 'other@example.com']);
        $this->assertNull(NewsletterSubscriber::where('email', 'other@example.com')->value('confirmed_at'));
        Notification::assertSentTimes(ConfirmNewsletterSubscription::class, 1);
    }

    public function test_dhivehi_signups_are_confirmed_in_dhivehi(): void
    {
        Notification::fake();

        $this->post('/dv/newsletter', ['email' => 'dv@example.com'])->assertRedirect();
        $subscriber = NewsletterSubscriber::sole();
        $this->assertSame('dv', $subscriber->locale);

        Notification::assertSentTo(new AnonymousNotifiable, ConfirmNewsletterSubscription::class, function (ConfirmNewsletterSubscription $notification, array $channels, AnonymousNotifiable $notifiable, ?string $locale) {
            return $locale === 'dv';
        });

        app()->setLocale('dv');
        $mail = (new ConfirmNewsletterSubscription($subscriber))->toMail(new AnonymousNotifiable);
        app()->setLocale('en');
        $this->assertSame('iruali ނިއުސްލެޓަރގެ ސަބްސްކްރިޕްޝަން ޔަޤީންކުރައްވާ', $mail->subject);
        $this->assertStringContainsString('/dv/newsletter/confirm/'.$subscriber->id, $mail->actionUrl);
        $this->get($mail->actionUrl)->assertOk()->assertSee('dir="rtl"', false)->assertSee('ސަބްސްކްރައިބް ކުރެވިއްޖެ');
    }

    // ---- Writing, preview and test -----------------------------------------------------------

    public function test_admins_write_a_newsletter_preview_it_and_send_themselves_a_test(): void
    {
        Mail::fake();
        $admin = $this->staff();
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id, 'is_active' => true, 'stock_quantity' => 3, 'name' => ['en' => 'Reef sandals', 'dv' => 'ރީފް ސެންޑަލް']]);

        $this->actingAs($admin)->get(route('admin.newsletter'))->assertOk()
            ->assertSee('Write a newsletter')
            ->assertSee('href="'.route('admin.newsletter-issues.create').'"', false);
        $this->get(route('admin.newsletter-issues.create'))->assertOk()->assertSee('name="subject_dv"', false)->assertSee('name="new_arrivals_days"', false);

        $this->post(route('admin.newsletter-issues.store'), ['subject_en' => '', 'intro_en' => ''])->assertSessionHasErrors(['subject_en', 'intro_en']);
        $this->post(route('admin.newsletter-issues.store'), [
            'subject_en' => 'New in this week',
            'subject_dv' => 'މި ހަފުތާގެ އައު ތަކެތި',
            'intro_en' => "Fresh from the shops.\n\nSee you soon.",
            'intro_dv' => 'ފިހާރަތަކުން އައި އައު ތަކެތި.',
            'new_arrivals' => '1',
            'new_arrivals_days' => '14',
            'deals' => '0',
            'campaign_id' => '',
            'brands' => '0',
        ])->assertSessionHasNoErrors();

        $issue = NewsletterIssue::sole();
        $this->assertSame('draft', $issue->status);
        $this->assertSame(14, $issue->newArrivalDays());
        $this->assertSame($admin->id, $issue->created_by);

        $this->get(route('admin.newsletter-issues.show', $issue))->assertOk()
            ->assertSee('src="'.route('admin.newsletter-issues.preview', ['issue' => $issue, 'lang' => 'en']).'"', false)
            ->assertSee('Send me a test')
            ->assertSee('data-audience', false);

        $this->get(route('admin.newsletter-issues.preview', ['issue' => $issue, 'lang' => 'en']))->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('Hello Office admin,')
            ->assertSeeInOrder(['Fresh from the shops.', '</p>', 'See you soon.'], false)
            ->assertSee('New in the last 14 days')
            ->assertSee('Reef sandals');
        $this->get(route('admin.newsletter-issues.preview', ['issue' => $issue, 'lang' => 'dv']))->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('ފިހާރަތަކުން އައި އައު ތަކެތި.')
            ->assertSee('ރީފް ސެންޑަލް')
            ->assertSee(url('/dv/products/'), false);

        $this->post(route('admin.newsletter-issues.test', $issue), ['lang' => 'dv'])->assertRedirect()->assertSessionHas('success');
        Mail::assertSent(NewsletterMail::class, function (NewsletterMail $mail) use ($admin) {
            return $mail->hasTo($admin->email) && $mail->locale === 'dv' && $mail->test
                && $mail->unsubscribe === ['route' => 'marketing.unsubscribe', 'params' => ['user' => $admin->id]];
        });
        Mail::assertSentCount(1);
        $this->assertNotNull($issue->fresh()->test_sent_at);
        $this->assertSame('draft', $issue->fresh()->status, 'a test sends nothing to anyone else');

        // Edited while a draft; the edit page is gone once it is sent
        $this->get(route('admin.newsletter-issues.edit', $issue))->assertOk()->assertSee('New in this week');
        $this->put(route('admin.newsletter-issues.update', $issue), ['subject_en' => 'New this week', 'intro_en' => 'Fresh.', 'new_arrivals' => '0'])->assertRedirect(route('admin.newsletter-issues.show', $issue));
        $this->assertSame('New this week', $issue->fresh()->subject_en);
        $this->assertNull($issue->fresh()->newArrivalDays());
    }

    public function test_the_test_email_has_the_subject_marked_and_a_working_unsubscribe_link(): void
    {
        $admin = $this->staff();
        $issue = $this->issue();
        $mail = app(NewsletterService::class)->mail($issue, $admin->name, ['route' => 'marketing.unsubscribe', 'params' => ['user' => $admin->id]], true);

        $this->assertSame('[Test] Eid deals are here', $mail->locale('en')->envelope()->subject);
        $html = $mail->locale('en')->render();
        $this->assertStringContainsString(e(URL::signedRoute('marketing.unsubscribe', ['user' => $admin->id])), $html);
        $this->assertSame('<'.URL::signedRoute('marketing.unsubscribe', ['user' => $admin->id]).'>', $mail->headers()->text['List-Unsubscribe']);

        // A blank line (even with spaces on it) starts a new paragraph; a single line break does not
        $this->assertSame(["One\nline two", 'Three'], NewsletterMail::paragraphs("One\nline two\n  \n\nThree\n"));
        $html = app(NewsletterService::class)->mail($this->issue(['intro_en' => "One\nline two\n\nThree"]), null, null)->locale('en')->render();
        $this->assertMatchesRegularExpression('/>One<br\s*\/?>\s*line two<\/p>\s*<p[^>]*>Three<\/p>/', $html);
    }

    public function test_only_admins_write_and_send_support_sees_the_subscribers(): void
    {
        $support = $this->staff('support');
        $issue = $this->issue();

        $this->actingAs($support)->get(route('admin.newsletter'))->assertOk()->assertDontSee('Write a newsletter');
        $this->get(route('admin.newsletter-issues.create'))->assertForbidden();
        $this->get(route('admin.newsletter-issues.show', $issue))->assertForbidden();
        $this->post(route('admin.newsletter-issues.send', $issue))->assertForbidden();

        $this->actingAs(User::factory()->create())->get(route('admin.newsletter-issues.show', $issue))->assertForbidden();
        $this->assertSame('draft', $issue->fresh()->status);
    }

    // ---- Sending -------------------------------------------------------------------------------

    public function test_each_address_gets_it_once_in_its_language_with_its_unsubscribe_link(): void
    {
        Mail::fake();
        $admin = $this->staff();
        $aisha = User::factory()->create(['email' => 'aisha@example.com', 'name' => 'Aisha', 'preferred_language' => 'en']);
        $hassan = User::factory()->create(['email' => 'hassan@example.com', 'name' => 'Hassan', 'preferred_language' => 'dv']);
        $optedOut = User::factory()->create(['email' => 'no@example.com', 'marketing_opt_out_at' => now()]);
        User::factory()->create(['email' => 'smoke@example.com', 'is_smoke_test' => true]);
        User::factory()->create(['email' => 'banned@example.com', 'banned_until' => now()->addMonth()]);
        User::factory()->create(['email' => 'inactive@example.com', 'is_active' => false]);
        $this->staff('support'); // staff accounts are not sent marketing
        $fan = $this->subscriber('fan@example.com');
        $this->subscriber('HASSAN@example.com', 'en'); // also a customer: one email, the account's language
        $this->subscriber('waiting@example.com', 'en', false); // never confirmed
        $this->subscriber('no@example.com'); // their account opted out

        $issue = $this->issue();
        $this->actingAs($admin)->get(route('admin.newsletter-issues.show', $issue))->assertOk()->assertSee('It would go to <strong>3</strong> addresses', false);
        $this->assertSame(['customers' => 2, 'subscribers' => 1, 'total' => 3], app(NewsletterService::class)->audience());

        $this->post(route('admin.newsletter-issues.send', $issue))->assertRedirect(route('admin.newsletter-issues.show', $issue))
            ->assertSessionHas('success', 'Sending to 3 addresses. The emails go out in batches; this page shows how far it got.');

        Mail::assertSentCount(3);
        Mail::assertSent(NewsletterMail::class, fn (NewsletterMail $mail) => $mail->hasTo('aisha@example.com') && $mail->locale === 'en' && $mail->name === 'Aisha'
            && $mail->unsubscribe === ['route' => 'marketing.unsubscribe', 'params' => ['user' => $aisha->id]]);
        Mail::assertSent(NewsletterMail::class, fn (NewsletterMail $mail) => $mail->hasTo('hassan@example.com') && $mail->locale === 'dv'
            && $mail->unsubscribe === ['route' => 'marketing.unsubscribe', 'params' => ['user' => $hassan->id]]);
        Mail::assertSent(NewsletterMail::class, fn (NewsletterMail $mail) => $mail->hasTo('fan@example.com') && $mail->locale === 'en' && $mail->name === null
            && $mail->unsubscribe === ['route' => 'newsletter.unsubscribe', 'params' => ['subscriber' => $fan->id]]);
        foreach (['no@example.com', 'smoke@example.com', 'banned@example.com', 'inactive@example.com', 'waiting@example.com', $admin->email] as $left) {
            Mail::assertNotSent(NewsletterMail::class, fn (NewsletterMail $mail) => $mail->hasTo($left));
        }

        $issue->refresh();
        $this->assertSame(['sent', 3, 3, 0], [$issue->status, $issue->recipients_count, $issue->sent_count, $issue->failed_count]);
        $this->assertSame($admin->id, $issue->sent_by);
        $this->assertSame(3, NewsletterDelivery::where('status', 'sent')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'newsletter.sent', 'subject_id' => $issue->id, 'user_id' => $admin->id]);
        $this->assertNotNull($issue->content);

        // The Dhivehi reader's email is in Dhivehi, right to left, with the signed link that works
        $dvMail = Mail::sent(NewsletterMail::class, fn (NewsletterMail $mail) => $mail->hasTo('hassan@example.com'))->first();
        $html = $dvMail->render();
        $this->assertSame('ޢީދު ޑީލްތައް އައިއްސި', $dvMail->envelope()->subject);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('މި ހަފުތާގައި ބޮޑު ޑިސްކައުންޓް.', $html);
        $this->assertStringContainsString('Hassan', $html);
        $link = URL::signedRoute('marketing.unsubscribe', ['user' => $hassan->id]);
        $this->assertStringContainsString(e($link), $html);
        $this->get($link)->assertOk();
        $this->assertNotNull($hassan->fresh()->marketing_opt_out_at);

        $fanMail = Mail::sent(NewsletterMail::class, fn (NewsletterMail $mail) => $mail->hasTo('fan@example.com'))->first();
        $this->assertSame('Eid deals are here', $fanMail->envelope()->subject);
        $this->assertStringContainsString('Hello,', $fanMail->render());
        $this->get($fanMail->unsubscribeUrl())->assertOk()->assertSee('You are unsubscribed');
        $this->assertModelMissing($fan);

        // The page shows the result; the list too
        $this->get(route('admin.newsletter-issues.show', $issue))->assertOk()->assertSee('data-delivery-counts', false)->assertDontSee('Send me a test');
        $this->get(route('admin.newsletter'))->assertOk()->assertSee('Eid deals are here')->assertSee('3 / 3');
    }

    public function test_an_issue_never_goes_to_an_address_twice(): void
    {
        Mail::fake();
        $admin = $this->staff();
        User::factory()->count(2)->create();
        $this->subscriber('fan@example.com');
        $issue = $this->issue();

        $this->actingAs($admin)->post(route('admin.newsletter-issues.send', $issue));
        Mail::assertSentCount(3);

        // Send pressed again, a batch retried by the queue, or run by hand: nothing more goes out
        $this->post(route('admin.newsletter-issues.send', $issue))->assertSessionHasErrors('send');
        (new SendNewsletterBatch($issue->id, NewsletterDelivery::pluck('id')->all()))->handle(app(NewsletterService::class));
        $this->assertNull(app(NewsletterService::class)->queue($issue->fresh(), $admin));
        Mail::assertSentCount(3);

        // A try that died after claiming a row (mid-send) is retried: the claimed row is not sent again
        $second = $this->issue(['subject_en' => 'Second']);
        Queue::fake();
        $this->post(route('admin.newsletter-issues.send', $second));
        $rows = NewsletterDelivery::where('newsletter_issue_id', $second->id)->orderBy('id')->get();
        $this->assertCount(3, $rows);
        $rows[0]->forceFill(['status' => 'sending'])->save();
        (new SendNewsletterBatch($second->id, $rows->modelKeys()))->handle(app(NewsletterService::class));
        (new SendNewsletterBatch($second->id, $rows->modelKeys()))->handle(app(NewsletterService::class));

        Mail::assertSentCount(5);
        Mail::assertNotSent(NewsletterMail::class, fn (NewsletterMail $mail) => $mail->issue->is($second) && $mail->hasTo($rows[0]->email));
        $second->refresh();
        $this->assertSame(['sent', 2, 1], [$second->status, $second->sent_count, $second->failed_count]);
    }

    public function test_sending_is_queued_in_spaced_batches(): void
    {
        Queue::fake();
        config(['newsletter.batch_size' => 2, 'newsletter.minutes_between_batches' => 5]);
        $admin = $this->staff();
        User::factory()->count(5)->create();
        $issue = $this->issue();

        $this->actingAs($admin)->post(route('admin.newsletter-issues.send', $issue))->assertSessionHas('success');

        $this->assertSame('sending', $issue->fresh()->status);
        Queue::assertPushed(SendNewsletterBatch::class, 3);
        $delays = [];
        Queue::assertPushed(SendNewsletterBatch::class, function (SendNewsletterBatch $job) use (&$delays, $issue) {
            $delays[] = [count($job->deliveryIds), (int) round(now()->diffInMinutes($job->delay))];

            return $job->issueId === $issue->id;
        });
        $this->assertSame([[2, 0], [2, 5], [1, 10]], $delays);

        $this->get(route('admin.newsletter-issues.show', $issue))->assertOk()->assertSee('Waiting');
    }

    public function test_readers_who_unsubscribe_after_the_send_started_are_skipped(): void
    {
        Mail::fake();
        Queue::fake();
        $admin = $this->staff();
        $leaving = User::factory()->create(['email' => 'leaving@example.com']);
        User::factory()->create(['email' => 'staying@example.com']);
        $fan = $this->subscriber('fan@example.com');
        $issue = $this->issue();
        $this->actingAs($admin)->post(route('admin.newsletter-issues.send', $issue));

        $leaving->forceFill(['marketing_opt_out_at' => now()])->save();
        $fan->delete();
        (new SendNewsletterBatch($issue->id, NewsletterDelivery::pluck('id')->all()))->handle(app(NewsletterService::class));

        Mail::assertSentCount(1);
        Mail::assertSent(NewsletterMail::class, fn (NewsletterMail $mail) => $mail->hasTo('staying@example.com'));
        $this->assertSame(['sent' => 1, 'skipped' => 2], NewsletterDelivery::query()->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->orderBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all());
        $this->assertSame('sent', $issue->fresh()->status);
    }

    public function test_emails_the_mail_server_refused_can_be_sent_again(): void
    {
        $admin = $this->staff();
        User::factory()->create(['email' => 'good@example.com']);
        $broken = $this->subscriber('broken@@example.com'); // refused by the mailer
        $issue = $this->issue();

        $this->actingAs($admin)->post(route('admin.newsletter-issues.send', $issue));
        $issue->refresh();
        $this->assertSame(['sent', 1, 1], [$issue->status, $issue->sent_count, $issue->failed_count]);
        $this->assertNotNull(NewsletterDelivery::where('status', 'failed')->value('error'));
        $this->get(route('admin.newsletter-issues.show', $issue))->assertSee('Try the failed ones again');

        // Fixed (here: the address), then retried: only the failed one goes again
        NewsletterDelivery::where('newsletter_subscriber_id', $broken->id)->update(['email' => 'fixed@example.com']);
        $this->post(route('admin.newsletter-issues.retry', $issue))->assertSessionHas('success', '1 failed emails are queued again.');

        $issue->refresh();
        $this->assertSame(['sent', 2, 0], [$issue->status, $issue->sent_count, $issue->failed_count]);
        $sentTo = collect(app('mailer')->getSymfonyTransport()->messages())->map(fn ($message) => $message->getEnvelope()->getRecipients()[0]->getAddress())->all();
        $this->assertSame(['good@example.com', 'fixed@example.com'], $sentTo);
    }

    public function test_it_needs_dhivehi_and_someone_to_send_to(): void
    {
        Mail::fake();
        $admin = $this->staff();
        $issue = $this->issue(['subject_dv' => null]);

        $this->actingAs($admin)->post(route('admin.newsletter-issues.send', $issue))->assertSessionHasErrors('send');
        $this->assertSame('draft', $issue->fresh()->status);

        $issue->update(['subject_dv' => 'ޢީދު']);
        $this->post(route('admin.newsletter-issues.send', $issue))->assertSessionHasErrors('send'); // nobody yet
        $this->assertSame('draft', $issue->fresh()->status);
        Mail::assertNothingSent();

        // Drafts can be deleted; sent issues cannot be edited or deleted
        $this->delete(route('admin.newsletter-issues.destroy', $issue))->assertRedirect(route('admin.newsletter'));
        $this->assertModelMissing($issue);
    }

    public function test_sections_are_filled_with_new_arrivals_deals_the_campaign_and_brands(): void
    {
        Mail::fake();
        $admin = $this->staff();
        $category = Category::factory()->create();
        $make = fn (array $attributes) => Product::factory()->create(array_merge(['category_id' => $category->id, 'is_active' => true, 'stock_quantity' => 3, 'price' => 100], $attributes));

        $this->travel(-20)->days();
        $old = $make(['name' => ['en' => 'Old lamp']]);
        $oldDeal = $make(['name' => ['en' => 'Old deal'], 'price' => 50, 'compare_price' => 100, 'brand' => 'Reefline']);
        $this->travel(20)->days();
        $new = $make(['name' => ['en' => 'New kettle']]);
        $newDeal = $make(['name' => ['en' => 'New deal'], 'price' => 80, 'compare_price' => 100]);
        $make(['name' => ['en' => 'Hidden new'], 'is_active' => false]);
        $make(['name' => ['en' => 'Sold out new'], 'stock_quantity' => 0]);
        $campaign = Campaign::factory()->create(['name' => 'Eid sale', 'headline' => ['en' => 'Eid sale is on', 'dv' => 'ޢީދު ސޭލް']]);
        CampaignProduct::create(['campaign_id' => $campaign->id, 'product_id' => $old->id, 'seller_id' => $old->seller_id, 'approved_at' => now()]);
        $ended = Campaign::factory()->create(['starts_at' => now()->subWeeks(2), 'ends_at' => now()->subWeek()]);
        User::factory()->create();

        $issue = $this->issue(['sections' => ['new_arrivals_days' => 7, 'deals' => true, 'campaign_id' => $campaign->id, 'brands' => true]]);
        $content = app(NewsletterService::class)->pick($issue);
        $this->assertSame(7, $content['new_arrivals']['days']);
        $this->assertSame([$newDeal->id, $new->id], $content['new_arrivals']['products']);
        $this->assertSame([$oldDeal->id], $content['deals'], 'listed once: the new deal is already in new arrivals');
        $this->assertSame(['id' => $campaign->id, 'products' => [$old->id]], $content['campaign']);
        $this->assertSame([Brand::where('name', 'Reefline')->value('id')], array_values(array_intersect($content['brands'], [Brand::where('name', 'Reefline')->value('id')])));

        $this->actingAs($admin)->post(route('admin.newsletter-issues.send', $issue));
        $this->assertSame($content, $issue->fresh()->content, 'frozen when sent');

        $html = Mail::sent(NewsletterMail::class)->first()->render();
        foreach (['New in the last 7 days', 'New kettle', 'Deals right now', 'Old deal', 'Eid sale is on', 'Old lamp', 'Brands to discover', 'Reefline', route('deals'), route('campaigns.show', $campaign)] as $needle) {
            $this->assertStringContainsString(e($needle), $html, $needle);
        }
        $this->assertStringNotContainsString('Hidden new', $html);
        $this->assertStringNotContainsString('Sold out new', $html);

        // A campaign that has ended is left out
        $later = $this->issue(['sections' => ['new_arrivals_days' => null, 'deals' => false, 'campaign_id' => $ended->id, 'brands' => false]]);
        $this->assertSame([], app(NewsletterService::class)->pick($later));
    }
}
