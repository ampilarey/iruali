<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\ShopStaff;
use App\Models\ShopStaffInvitation;
use App\Models\User;
use App\Notifications\ShopStaffInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Concerns\ActsAsShopStaff;
use Tests\TestCase;

/**
 * Seller Centre → Staff: the owner invites by email (a signed link that works for 7 days), the
 * invited person joins with their account or makes one, and used, withdrawn, run-out and
 * wrong-account links say so. Shop owners, other shops' staff and iruali's team cannot join.
 */
class ShopStaffInvitationsTest extends TestCase
{
    use ActsAsShopStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        // Making an account checks the password against pwnedpasswords; keep that off the network
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
    }

    /**
     * Invite through the Staff page as the owner and return the link from the email.
     */
    protected function invite(User $shop, string $email, string $role = 'packer'): string
    {
        $this->actingAs($shop)->post(route('seller.staff.invite'), ['email' => $email, 'role' => $role])
            ->assertRedirect(route('seller.staff'))
            ->assertSessionHas('success');

        $url = '';
        Notification::assertSentOnDemand(ShopStaffInvited::class, function (ShopStaffInvited $notification, array $channels, object $notifiable) use ($email, &$url) {
            if (($notifiable->routes['mail'] ?? null) !== ShopStaffInvitation::normalizeEmail($email)) {
                return false;
            }
            $url = $notification->url; // the latest one sent to this address

            return true;
        });

        return $url;
    }

    /** The next request is a visitor's, not the owner's. */
    protected function signOut(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_the_owner_invites_by_email_with_a_signed_link_that_works_for_seven_days(): void
    {
        $shop = $this->staffedShop('Coral Corner');
        $this->actingAs($shop)->get('/seller/staff')->assertOk()->assertSee('Nobody else can open your Seller Centre yet.');

        $url = $this->invite($shop, 'Hassan@Example.test', 'packer');

        $invitation = ShopStaffInvitation::sole();
        $this->assertSame('hassan@example.test', $invitation->email);
        $this->assertSame('packer', $invitation->role);
        $this->assertSame($shop->id, $invitation->invited_by);
        $this->assertTrue($invitation->isPending());
        $this->assertEqualsWithDelta(now()->addDays(7)->getTimestamp(), $invitation->expires_at->getTimestamp(), 60);
        // The link is signed and carries a token that is only stored hashed
        $this->assertStringContainsString('signature=', $url);
        $token = basename((string) parse_url($url, PHP_URL_PATH));
        $this->assertSame(ShopStaffInvitation::hashToken($token), $invitation->token_hash);
        $this->assertStringNotContainsString($token, (string) json_encode($invitation->getAttributes()));

        Notification::assertSentOnDemand(ShopStaffInvited::class, function (ShopStaffInvited $notification, array $channels, object $notifiable) {
            $mail = $notification->toMail($notifiable);

            return $channels === ['mail'] && str_contains((string) $mail->subject, 'Coral Corner') && $mail->actionUrl === $notification->url;
        });
        $log = AuditLog::where('action', 'shop_staff.invited')->sole();
        $this->assertSame($shop->id, $log->user_id);
        $this->assertSame(['email' => 'hassan@example.test', 'role' => 'packer', 'again' => false, 'invitation_id' => $invitation->id], $log->changes);

        $this->actingAs($shop)->get('/seller/staff')->assertOk()->assertSee('hassan@example.test')->assertSee('Withdraw');
    }

    public function test_someone_new_makes_an_account_from_the_link_and_joins(): void
    {
        $shop = $this->staffedShop('Coral Corner');
        $url = $this->invite($shop, 'newbie@example.test', 'packer');
        $invitation = ShopStaffInvitation::sole();
        $this->signOut();

        $this->get($url)->assertOk()
            ->assertSee('data-invitation-state="create_account"', false)
            ->assertSee('newbie@example.test')
            ->assertSee('Create my account and join');

        // The usual password rules apply
        $this->post($url, ['name' => 'Mohamed Ali', 'password' => 'short', 'password_confirmation' => 'short', 'agree_terms' => 1])->assertSessionHasErrors('password');
        $this->assertFalse(User::where('email', 'newbie@example.test')->exists());

        $this->post($url, ['name' => 'Mohamed Ali', 'password' => 'Lagoon#2026x', 'password_confirmation' => 'Lagoon#2026x', 'agree_terms' => 1])
            ->assertRedirect(route('seller.dashboard'));

        $user = User::where('email', 'newbie@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at); // the link confirmed the address
        $this->assertTrue($user->hasRole('customer'));
        $this->assertFalse($user->hasRole('seller'));
        $member = ShopStaff::where('user_id', $user->id)->sole();
        $this->assertSame([$shop->id, 'packer', $shop->id], [$member->shop_id, $member->role, $member->invited_by]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertSame($user->id, $invitation->fresh()->accepted_by);
        $this->assertSame($user->id, AuditLog::where('action', 'shop_staff.joined')->sole()->user_id);

        // In: a packer starts on the orders page, with the welcome message
        $this->get('/seller/dashboard')->assertRedirect(route('seller.orders'));
        $this->get('/seller/orders')->assertOk()->assertSee('Welcome to Coral Corner');

        // The link is used up
        $this->get($url)->assertOk()->assertSee('data-invitation-state="accepted"', false)->assertSee('This invitation has already been used.')->assertSee('Open the Seller Centre');
        $this->post($url, ['name' => 'Again', 'password' => 'Lagoon#2026x', 'password_confirmation' => 'Lagoon#2026x', 'agree_terms' => 1])->assertRedirect($url);
        $this->assertSame(1, ShopStaff::count());
    }

    public function test_someone_with_an_account_signs_in_with_it_and_accepts(): void
    {
        $shop = $this->staffedShop('Coral Corner');
        $fatima = User::factory()->create(['email' => 'fatima@example.test', 'password' => bcrypt('secret-pass'), 'name' => 'Fatima Ahmed']);
        $url = $this->invite($shop, 'fatima@example.test', 'manager');
        $this->signOut();

        $this->get($url)->assertOk()->assertSee('data-invitation-state="sign_in"', false)->assertSee('There is already an iruali account for fatima@example.test.');
        // Accepting before signing in goes to the sign-in page, which comes back here
        $this->post($url)->assertRedirect(route('login'));
        $this->post('/login', ['email' => 'fatima@example.test', 'password' => 'secret-pass'])->assertRedirect($url);

        $this->get($url)->assertOk()->assertSee('data-invitation-state="accept"', false)->assertSee('Accept and open the Seller Centre');
        $this->post($url)->assertRedirect(route('seller.dashboard'));

        $this->assertSame('manager', ShopStaff::where('user_id', $fatima->id)->value('role'));
        $this->get('/seller/dashboard')->assertOk()->assertSee('Welcome to Coral Corner')->assertSee('Signed in as Fatima Ahmed · Manager');
        $this->assertSame(1, User::where('email', 'fatima@example.test')->count());
    }

    public function test_run_out_withdrawn_replaced_and_tampered_links_say_so(): void
    {
        $shop = $this->staffedShop('Coral Corner');

        // Run out after 7 days
        $late = $this->invite($shop, 'late@example.test');
        $this->signOut();
        $this->travel(8)->days();
        $this->get($late)->assertOk()->assertSee('data-invitation-state="expired"', false)->assertSee('This invitation has run out.');
        $this->post($late, ['name' => 'Late', 'password' => 'Lagoon#2026x', 'password_confirmation' => 'Lagoon#2026x', 'agree_terms' => 1])->assertRedirect($late);
        $this->assertFalse(User::where('email', 'late@example.test')->exists());
        $this->actingAs($shop)->get('/seller/staff')->assertOk()->assertSee('The link ran out on')->assertSee('Send again');
        $this->travelBack();

        // Withdrawn by the owner
        $gone = $this->invite($shop, 'gone@example.test');
        $invitation = ShopStaffInvitation::where('email', 'gone@example.test')->sole();
        $this->actingAs($shop)->delete(route('seller.staff.invitations.revoke', $invitation))->assertRedirect(route('seller.staff'));
        $this->assertNotNull($invitation->fresh()->revoked_at);
        $this->assertSame($shop->id, AuditLog::where('action', 'shop_staff.invitation_revoked')->sole()->user_id);
        $this->signOut();
        $this->get($gone)->assertOk()->assertSee('data-invitation-state="revoked"', false)->assertSee('Coral Corner has withdrawn this invitation.');

        // Sending again replaces the link: the old one stops working
        $first = $this->invite($shop, 'again@example.test');
        $second = $this->invite($shop, 'again@example.test');
        $this->assertNotSame($first, $second);
        $this->assertSame(1, ShopStaffInvitation::where('email', 'again@example.test')->count());
        $this->assertTrue(AuditLog::where('action', 'shop_staff.invited')->get()->contains(fn ($log) => $log->changes['again'] === true));
        $this->signOut();
        $this->get($first)->assertOk()->assertSee('data-invitation-state="invalid"', false)->assertSee('This invitation link does not work any more.');
        $this->get($second)->assertOk()->assertSee('data-invitation-state="create_account"', false);

        // A changed link is refused; a validly signed link with another token is not an invitation
        $this->get(str_replace('signature=', 'signature=0', $second))->assertForbidden();
        $this->post(str_replace('signature=', 'signature=0', $second), ['name' => 'X'])->assertForbidden();
        $again = ShopStaffInvitation::where('email', 'again@example.test')->sole();
        $this->get(URL::signedRoute('shop-invitations.show', ['invitation' => $again->id, 'token' => 'not-the-token']))->assertOk()->assertSee('data-invitation-state="invalid"', false);
        $this->get(URL::signedRoute('shop-invitations.show', ['invitation' => 999999, 'token' => 'whatever']))->assertOk()->assertSee('data-invitation-state="invalid"', false);
    }

    public function test_a_link_opened_in_the_wrong_account_says_so(): void
    {
        $shop = $this->staffedShop('Coral Corner');
        $url = $this->invite($shop, 'right@example.test');
        $wrong = User::factory()->create(['email' => 'wrong@example.test']);

        $this->actingAs($wrong)->get($url)->assertOk()
            ->assertSee('data-invitation-state="wrong_account"', false)
            ->assertSee('This invitation is for right@example.test, but you are signed in as wrong@example.test.')
            ->assertSee('Sign Out');
        $this->actingAs($wrong)->post($url)->assertRedirect($url);
        $this->assertSame(0, ShopStaff::count());
        $this->assertTrue(ShopStaffInvitation::sole()->isPending());
    }

    public function test_shop_owners_other_shops_staff_and_the_iruali_team_cannot_be_invited(): void
    {
        $shop = $this->staffedShop('Coral Corner');
        $otherShop = $this->staffedShop('Island Post', ['email' => 'owner@islandpost.test']);
        $this->staffMember($otherShop, 'packer', ['email' => 'packer@islandpost.test']);
        $this->staffMember($shop, 'packer', ['email' => 'mine@example.test']);
        $applicant = User::factory()->create(['email' => 'applicant@example.test']);
        $applicant->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id); // applied, not approved yet
        $admin = User::factory()->create(['email' => 'support@iruali.test']);
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'support'], ['display_name' => 'Support'])->id);

        $refusals = [
            'owner@islandpost.test' => 'This person has a shop of their own on iruali',
            'applicant@example.test' => 'This person has a shop of their own on iruali',
            'packer@islandpost.test' => 'This person already works for another shop.',
            'support@iruali.test' => 'This account cannot join a shop\'s staff. Invite a different email.', // without saying why
            'mine@example.test' => 'This person is already on your staff.',
            $shop->email => 'That is your own account.',
        ];
        foreach ($refusals as $email => $message) {
            $this->actingAs($shop)->post(route('seller.staff.invite'), ['email' => $email, 'role' => 'manager'])->assertSessionHasErrors('email');
            $this->assertStringContainsString($message, session('errors')->first('email'), $email);
        }
        $this->assertSame(0, ShopStaffInvitation::count());
        Notification::assertNothingSent();
    }

    public function test_someone_who_got_a_shop_or_another_job_after_the_invitation_cannot_join(): void
    {
        $shop = $this->staffedShop('Coral Corner');
        $otherShop = $this->staffedShop('Island Post');

        $url = $this->invite($shop, 'later@example.test');
        $later = User::factory()->create(['email' => 'later@example.test']);
        $later->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $this->actingAs($later)->get($url)->assertOk()->assertSee('data-invitation-state="has_shop"', false)->assertSee('This account has a shop of its own on iruali');
        $this->actingAs($later)->post($url)->assertRedirect($url);

        $busyUrl = $this->invite($shop, 'busy@example.test');
        $busy = $this->staffMember($otherShop, 'manager', ['email' => 'busy@example.test']);
        $this->actingAs($busy)->get($busyUrl)->assertOk()->assertSee('data-invitation-state="staff_elsewhere"', false)->assertSee('This account already works for another shop.');
        $this->actingAs($busy)->post($busyUrl)->assertRedirect($busyUrl);

        $this->assertSame(0, ShopStaff::where('shop_id', $shop->id)->count());
        $this->assertSame($otherShop->id, ShopStaff::where('user_id', $busy->id)->value('shop_id'));

        // A suspended shop's links do not work either
        $closedUrl = $this->invite($shop, 'closed@example.test');
        $shop->forceFill(['status' => 'suspended'])->save();
        $this->signOut();
        $this->get($closedUrl)->assertOk()->assertSee('data-invitation-state="shop_closed"', false);
    }

    public function test_invitations_are_rate_limited_and_a_shop_has_a_ceiling(): void
    {
        config(['shop_staff.invitations_per_hour' => 3, 'shop_staff.max_members' => 5]);
        $shop = $this->staffedShop();

        foreach (['one', 'two', 'three'] as $name) {
            $this->invite($shop, $name.'@example.test');
        }
        $this->actingAs($shop)->post(route('seller.staff.invite'), ['email' => 'four@example.test', 'role' => 'packer'])
            ->assertSessionHasErrors(['email' => 'You have sent a lot of invitations. Please try again in an hour.']);
        $this->assertFalse(ShopStaffInvitation::where('email', 'four@example.test')->exists());

        $this->travel(61)->minutes();
        $this->invite($shop, 'four@example.test');
        $this->staffMember($shop, 'packer');

        // 4 open invitations + 1 member = 5: full, though sending one of them again is fine
        $this->actingAs($shop)->post(route('seller.staff.invite'), ['email' => 'five@example.test', 'role' => 'packer'])
            ->assertSessionHasErrors(['email' => 'Your shop has 5 staff members and invitations already. Remove someone or withdraw an invitation first.']);
        $this->invite($shop, 'one@example.test');
    }

    public function test_the_owner_manages_only_their_own_staff_and_staff_cannot_manage_staff(): void
    {
        $shop = $this->staffedShop('Coral Corner');
        $otherShop = $this->staffedShop('Island Post');
        $manager = $this->staffMember($shop, 'manager', ['name' => 'Aminath Rasheed', 'email' => 'aminath@example.test']);
        $theirs = ShopStaff::where('user_id', $this->staffMember($otherShop, 'packer')->id)->sole();
        $theirInvitation = $this->pendingInvitationFor($otherShop, 'invited@islandpost.test');

        $this->actingAs($shop)->get('/seller/staff')->assertOk()
            ->assertSee('Aminath Rasheed')
            ->assertSee('aminath@example.test')
            ->assertSee('data-staff-member', false)
            ->assertDontSee('invited@islandpost.test');

        $this->actingAs($shop)->put(route('seller.staff.update', $theirs), ['role' => 'manager'])->assertNotFound();
        $this->actingAs($shop)->delete(route('seller.staff.destroy', $theirs))->assertNotFound();
        $this->actingAs($shop)->delete(route('seller.staff.invitations.revoke', $theirInvitation))->assertNotFound();
        $this->actingAs($shop)->put(route('seller.staff.update', ShopStaff::where('user_id', $manager->id)->sole()), ['role' => 'owner'])->assertSessionHasErrors('role');
        $this->assertSame('packer', $theirs->fresh()->role);
        $this->assertTrue($theirInvitation->fresh()->isPending());

        // Staff never manage staff
        $this->actingAs($manager)->get('/seller/staff')->assertForbidden();
        $this->actingAs($manager)->post(route('seller.staff.invite'), ['email' => 'friend@example.test', 'role' => 'manager'])->assertForbidden();
        $this->actingAs($manager)->put(route('seller.staff.settings'), ['staff_require_two_factor' => 0])->assertForbidden();
        $this->assertSame(0, ShopStaffInvitation::where('shop_id', $shop->id)->count());
    }
}
