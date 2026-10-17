<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SellerWaitlist;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SellerApplicationsReopened;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * New shop applications can be paused (the links stay, the page explains) or hidden (the links come
 * off the site), until a reopening day or until an admin opens them again. People can ask to be told
 * when they reopen. Shops already on iruali and applications already sent are never affected.
 */
class SellerApplicationsPauseTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-13 10:00'));
        $this->admin = $this->withRole(User::factory()->create(), 'admin');
    }

    protected function withRole(User $user, string $role): User
    {
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function applications(string $status, string $until = '', string $english = '', string $dhivehi = ''): void
    {
        Setting::set(['seller_applications' => $status, 'seller_applications_until' => $until, 'seller_applications_message_en' => $english, 'seller_applications_message_dv' => $dhivehi]);
    }

    protected function apply(User $user): TestResponse
    {
        return $this->actingAs($user)->post(route('seller.apply.store'), [
            'business_name' => 'Coral Crafts', 'business_description' => 'Handmade lacquer boxes and coconut crafts from Thulhaadhoo.',
            'phone' => '7771234', 'address' => 'Majeedhee Magu', 'home_island_id' => \App\Models\Island::where('code', 'kaafu-male')->value('id'), 'delivery_coverage' => 'all', 'agree_seller_terms' => '1',
        ]);
    }

    protected function seller(bool $approved = true): User
    {
        return $this->withRole(User::factory()->create(['is_seller' => true, 'seller_approved' => $approved, 'onboarding_completed_at' => now()]), 'seller');
    }

    public function test_open_by_default_the_links_lead_to_the_form(): void
    {
        $this->get('/seller/apply')->assertRedirect(route('login'));
        $this->get('/')->assertOk()->assertSee(route('seller.apply'), false)->assertSee('data-footer-sell', false);
        $this->get('/help')->assertOk()->assertSee('id="selling"', false);

        $customer = User::factory()->create();
        $this->actingAs($customer)->get('/seller/apply')->assertOk()->assertSee('action="'.route('seller.apply.store').'"', false)->assertDontSee('data-applications-closed', false);
        $this->apply($customer)->assertRedirect(route('seller.dashboard'));
        $this->assertTrue($customer->fresh()->hasRole('seller'));
    }

    public function test_paused_applications_keep_the_links_but_show_the_message_and_refuse_the_form(): void
    {
        $this->applications('paused', '2026-12-01', 'We are getting our first shops ready.', 'އަޅުގަނޑުމެން ފުރަތަމަ ފިހާރަތައް ތައްޔާރުކުރަނީ.');

        // Guests see why, without signing up first, in either language
        $this->get('/seller/apply')->assertOk()
            ->assertSee('data-applications-closed', false)
            ->assertSee('We are not taking new shops right now')
            ->assertSee('We are getting our first shops ready.')
            ->assertSee('Applications reopen on 1 December 2026.')
            ->assertSee('id="waitlist-email"', false)->assertSee('Tell me when you reopen');
        $this->withSession(['locale' => 'dv'])->get('/seller/apply')->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('މިވަގުތު އައު ފިހާރަތައް ބަލައެއް ނުގަނަމު')
            ->assertSee('އަޅުގަނޑުމެން ފުރަތަމަ ފިހާރަތައް ތައްޔާރުކުރަނީ.');
        $this->withSession(['locale' => 'en']);

        // The links stay
        $this->get('/')->assertOk()->assertSee(route('seller.apply'), false)->assertSee('data-footer-sell', false);

        // Signed in: the page offers their own email; sending the form anyway is refused
        $customer = User::factory()->create(['email' => 'aisha@example.com']);
        $this->actingAs($customer)->get('/seller/apply')->assertOk()
            ->assertSee('We will email aisha@example.com once, when applications reopen.')->assertDontSee('id="waitlist-email"', false);
        $this->apply($customer)->assertRedirect(route('seller.apply'))
            ->assertSessionHas('error', 'iruali is not taking new shops right now, so your application was not sent.');
        $customer->refresh();
        $this->assertFalse($customer->hasRole('seller'));
        $this->assertFalse((bool) $customer->is_seller);

        // Shops already on iruali, and applications already sent, carry on
        $pending = $this->seller(approved: false);
        $this->actingAs($pending)->get('/seller/apply')->assertRedirect(route('seller.dashboard'));
        $this->actingAs($pending)->get(route('seller.dashboard'))->assertOk();
        $this->actingAs($this->seller())->get(route('seller.dashboard'))->assertOk();
    }

    public function test_hidden_applications_take_the_links_off_the_site_but_the_address_still_explains(): void
    {
        $this->applications('hidden');
        $apply = route('seller.apply');

        foreach (['/', '/dv', '/help', '/shop'] as $page) {
            $this->get($page)->assertOk()->assertDontSee($apply, false)->assertDontSee('data-footer-sell', false);
        }
        $this->get('/help')->assertDontSee('id="selling"', false);

        // Anyone who still has the address is told, and the form is refused
        $this->get($apply)->assertOk()->assertSee('data-applications-closed', false)->assertSee('We have paused new shop applications for a while.');
        $customer = User::factory()->create();
        $this->actingAs($customer)->get('/')->assertOk()->assertDontSee($apply, false);
        $this->apply($customer)->assertRedirect($apply)->assertSessionHas('error');
        $this->assertFalse($customer->fresh()->hasRole('seller'));

        // A shop still reaches its Seller Centre from the menu
        $this->actingAs($this->seller())->get('/')->assertOk()->assertSee(route('seller.dashboard'), false);
    }

    public function test_the_waiting_list_is_told_once_when_applications_reopen_on_their_day(): void
    {
        $this->applications('paused', '2026-10-20');

        $this->post(route('seller.waitlist'), ['email' => ' Aisha@Example.com '])->assertRedirect(route('seller.apply'))
            ->assertSessionHas('success', 'Thank you. We will email Aisha@Example.com when iruali takes new shops again.');
        $this->post(route('seller.waitlist'), ['email' => 'aisha@example.com'])->assertRedirect();
        $this->post(route('seller.waitlist'), ['email' => ''])->assertSessionHasErrors('email');
        $this->post(route('seller.waitlist'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->withSession(['locale' => 'dv'])->post(route('seller.waitlist'), ['email' => 'mohamed@example.com'])->assertRedirect();
        $this->withSession(['locale' => 'en']);
        $member = User::factory()->create(['email' => 'shifa@example.com']);
        $this->actingAs($member)->post(route('seller.waitlist'))->assertRedirect();
        $this->actingAs($member)->get('/seller/apply')->assertOk()->assertSee('Thank you. We will email shifa@example.com when iruali takes new shops again.');
        $this->actingAs($member)->get('/seller/apply')->assertOk()->assertSee('You are on the list. We will email you when applications reopen.')->assertDontSee('data-waitlist-form', false);

        $this->assertSame(['aisha@example.com', 'mohamed@example.com', 'shifa@example.com'], SellerWaitlist::orderBy('email')->pluck('email')->all());
        $this->assertSame('dv', SellerWaitlist::where('email', 'mohamed@example.com')->value('locale'));
        $this->assertSame($member->id, SellerWaitlist::where('email', 'shifa@example.com')->value('user_id'));

        // Before the day nothing changes
        $this->artisan('sellers:applications-reopen')->assertSuccessful();
        Notification::assertNothingSent();
        $this->assertSame('paused', Setting::get('seller_applications'));

        // On the day the form is back straight away, and the hourly run stores it and tells everyone once
        $this->travelTo(Carbon::parse('2026-10-20 00:30'));
        $this->actingAs(User::factory()->create())->get('/seller/apply')->assertOk()->assertSee('action="'.route('seller.apply.store').'"', false);
        $this->artisan('sellers:applications-reopen')->expectsOutput('New shop applications are open again.')->expectsOutput('Emailed 3 people on the waiting list.')->assertSuccessful();
        $this->assertSame('open', Setting::get('seller_applications'));
        $this->assertSame('', Setting::get('seller_applications_until'));
        Notification::assertSentOnDemandTimes(SellerApplicationsReopened::class, 3);
        Notification::assertSentOnDemand(SellerApplicationsReopened::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'mohamed@example.com' && $notification->locale === 'dv');
        $this->assertSame(0, SellerWaitlist::waiting()->count());

        $this->artisan('sellers:applications-reopen')->assertSuccessful();
        Notification::assertSentOnDemandTimes(SellerApplicationsReopened::class, 3);

        // While open there is no list to join
        $this->post(route('seller.waitlist'), ['email' => 'late@example.com'])->assertRedirect(route('seller.apply'));
        $this->assertNull(SellerWaitlist::where('email', 'late@example.com')->first());
    }

    public function test_admins_pause_hide_and_reopen_applications_from_the_sellers_page(): void
    {
        $this->actingAs($this->admin)->get(route('admin.sellers'))->assertOk()
            ->assertSee('href="'.route('admin.seller-applications').'"', false)->assertSee('data-applications-status="open"', false);
        $this->actingAs($this->admin)->get(route('admin.seller-applications'))->assertOk()
            ->assertSee('data-application-status="paused"', false)->assertSee('Applications are <span class="font-semibold text-green-700">open</span>.', false);

        $form = ['seller_applications' => 'paused', 'seller_applications_until' => '2026-11-01', 'seller_applications_message_en' => 'Back in November.', 'seller_applications_message_dv' => ''];
        $this->actingAs($this->admin)->put(route('admin.seller-applications.update'), $form)
            ->assertRedirect(route('admin.seller-applications'))->assertSessionHas('success', 'New shop applications are paused: "Sell on iruali" shows your message, until 1 Nov 2026.');
        $this->assertSame(['paused', '2026-11-01', 'Back in November.'], [Setting::get('seller_applications'), Setting::get('seller_applications_until'), Setting::get('seller_applications_message_en')]);
        $this->assertSame(['status' => 'paused', 'reopens' => '2026-11-01'], AuditLog::where('action', 'seller_applications.saved')->latest('id')->first()->changes);

        $this->actingAs($this->admin)->put(route('admin.seller-applications.update'), ['seller_applications_until' => '2026-10-13'] + $form)->assertSessionHasErrors('seller_applications_until');
        $this->actingAs($this->admin)->put(route('admin.seller-applications.update'), ['seller_applications' => 'closed'] + $form)->assertSessionHasErrors('seller_applications');
        $this->actingAs($this->admin)->put(route('admin.seller-applications.update'), ['seller_applications' => 'hidden', 'seller_applications_until' => ''] + $form)->assertSessionHas('success');
        $this->assertSame('hidden', Setting::get('seller_applications'));

        // Opening them again tells the waiting list straight away
        SellerWaitlist::create(['email' => 'aisha@example.com', 'locale' => 'en']);
        SellerWaitlist::create(['email' => 'mohamed@example.com', 'locale' => 'dv']);
        $this->actingAs($this->admin)->get(route('admin.seller-applications'))->assertOk()->assertSee('<span class="font-semibold">2</span> people are waiting', false);
        $this->actingAs($this->admin)->put(route('admin.seller-applications.update'), ['seller_applications' => 'open', 'seller_applications_until' => '2026-11-01'] + $form)
            ->assertSessionHas('success', 'New shop applications are open. 2 people on the waiting list will get an email.');
        $this->assertSame(['open', ''], [Setting::get('seller_applications'), Setting::get('seller_applications_until')]);
        Notification::assertSentOnDemandTimes(SellerApplicationsReopened::class, 2);

        // Admins only
        foreach (['support', 'finance', 'catalogue'] as $role) {
            $staff = $this->withRole(User::factory()->create(), $role);
            $this->actingAs($staff)->get(route('admin.seller-applications'))->assertForbidden();
            $this->actingAs($staff)->put(route('admin.seller-applications.update'), $form)->assertForbidden();
        }
    }

    public function test_the_anonymiser_replaces_waiting_list_emails(): void
    {
        $entry = SellerWaitlist::create(['email' => 'aisha@example.com', 'locale' => 'en']);

        $this->artisan('iruali:anonymise', ['--force' => true])->assertSuccessful();

        $this->assertSame("waiting{$entry->id}@example.test", $entry->fresh()->email);
    }
}
