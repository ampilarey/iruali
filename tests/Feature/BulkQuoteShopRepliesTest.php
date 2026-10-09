<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\QuoteMessage;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Notifications\QuoteAccepted;
use App\Notifications\QuoteDeclined;
use App\Notifications\QuoteMessageReceived;
use App\Notifications\QuoteReceived;
use App\Notifications\QuoteRequested;
use App\Notifications\QuoteWithdrawn;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsQuotes;
use Tests\TestCase;

/**
 * Bulk quotes, the shop's side: Seller Centre → Quote requests (its own only), sending or changing a
 * quote, declining with a reason, the request's messages, and the emails each step sends.
 */
class BulkQuoteShopRepliesTest extends TestCase
{
    use BuildsQuotes, RefreshDatabase;

    protected User $shopUser;

    protected Product $product;

    protected User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-15 10:00'));
        $this->shopUser = $this->shop('Island Crafts');
        $this->product = $this->productOf($this->shopUser, 120, ['name' => ['en' => 'Coconut soap'], 'stock_quantity' => 500]);
        $this->buyer = $this->customer();
    }

    protected function quoteForm(array $overrides = []): array
    {
        return array_merge(['unit_price' => '95.50', 'quantity' => '35', 'valid_until' => '2026-10-22', 'message' => 'Packed in boxes of 5. Next boat on Thursday.'], $overrides);
    }

    public function test_the_seller_centre_lists_the_shops_own_requests_by_status(): void
    {
        $other = $this->shop('Reefline Marine');
        $mine = $this->quoteFor($this->buyer, $this->product);
        $quoted = $this->quoteFor($this->customer(), $this->productOf($this->shopUser, 60, ['name' => ['en' => 'Palm basket']]), ['status' => 'quoted']);
        $theirs = $this->quoteFor($this->buyer, $this->productOf($other, 40, ['name' => ['en' => 'Snorkel set']]));

        $this->actingAs($this->shopUser)->get(route('seller.dashboard'))->assertOk()->assertSee(route('seller.quotes'), false)->assertSee('Quote requests');
        $this->get(route('seller.quotes'))->assertOk()
            ->assertSee('data-quote-row="'.$mine->id.'"', false)->assertSee('Sun Island Resort Pvt Ltd')->assertSee('Coconut soap')
            ->assertDontSee('data-quote-row="'.$quoted->id.'"', false)->assertDontSee('Snorkel set');
        $this->get(route('seller.quotes', ['status' => 'quoted']))->assertOk()->assertSee('data-quote-row="'.$quoted->id.'"', false)->assertDontSee('data-quote-row="'.$mine->id.'"', false);
        $this->get(route('seller.quotes', ['status' => 'all']))->assertOk()->assertSee('Palm basket')->assertSee('Coconut soap')->assertDontSee('Snorkel set');

        $this->get(route('seller.quotes.show', $mine))->assertOk()->assertSee('Sun Island Resort Pvt Ltd')->assertSee('1012345GST501')
            ->assertSee('Hithadhoo, Addu')->assertSee('For the staff canteen.')->assertSee('Send a quote');

        // Another shop's request is not this shop's business
        $this->get(route('seller.quotes.show', $theirs))->assertForbidden();
        $this->post(route('seller.quotes.quote', $theirs), $this->quoteForm())->assertForbidden();
        $this->post(route('seller.quotes.decline', $theirs), ['reason' => 'No'])->assertForbidden();
        $this->post(route('seller.quotes.messages', $theirs), ['body' => 'Hello'])->assertForbidden();
        $this->assertSame('new', $theirs->fresh()->status);
        $this->assertSame(0, QuoteMessage::count());

        // Customers are not shops
        $this->actingAs($this->buyer)->get(route('seller.quotes'))->assertForbidden();
        $this->actingAs($this->buyer)->get(route('seller.quotes.show', $mine))->assertForbidden();
    }

    public function test_the_shop_sends_a_quote_and_the_customer_is_emailed(): void
    {
        $quote = $this->quoteFor($this->buyer, $this->product);

        $this->actingAs($this->shopUser)->post(route('seller.quotes.quote', $quote), $this->quoteForm())
            ->assertRedirect(route('seller.quotes.show', $quote))->assertSessionHas('success');

        $quote->refresh();
        $this->assertSame('quoted', $quote->status);
        $this->assertSame('95.50', $quote->unit_price);
        $this->assertSame(35, $quote->quoted_quantity);
        $this->assertSame('2026-10-22', $quote->valid_until->toDateString());
        $this->assertSame('120.00', $quote->list_price, 'the listed price is kept for comparison');
        $this->assertSame(3342.5, $quote->lineTotal());
        $this->assertSame(20.4, $quote->savingPercent());
        Notification::assertSentTo($this->buyer, QuoteReceived::class, fn ($n) => $n->quote->is($quote) && ! $n->changed);

        $this->actingAs($this->buyer)->get(route('quotes.show', $quote))->assertOk()
            ->assertSee('Island Crafts sent you a quote.', false)->assertSee('MVR 95.50')->assertSee('MVR 3,342.50')
            ->assertSee('20.4% under the listed MVR 120.00')->assertSee('Packed in boxes of 5.')->assertSee('Accept and add to cart');
    }

    public function test_the_quote_is_validated(): void
    {
        $quote = $this->quoteFor($this->buyer, $this->product);
        $this->actingAs($this->shopUser);

        $this->post(route('seller.quotes.quote', $quote), $this->quoteForm(['unit_price' => '']))->assertSessionHasErrorsIn('quote', 'unit_price');
        $this->post(route('seller.quotes.quote', $quote), $this->quoteForm(['unit_price' => '0']))->assertSessionHasErrorsIn('quote', 'unit_price');
        $this->post(route('seller.quotes.quote', $quote), $this->quoteForm(['unit_price' => '9.999']))->assertSessionHasErrorsIn('quote', 'unit_price');
        $this->post(route('seller.quotes.quote', $quote), $this->quoteForm(['quantity' => '0']))->assertSessionHasErrorsIn('quote', 'quantity');
        $this->post(route('seller.quotes.quote', $quote), $this->quoteForm(['valid_until' => '2026-10-14']))->assertSessionHasErrorsIn('quote', 'valid_until');
        $this->post(route('seller.quotes.quote', $quote), $this->quoteForm(['valid_until' => '2026-12-31']))->assertSessionHasErrorsIn('quote', 'valid_until');
        $this->post(route('seller.quotes.quote', $quote), $this->quoteForm(['message' => str_repeat('a', 1001)]))->assertSessionHasErrorsIn('quote', 'message');
        // Price × quantity stays within what one order line can hold
        $this->post(route('seller.quotes.quote', $quote), $this->quoteForm(['unit_price' => '999999', 'quantity' => '50']))->assertSessionHasErrorsIn('quote', 'unit_price');

        $this->assertSame('new', $quote->fresh()->status);
        Notification::assertNothingSentTo($this->buyer);

        // Today is a valid last day; the form offers a week by default
        $this->get(route('seller.quotes.show', $quote))->assertOk()->assertSee('value="2026-10-22"', false);
        $this->post(route('seller.quotes.quote', $quote), $this->quoteForm(['valid_until' => '2026-10-15', 'message' => '']))->assertSessionHasNoErrors();
        $this->assertNull($quote->fresh()->shop_message);
    }

    public function test_the_shop_may_change_its_quote_until_the_customer_accepts(): void
    {
        $quote = $this->quoteFor($this->buyer, $this->product, ['status' => 'quoted']);

        $this->actingAs($this->shopUser)->get(route('seller.quotes.show', $quote))->assertOk()->assertSee('Change your quote');
        $this->post(route('seller.quotes.quote', $quote), $this->quoteForm(['unit_price' => '90']))->assertSessionHasNoErrors();
        $this->assertSame('90.00', $quote->fresh()->unit_price);
        Notification::assertSentTo($this->buyer, QuoteReceived::class, fn ($n) => $n->changed);
        $mail = (new QuoteReceived($quote->fresh(), true))->toMail($this->buyer);
        $this->assertSame('Island Crafts changed its quote for Coconut soap', $mail->subject);

        // Once accepted (or closed) it can't change
        $quote->forceFill(['status' => 'accepted'])->save();
        $this->post(route('seller.quotes.quote', $quote), $this->quoteForm(['unit_price' => '50']))->assertSessionHas('error');
        $this->assertSame('90.00', $quote->fresh()->unit_price);
        $this->get(route('seller.quotes.show', $quote))->assertOk()->assertDontSee('Change your quote')->assertSee('the quote is in the customer&#039;s cart', false);
    }

    public function test_the_shop_declines_with_a_reason_and_the_customer_is_told(): void
    {
        $quote = $this->quoteFor($this->buyer, $this->product);
        $this->actingAs($this->shopUser);

        $this->post(route('seller.quotes.decline', $quote), ['reason' => ''])->assertSessionHasErrorsIn('decline', 'reason');
        $this->post(route('seller.quotes.decline', $quote), ['reason' => 'We cannot make 40 before that date.'])
            ->assertRedirect(route('seller.quotes.show', $quote))->assertSessionHas('success');

        $quote->refresh();
        $this->assertSame('declined', $quote->status);
        $this->assertSame('shop', $quote->declined_by);
        $this->assertSame('We cannot make 40 before that date.', $quote->decline_reason);
        Notification::assertSentTo($this->buyer, QuoteDeclined::class);
        Notification::assertNotSentTo($this->shopUser, QuoteWithdrawn::class);

        $this->actingAs($this->buyer)->get(route('quotes.show', $quote))->assertOk()->assertSee('Declined by Island Crafts')->assertSee('We cannot make 40 before that date.');

        // A declined request can't be quoted or declined again
        $this->actingAs($this->shopUser)->post(route('seller.quotes.quote', $quote), $this->quoteForm())->assertSessionHas('error');
        $this->post(route('seller.quotes.decline', $quote), ['reason' => 'Again'])->assertSessionHas('error');
        $this->assertSame('declined', $quote->fresh()->status);
    }

    public function test_both_sides_write_in_the_thread(): void
    {
        $quote = $this->quoteFor($this->buyer, $this->product);

        $this->actingAs($this->buyer)->post(route('quotes.messages', $quote), ['body' => ''])->assertSessionHasErrorsIn('quoteMessage', 'body');
        $this->post(route('quotes.messages', $quote), ['body' => str_repeat('a', 1001)])->assertSessionHasErrorsIn('quoteMessage', 'body');
        $this->post(route('quotes.messages', $quote), ['body' => 'Can you print our logo on the boxes?'])->assertRedirect(route('quotes.show', $quote).'#messages');
        $this->assertSame(1, $quote->fresh()->seller_unread);
        Notification::assertSentTo($this->shopUser, QuoteMessageReceived::class);

        $this->actingAs($this->shopUser)->get(route('seller.quotes', ['status' => 'new']))->assertSee('1 new message');
        $this->get(route('seller.quotes.show', $quote))->assertOk()->assertSee('Can you print our logo on the boxes?')->assertSee('Sun Island Resort Pvt Ltd');
        $this->assertSame(0, $quote->fresh()->seller_unread, 'opening the request reads it');

        $this->post(route('seller.quotes.messages', $quote), ['body' => 'Yes, for MVR 2 more each.'])->assertRedirect(route('seller.quotes.show', $quote).'#messages');
        $this->assertSame(1, $quote->fresh()->customer_unread);
        Notification::assertSentTo($this->buyer, QuoteMessageReceived::class);
        $this->actingAs($this->buyer)->get(route('quotes.index'))->assertSee('1 new message');
        $this->get(route('quotes.show', $quote))->assertOk()->assertSee('Yes, for MVR 2 more each.')->assertSee('Island Crafts');

        $messages = QuoteMessage::orderBy('id')->get();
        $this->assertSame(['customer', 'seller'], $messages->pluck('sender_role')->all());

        // A second message within ten minutes adds to the thread without another email
        Notification::fake();
        $this->actingAs($this->buyer)->post(route('quotes.messages', $quote), ['body' => 'Great, please send the quote.']);
        Notification::assertNothingSentTo($this->shopUser);
        $this->travel(11)->minutes();
        $this->post(route('quotes.messages', $quote), ['body' => 'Any news?']);
        Notification::assertSentTo($this->shopUser, QuoteMessageReceived::class);

        // Another customer can't read or write in it; a closed request takes no more messages
        $this->actingAs($this->customer())->get(route('quotes.show', $quote))->assertForbidden();
        $this->post(route('quotes.messages', $quote), ['body' => 'Hi'])->assertForbidden();
        $quote->forceFill(['status' => 'declined', 'declined_by' => 'shop'])->save();
        $this->actingAs($this->buyer)->post(route('quotes.messages', $quote), ['body' => 'Hello?'])->assertSessionHas('notification');
        $this->assertSame(4, QuoteMessage::count());
        $this->get(route('quotes.show', $quote))->assertOk()->assertSee('This request is closed, so no more messages can be added.');
    }

    public function test_the_emails_say_what_happened_and_link_to_the_request(): void
    {
        foreach ([QuoteRequested::class, QuoteReceived::class, QuoteDeclined::class, QuoteAccepted::class, QuoteWithdrawn::class, QuoteMessageReceived::class] as $class) {
            $this->assertContains(ShouldQueue::class, class_implements($class), "{$class} should be queued");
        }

        $quote = $this->quoteFor($this->buyer, $this->product, ['status' => 'quoted', 'unit_price' => 95.5, 'quoted_quantity' => 35, 'shop_message' => 'Packed in boxes of 5.']);

        $mail = (new QuoteRequested($quote))->toMail($this->shopUser);
        $this->assertSame('New bulk quote request: Coconut soap', $mail->subject);
        $this->assertStringContainsString('Sun Island Resort Pvt Ltd asks you for a price on 40 × Coconut soap.', implode(' ', $mail->introLines));
        $this->assertSame(route('seller.quotes.show', $quote), $mail->actionUrl);

        $mail = (new QuoteReceived($quote))->toMail($this->buyer);
        $this->assertSame('Island Crafts sent you a quote for Coconut soap', $mail->subject);
        $this->assertStringContainsString('MVR 95.50 × 35 = MVR 3,342.50', implode(' ', $mail->introLines));
        $this->assertStringContainsString('Packed in boxes of 5.', implode(' ', $mail->introLines));
        $this->assertSame(route('quotes.show', $quote), $mail->actionUrl);

        $quote->forceFill(['status' => 'declined', 'declined_by' => 'shop', 'decline_reason' => 'Out of season'])->save();
        $mail = (new QuoteDeclined($quote))->toMail($this->buyer);
        $this->assertSame('Island Crafts can\'t quote for Coconut soap', $mail->subject);
        $this->assertStringContainsString('Reason: Out of season', implode(' ', $mail->introLines));

        $mail = (new QuoteAccepted($quote))->toMail($this->shopUser);
        $this->assertSame('Quote #'.$quote->id.' accepted: Coconut soap', $mail->subject);
        $this->assertSame(route('seller.quotes.show', $quote), $mail->actionUrl);

        $quote->forceFill(['declined_by' => 'customer'])->save();
        $mail = (new QuoteWithdrawn($quote))->toMail($this->shopUser);
        $this->assertSame('Quote #'.$quote->id.' was declined by the customer', $mail->subject);
        $this->assertStringContainsString('Sun Island Resort Pvt Ltd declined your quote for Coconut soap.', implode(' ', $mail->introLines));
    }

    public function test_quote_emails_follow_the_shop_and_customer_preferences(): void
    {
        // The shop can turn quote emails off under Settings → Notifications
        $this->actingAs($this->shopUser)->get(route('seller.settings.notifications'))->assertOk()->assertSee('Bulk quote requests');
        $this->put('/seller/settings/notifications', ['new_order' => 1, 'return' => 1, 'payout' => 1, 'low_stock' => 1, 'quotes' => 0])->assertRedirect();
        $this->assertFalse($this->shopUser->fresh()->wantsNotification('quotes'));

        $this->sendRequest($this->buyer, $this->product)->assertSessionHasNoErrors();
        $quote = QuoteRequest::sole();
        Notification::assertNotSentTo($this->shopUser, QuoteRequested::class);

        // A customer who chose SMS for order updates gets the quote by SMS
        $this->buyer->forceFill(['phone' => '+9607771234', 'phone_verified_at' => now(), 'notification_preferences' => ['customer' => ['order_updates' => 'sms']]])->save();
        $this->actingAs($this->shopUser)->post(route('seller.quotes.quote', $quote), $this->quoteForm())->assertSessionHasNoErrors();
        Notification::assertSentTo($this->buyer, QuoteReceived::class, fn ($n, $channels) => $channels === ['sms']);
        $this->assertStringContainsString('Island Crafts sent you a quote for Coconut soap', (new QuoteReceived($quote->fresh()))->toSms($this->buyer));
    }
}
