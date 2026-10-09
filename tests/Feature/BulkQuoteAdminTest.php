<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\QuoteMessage;
use App\Models\User;
use App\Notifications\QuoteDeclined;
use App\Notifications\QuoteMessageReceived;
use App\Notifications\QuoteWithdrawn;
use App\Support\AdminInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsQuotes;
use Tests\TestCase;

/**
 * Bulk quotes for staff: Admin → Quotes with its status filters, the inbox row for requests no shop
 * has answered for more than two days, writing in a request and closing it. Only these staff
 * actions are audited; what customers and shops do is not.
 */
class BulkQuoteAdminTest extends TestCase
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

    public function test_the_quotes_page_lists_every_request_with_status_filters(): void
    {
        $waiting = $this->quoteFor($this->buyer, $this->product, ['created_at' => now()->subDays(3)]);
        $fresh = $this->quoteFor($this->customer(), $this->product);
        $quoted = $this->quoteFor($this->customer(), $this->productOf($this->shop('Reefline Marine'), 40, ['name' => ['en' => 'Snorkel set']]), ['status' => 'quoted']);
        $admin = $this->staffMember('admin');

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.quotes'), false);
        $this->get(route('admin.quotes'))->assertOk()
            ->assertSee('data-quote-row="'.$waiting->id.'"', false)->assertSee('data-quote-row="'.$fresh->id.'"', false)->assertSee('data-quote-row="'.$quoted->id.'"', false)
            ->assertSee('Waiting for a shop (2+ days) (1)', false)->assertSee('All (3)');
        $this->get(route('admin.quotes', ['status' => 'waiting']))->assertOk()
            ->assertSee('data-quote-row="'.$waiting->id.'"', false)->assertDontSee('data-quote-row="'.$fresh->id.'"', false)->assertDontSee('data-quote-row="'.$quoted->id.'"', false);
        $this->get(route('admin.quotes', ['status' => 'quoted']))->assertOk()
            ->assertSee('data-quote-row="'.$quoted->id.'"', false)->assertDontSee('data-quote-row="'.$waiting->id.'"', false)->assertSee('Snorkel set');

        $this->get(route('admin.quotes.show', $quoted))->assertOk()->assertSee('Sun Island Resort Pvt Ltd')->assertSee('Reefline Marine')
            ->assertSee('MVR 80.00')->assertSee('Close this request');
    }

    public function test_the_inbox_counts_requests_waiting_more_than_two_days_for_a_shop(): void
    {
        $this->quoteFor($this->buyer, $this->product, ['created_at' => now()->subDays(2)->subMinute()]);
        $this->quoteFor($this->customer(), $this->product, ['created_at' => now()->subDays(1)]);
        $this->quoteFor($this->customer(), $this->product, ['created_at' => now()->subDays(5), 'status' => 'quoted']); // answered

        $row = collect(AdminInbox::all())->firstWhere('key', 'quotes_waiting');
        $this->assertSame('Quote requests waiting more than 2 days for a shop', $row['label']);
        $this->assertSame(1, $row['count']);
        $this->assertSame(route('admin.quotes', ['status' => 'waiting']), $row['url']);

        $this->actingAs($this->staffMember('admin'))->get(route('admin.inbox'))->assertOk()->assertSee('Quote requests waiting more than 2 days for a shop');
        // Support follows shops up; finance does not see the row
        $this->assertNotNull(collect(AdminInbox::items($this->staffMember('support')))->firstWhere('key', 'quotes_waiting'));
        $this->assertNull(collect(AdminInbox::items($this->staffMember('finance')))->firstWhere('key', 'quotes_waiting'));
    }

    public function test_staff_close_a_request_both_sides_are_told_and_it_is_audited(): void
    {
        $quote = $this->quoteFor($this->buyer, $this->product, ['status' => 'quoted']);
        $this->accept($quote);
        $this->assertSame(1, CartItem::count());
        $support = $this->staffMember('support');

        $this->actingAs($support)->post(route('admin.quotes.close', $quote), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('admin.quotes.close', $quote), ['reason' => 'The price was a typing mistake.'])->assertRedirect(route('admin.quotes.show', $quote));

        $quote->refresh();
        $this->assertSame(['declined', 'admin', 'The price was a typing mistake.'], [$quote->status, $quote->declined_by, $quote->decline_reason]);
        $this->assertSame(0, CartItem::count(), 'the quote came out of the cart');
        Notification::assertSentTo($this->buyer, QuoteDeclined::class);
        Notification::assertSentTo($this->shopUser, QuoteWithdrawn::class);
        $this->assertSame('Your quote request for Coconut soap was closed', (new QuoteDeclined($quote))->toMail($this->buyer)->subject);

        $log = AuditLog::where('action', 'quote.closed')->sole();
        $this->assertSame($support->id, $log->user_id);
        $this->assertSame($quote->id, $log->subject_id);
        $this->assertSame(['from' => 'accepted', 'to' => 'declined', 'reason' => 'The price was a typing mistake.'], $log->changes);

        $this->actingAs($this->buyer)->get(route('quotes.show', $quote))->assertSee('Closed by iruali')->assertSee('The price was a typing mistake.');

        // A closed request can't be closed again
        $this->actingAs($support)->post(route('admin.quotes.close', $quote), ['reason' => 'Again'])->assertSessionHas('error');
        $this->assertSame(1, AuditLog::where('action', 'quote.closed')->count());
    }

    public function test_staff_write_in_a_request_as_iruali_support_and_only_staff_actions_are_audited(): void
    {
        $quote = $this->quoteFor($this->buyer, $this->product);

        // The customer's and the shop's own steps leave no audit trail
        $this->actingAs($this->buyer)->post(route('quotes.messages', $quote), ['body' => 'Any news?']);
        $this->actingAs($this->shopUser)->post(route('seller.quotes.quote', $quote), ['unit_price' => '90', 'quantity' => '40', 'valid_until' => '2026-10-20']);
        $this->actingAs($this->shopUser)->post(route('seller.quotes.messages', $quote), ['body' => 'Sent!']);
        $this->assertSame(0, AuditLog::where('action', 'like', 'quote.%')->count());

        Notification::fake();
        $this->travel(11)->minutes(); // past the "one email every ten minutes" of the messages above
        $admin = $this->staffMember('admin');
        $this->actingAs($admin)->post(route('admin.quotes.messages', $quote), ['body' => 'iruali here: the shop has replied, please check.'])
            ->assertRedirect(route('admin.quotes.show', $quote).'#messages');

        $message = QuoteMessage::latest('id')->first();
        $this->assertSame('admin', $message->sender_role);
        $this->assertSame('iruali support', $message->senderName());
        Notification::assertSentTo($this->buyer, QuoteMessageReceived::class);
        Notification::assertSentTo($this->shopUser, QuoteMessageReceived::class);
        $this->assertSame(1, AuditLog::where('action', 'quote.message')->where('subject_id', $quote->id)->count());

        $this->actingAs($this->buyer)->get(route('quotes.show', $quote))->assertSee('iruali here: the shop has replied, please check.')->assertSee('iruali support');
    }

    public function test_only_staff_who_may_open_quotes_get_in(): void
    {
        $quote = $this->quoteFor($this->buyer, $this->product);

        $this->actingAs($this->staffMember('support'))->get(route('admin.quotes'))->assertOk();
        $this->get(route('admin.quotes.show', $quote))->assertOk();

        $this->actingAs($this->staffMember('finance'))->get(route('admin.quotes'))->assertForbidden();
        $this->post(route('admin.quotes.close', $quote), ['reason' => 'x'])->assertForbidden();

        $this->actingAs($this->buyer)->get(route('admin.quotes'))->assertForbidden();
        $this->actingAs($this->shopUser)->get(route('admin.quotes.show', $quote))->assertForbidden();
        $this->post(route('admin.quotes.messages', $quote), ['body' => 'x'])->assertForbidden();
        $this->assertSame('new', $quote->fresh()->status);
        $this->assertSame(0, QuoteMessage::count());
    }
}
