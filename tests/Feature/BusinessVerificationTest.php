<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\PayoutBatch;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\SellerPayout;
use App\Models\SellerVerification;
use App\Models\User;
use App\Notifications\BusinessVerificationReviewed;
use App\Services\PayoutService;
use App\Services\SellerVerificationService;
use App\Support\AdminInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Business verification: shops send their registration certificate and the owner's ID card, which
 * are kept on the private disk and shown only to admins and the shop itself; admins approve or reject
 * with a reason (emailed, audited, counted in the inbox); approved shops show "Verified business";
 * and payouts can be made to wait for it.
 */
class BusinessVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();
    }

    protected function seller(string $shopName = 'Island Crafts', array $attributes = []): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => $shopName] + $attributes);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    protected function staff(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function documents(array $overrides = []): array
    {
        return array_merge([
            'business_registration_number' => 'c-0123/2020 ',
            'national_id_number' => 'a123456',
            'registration_certificate' => UploadedFile::fake()->create('certificate.pdf', 300, 'application/pdf'),
            'id_card_front' => UploadedFile::fake()->image('id-front.jpg', 800, 500),
        ], $overrides);
    }

    /** A shop whose documents are already on file, in the given state. */
    protected function verifiedShop(string $status = SellerVerification::APPROVED, string $shopName = 'Island Crafts'): User
    {
        $shop = $this->seller($shopName);
        $verification = app(SellerVerificationService::class)->submit($shop, $this->documents());
        $verification->forceFill(['status' => $status, 'reviewed_at' => $status === 'pending' ? null : now()])->save();

        return $shop->fresh();
    }

    protected function inboxCount(User $admin): ?int
    {
        AdminInbox::forget('__refresh'); // the inbox is worked out once per request

        return collect(AdminInbox::items($admin))->firstWhere('key', 'shops_to_verify')['count'] ?? null;
    }

    public function test_a_shop_sends_its_documents_and_they_are_kept_on_the_private_disk(): void
    {
        $shop = $this->seller();

        $this->actingAs($shop)->get(route('seller.settings.verification'))->assertOk()
            ->assertSee('Get the &quot;Verified business&quot; badge', false)
            ->assertSee('enctype="multipart/form-data"', false);

        $this->put(route('seller.settings.verification.update'), $this->documents())
            ->assertRedirect(route('seller.settings.verification'))
            ->assertSessionHas('success');

        $verification = $shop->fresh()->businessVerification;
        $this->assertSame(SellerVerification::PENDING, $verification->status);
        $this->assertSame('C-0123/2020', $verification->business_registration_number);
        $this->assertSame('A123456', $verification->nationalIdNumber());
        $this->assertNotNull($verification->submitted_at);

        // The ID number is encrypted in the database
        $raw = DB::table('seller_verifications')->value('national_id_number');
        $this->assertStringNotContainsString('A123456', $raw);
        $this->assertSame('A123456', Crypt::decryptString($raw));

        // Files: random names in the shop's folder on the private disk, typed by their content, nothing public
        $this->assertSame('local', SellerVerification::DISK);
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.local.root'));
        $this->assertFalse((bool) config('filesystems.disks.local.serve'));
        foreach (['certificate_path' => 'pdf', 'id_card_path' => 'jpg'] as $column => $extension) {
            $path = $verification->getAttribute($column);
            $this->assertMatchesRegularExpression('#^seller-verifications/'.$shop->id.'/[0-9a-f-]{36}\.'.$extension.'$#', $path);
            Storage::disk('local')->assertExists($path);
            Storage::disk('public')->assertMissing($path);
            $this->get('/storage/'.$path)->assertNotFound();
        }
        $this->assertSame([], Storage::disk('public')->allFiles(), 'nothing is written to the public disk');

        // Hidden from JSON, and never in the audit log
        $this->assertArrayNotHasKey('national_id_number', $verification->toArray());
        $this->assertArrayNotHasKey('certificate_path', $verification->toArray());
        $log = AuditLog::where('action', 'seller.verification_submitted')->sole();
        $this->assertSame($shop->id, $log->subject_id);
        $this->assertStringNotContainsString('A123456', json_encode($log->changes));

        // The status shows on the page, with the number masked
        $this->get(route('seller.settings.verification'))->assertOk()
            ->assertSee('Waiting for iruali to check your documents')
            ->assertSee('A•••456')
            ->assertDontSee('A123456');
    }

    public function test_documents_are_validated(): void
    {
        $this->actingAs($this->seller());
        $url = route('seller.settings.verification.update');

        $this->put($url, [])->assertSessionHasErrors(['business_registration_number', 'national_id_number', 'registration_certificate', 'id_card_front']);
        $this->put($url, $this->documents(['registration_certificate' => UploadedFile::fake()->create('cert.pdf', 10, 'text/html')]))->assertSessionHasErrors('registration_certificate');
        $this->put($url, $this->documents(['registration_certificate' => UploadedFile::fake()->create('cert.pdf', 5121, 'application/pdf')]))->assertSessionHasErrors('registration_certificate');
        $this->put($url, $this->documents(['id_card_front' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf')]))->assertSessionHasErrors('id_card_front');
        $this->put($url, $this->documents(['id_card_front' => UploadedFile::fake()->image('id.png')->size(6000)]))->assertSessionHasErrors('id_card_front');
        $this->put($url, $this->documents(['business_registration_number' => '<script>']))->assertSessionHasErrors('business_registration_number');
        $this->put($url, $this->documents(['national_id_number' => 'A12/34']))->assertSessionHasErrors('national_id_number');
        $this->assertSame(0, SellerVerification::count());

        // A PNG certificate and a PNG ID photo are fine
        $this->put($url, $this->documents([
            'registration_certificate' => UploadedFile::fake()->image('cert.png'),
            'id_card_front' => UploadedFile::fake()->image('id.png'),
        ]))->assertSessionHasNoErrors();
        $this->assertStringEndsWith('.png', SellerVerification::sole()->certificate_path);
    }

    public function test_only_admins_and_the_shop_itself_can_open_the_documents(): void
    {
        $shop = $this->verifiedShop(SellerVerification::PENDING);
        $verification = $shop->businessVerification;
        $adminUrl = route('admin.verifications.document', [$verification, 'id_card']);
        $ownUrl = route('seller.settings.verification.document', 'id_card');

        // Not signed in
        $this->get($adminUrl)->assertRedirect(route('login'));
        $this->get($ownUrl)->assertRedirect(route('login'));

        // A customer, and support and finance staff
        $this->actingAs(User::factory()->create())->get($adminUrl)->assertForbidden();
        $this->get($ownUrl)->assertForbidden();
        $this->actingAs($this->staff('support'))->get($adminUrl)->assertForbidden();
        $this->actingAs($this->staff('finance'))->get($adminUrl)->assertForbidden();

        // Another shop: no way in through the admin route, and its own route only ever serves its own files
        $other = $this->seller('Reef Traders');
        $this->actingAs($other)->get($adminUrl)->assertForbidden();
        $this->get($ownUrl)->assertNotFound();
        app(SellerVerificationService::class)->submit($other, $this->documents());
        $this->get($ownUrl)->assertOk()->assertHeader('Content-Disposition', 'inline; filename=id-card-'.$other->id.'.jpg');

        // An admin, and the shop itself: shown inline, never cached
        $this->actingAs($this->staff('admin'))->get($adminUrl)->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Content-Disposition', 'inline; filename=id-card-'.$shop->id.'.jpg');
        $response = $this->get(route('admin.verifications.document', [$verification, 'certificate']))->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->get(route('admin.verifications.document', [$verification, 'passport']))->assertNotFound();

        $this->actingAs($shop)->get($ownUrl)->assertOk()->assertHeader('Content-Disposition', 'inline; filename=id-card-'.$shop->id.'.jpg');
        $this->get(route('seller.settings.verification.document', 'certificate'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_seller_application_takes_the_documents_optionally(): void
    {
        $application = [
            'business_name' => 'Island Crafts',
            'business_description' => 'Handmade coir rope, woven mats and lacquer work from Thulhaadhoo.',
            'phone' => '7771234', 'address' => 'M. Blue House', 'city' => 'Male', 'state' => 'Kaafu',
            'agree_seller_terms' => '1',
        ];

        $this->actingAs(User::factory()->create())->get(route('seller.apply'))->assertOk()
            ->assertSee('data-apply-verification', false)
            ->assertSee('enctype="multipart/form-data"', false);

        // Left empty: no verification
        $skipper = User::factory()->create();
        $this->actingAs($skipper)->post(route('seller.apply.store'), $application)->assertRedirect(route('seller.dashboard'));
        $this->assertNull($skipper->fresh()->businessVerification);

        // Half filled: all of it is asked for, and the application waits
        $half = User::factory()->create();
        $this->actingAs($half)->post(route('seller.apply.store'), ['phone' => '7771235', 'business_registration_number' => 'C-0123/2020'] + $application)
            ->assertSessionHasErrors(['national_id_number', 'registration_certificate', 'id_card_front']);
        $this->assertFalse($half->fresh()->hasRole('seller'));

        // Complete: sent for checking along with the application
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post(route('seller.apply.store'), ['phone' => '7771236'] + $application + $this->documents())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('seller.dashboard'));
        $verification = $applicant->fresh()->businessVerification;
        $this->assertSame(SellerVerification::PENDING, $verification->status);
        Storage::disk('local')->assertExists($verification->certificate_path);
    }

    public function test_admins_review_the_queue_and_approve_with_an_email_an_audit_entry_and_the_inbox_count(): void
    {
        $admin = $this->staff('admin');
        $shop = $this->verifiedShop(SellerVerification::PENDING);
        $verification = $shop->businessVerification;
        $this->verifiedShop(SellerVerification::APPROVED, 'Already Verified');

        $this->assertSame(1, $this->inboxCount($admin));
        $this->actingAs($admin)->get('/admin/inbox')->assertOk()->assertSee('Shops to verify')->assertSee('data-inbox="shops_to_verify" data-count="1"', false);
        $this->assertNull($this->inboxCount($this->staff('support')), 'support staff cannot open the queue, so they do not see the row');

        $this->actingAs($admin)->get(route('admin.verifications'))->assertOk()
            ->assertSee('Island Crafts')
            ->assertSee('C-0123/2020')
            ->assertSee('A123456')
            ->assertSee(route('admin.verifications.document', [$verification, 'certificate']), false)
            ->assertSee(route('admin.verifications.document', [$verification, 'id_card']), false)
            ->assertSee('<img src="'.route('admin.verifications.document', [$verification, 'id_card']).'"', false)
            ->assertDontSee('Already Verified');
        $this->get(route('admin.verifications', ['status' => 'approved']))->assertOk()->assertSee('Already Verified')->assertDontSee('Island Crafts');
        $this->get(route('admin.sellers'))->assertOk()->assertSee('data-seller-verification="pending"', false);

        $this->post(route('admin.verifications.approve', $verification), ['version' => $verification->submitted_at->getTimestamp()])
            ->assertRedirect()
            ->assertSessionHas('success', 'Island Crafts is now a verified business. We have emailed the shop.');

        $verification->refresh();
        $this->assertSame(SellerVerification::APPROVED, $verification->status);
        $this->assertSame($admin->id, $verification->reviewed_by);
        $this->assertNotNull($verification->reviewed_at);
        $this->assertTrue($shop->fresh()->hasVerifiedBusiness());
        $this->assertSame(0, $this->inboxCount($admin));

        Notification::assertSentTo($shop, BusinessVerificationReviewed::class, fn ($n) => $n->approved === true);
        $mail = (new BusinessVerificationReviewed(true))->toMail($shop);
        $this->assertSame('Your business is verified on iruali', $mail->subject);
        $this->assertStringContainsString('Verified business', implode(' ', $mail->introLines));
        $this->assertSame(route('sellers.show', $shop), $mail->actionUrl);

        $log = AuditLog::where('action', 'seller.verification_approved')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($shop->id, $log->subject_id);
        $this->assertSame('C-0123/2020', $log->changes['registration_number']);

        // Approving again changes nothing
        $this->post(route('admin.verifications.approve', $verification), ['version' => $verification->submitted_at->getTimestamp()])->assertSessionHas('error');
        $this->assertSame(1, AuditLog::where('action', 'seller.verification_approved')->count());
    }

    public function test_admins_reject_with_a_reason_that_is_emailed_and_audited(): void
    {
        $admin = $this->staff('admin');
        $shop = $this->verifiedShop(SellerVerification::PENDING);
        $verification = $shop->businessVerification;
        $version = $verification->submitted_at->getTimestamp();

        $this->actingAs($admin)->post(route('admin.verifications.reject', $verification), ['version' => $version, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->assertTrue($verification->fresh()->isPending());

        $this->post(route('admin.verifications.reject', $verification), ['version' => $version, 'reason' => 'The certificate is for a different business name.'])
            ->assertSessionHas('success');

        $verification->refresh();
        $this->assertSame(SellerVerification::REJECTED, $verification->status);
        $this->assertSame('The certificate is for a different business name.', $verification->rejection_reason);
        $this->assertFalse($shop->fresh()->hasVerifiedBusiness());
        $this->assertSame(0, $this->inboxCount($admin));

        Notification::assertSentTo($shop, BusinessVerificationReviewed::class, fn ($n) => $n->approved === false && $n->reason === 'The certificate is for a different business name.');
        $mail = (new BusinessVerificationReviewed(false, 'The certificate is for a different business name.'))->toMail($shop);
        $this->assertSame('We could not verify your business', $mail->subject);
        $this->assertStringContainsString('Reason: The certificate is for a different business name.', implode(' ', $mail->introLines));
        $this->assertSame(route('seller.settings.verification'), $mail->actionUrl);

        $log = AuditLog::where('action', 'seller.verification_rejected')->sole();
        $this->assertSame($shop->id, $log->subject_id);
        $this->assertSame('The certificate is for a different business name.', $log->changes['reason']);

        // The shop sees why, and can send new documents
        $this->actingAs($shop)->get(route('seller.settings.verification'))->assertOk()
            ->assertSee('We could not verify your business')
            ->assertSee('The certificate is for a different business name.');

        // Only admins decide
        $this->actingAs($shop)->post(route('admin.verifications.approve', $verification), ['version' => $version])->assertForbidden();
        $this->actingAs($this->staff('finance'))->post(route('admin.verifications.approve', $verification), ['version' => $version])->assertForbidden();
        $this->assertTrue($verification->fresh()->isRejected());
    }

    public function test_replacing_documents_puts_the_shop_back_to_pending_and_a_stale_decision_is_refused(): void
    {
        $admin = $this->staff('admin');
        $shop = $this->verifiedShop(SellerVerification::APPROVED);
        $verification = $shop->businessVerification;
        $oldCertificate = $verification->certificate_path;
        $oldVersion = $verification->submitted_at->getTimestamp();

        // Nothing new: stays verified
        $this->actingAs($shop)->put(route('seller.settings.verification.update'), ['business_registration_number' => 'C-0123/2020', 'national_id_number' => 'A123456'])
            ->assertSessionHas('success', 'Nothing changed: your documents are as before.');
        $this->assertTrue($verification->fresh()->isApproved());

        // A new certificate: back to pending, the old file goes, the badge is hidden
        $this->travel(5)->minutes();
        $this->put(route('seller.settings.verification.update'), [
            'business_registration_number' => 'C-0123/2020',
            'registration_certificate' => UploadedFile::fake()->create('new-certificate.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $verification->refresh();
        $this->assertSame(SellerVerification::PENDING, $verification->status);
        $this->assertNull($verification->reviewed_at);
        $this->assertNotSame($oldCertificate, $verification->certificate_path);
        Storage::disk('local')->assertMissing($oldCertificate);
        Storage::disk('local')->assertExists($verification->certificate_path);
        Storage::disk('local')->assertExists($verification->id_card_path);
        $this->assertSame('A123456', $verification->nationalIdNumber(), 'left empty, the ID number is kept');
        $this->assertFalse($shop->fresh()->hasVerifiedBusiness());
        $this->assertSame(['certificate'], AuditLog::where('action', 'seller.verification_submitted')->latest('id')->first()->changes['replaced']);

        // A decision taken on the page shown before the new documents came in is refused
        $this->actingAs($admin)->post(route('admin.verifications.approve', $verification), ['version' => $oldVersion])
            ->assertSessionHas('error', 'This shop sent new documents while you were looking. Check them again before deciding.');
        $this->assertTrue($verification->fresh()->isPending());

        // A changed number counts too, after a rejection
        $verification->forceFill(['status' => SellerVerification::REJECTED, 'rejection_reason' => 'Blurry'])->save();
        $this->actingAs($shop)->put(route('seller.settings.verification.update'), ['business_registration_number' => 'C-0999/2021']);
        $this->assertSame(SellerVerification::PENDING, $verification->fresh()->status);
        $this->assertNull($verification->fresh()->rejection_reason);
    }

    public function test_the_verified_business_badge_shows_only_for_approved_shops(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        $cases = [];
        foreach ([SellerVerification::APPROVED, SellerVerification::PENDING, SellerVerification::REJECTED, null] as $i => $status) {
            $shop = $status ? $this->verifiedShop($status, 'Shop '.$i) : $this->seller('Shop '.$i);
            $product = Product::factory()->create(['seller_id' => $shop->id, 'category_id' => $category->id, 'brand' => 'Reefline', 'is_active' => true, 'stock_quantity' => 5]);
            $cases[] = [$status, $shop, $product];
        }

        foreach ($cases as [$status, $shop, $product]) {
            $approved = $status === SellerVerification::APPROVED;
            foreach ([route('products.show', $product), route('sellers.show', $shop)] as $url) {
                $page = $this->get($url)->assertOk();
                $approved
                    ? $page->assertSee('data-verified-business', false)->assertSee('Verified business')->assertSee('iruali has checked this shop&#039;s business registration.', false)
                    : $page->assertDontSee('data-verified-business', false);
            }
        }

        // Brand page "Sold by" strip: the mark on the approved shop's chip only, and the explanation once
        $brandPage = $this->get(route('brands.show', $cases[0][2]->fresh()->brandModel))->assertOk()
            ->assertSee('data-verified-business-note', false);
        $this->assertSame(1, substr_count($brandPage->getContent(), 'data-verified-business>'));
    }

    public function test_payouts_can_be_made_to_wait_for_a_verified_business(): void
    {
        $admin = $this->staff('admin');
        $payouts = app(PayoutService::class);
        $unverified = $this->verifiedShop(SellerVerification::PENDING, 'Unverified Shop');
        $verified = $this->verifiedShop(SellerVerification::APPROVED, 'Verified Shop');
        $parts = [];
        foreach ([$unverified, $verified] as $shop) {
            $shop->bankAccount()->create(['bank' => 'bml', 'account_name' => $shop->business_name, 'account_number' => '7730000'.str_pad((string) $shop->id, 6, '0', STR_PAD_LEFT)]);
            $parts[$shop->id] = $this->payablePart($shop);
        }

        // Off by default: anyone with bank details can be paid
        $this->assertFalse(SellerVerification::requiredForPayouts());
        $this->assertNull($payouts->payoutBlockedReason($unverified->fresh()));
        $this->actingAs($admin)->get(route('admin.payouts'))->assertOk()
            ->assertSee('Require verified business before payouts')
            ->assertSee('data-payout-rule="off"', false)
            ->assertDontSee('data-payout-held', false);

        // Finance can see the rule but not change it
        $finance = $this->staff('finance');
        $this->actingAs($finance)->get(route('admin.payouts'))->assertOk()->assertSee('data-payout-rule="off"', false)->assertDontSee(route('admin.verifications.payout-rule'), false);
        $this->put(route('admin.verifications.payout-rule'), ['required' => 1])->assertForbidden();
        $this->assertFalse(SellerVerification::requiredForPayouts());

        // An admin turns it on (audited)
        $this->actingAs($admin)->put(route('admin.verifications.payout-rule'), ['required' => 1])->assertRedirect()->assertSessionHas('success');
        $this->assertTrue(SellerVerification::requiredForPayouts());
        $log = AuditLog::where('action', 'payouts.verified_business_rule')->sole();
        $this->assertSame(['from' => false, 'to' => true], $log->changes);
        $this->assertSame($admin->id, $log->user_id);

        // The unverified shop is held, with the reason, everywhere a payout can start
        $reason = $payouts->payoutBlockedReason($unverified->fresh());
        $this->assertStringContainsString('payouts need a verified business', $reason);
        $this->assertNull($payouts->payoutBlockedReason($verified->fresh()));
        $this->assertNull($payouts->createPayout($unverified->fresh(), [$parts[$unverified->id]->id], 'TRF-1', null, $admin));

        $this->get(route('admin.payouts'))->assertOk()
            ->assertSee('data-payout-rule="on"', false)
            ->assertSee('data-payout-held', false)
            ->assertSee('Unverified Shop')
            ->assertSee('Held: business not verified')
            ->assertSee(route('admin.payouts.create', $verified), false)
            ->assertDontSee(route('admin.payouts.create', $unverified), false);
        $this->get(route('admin.payouts.create', $unverified))->assertOk()->assertSee('data-payout-blocked', false)->assertDontSee('Record payout');
        $this->post(route('admin.payouts.store', $unverified), ['parts' => [$parts[$unverified->id]->id], 'reference' => 'TRF-1'])->assertSessionHas('error', $reason);
        $this->get(route('admin.payout-batches.create'))->assertOk()
            ->assertSee('data-payout-blocked', false)
            ->assertSee('name="sellers[]" value="'.$verified->id.'"', false)
            ->assertDontSee('name="sellers[]" value="'.$unverified->id.'"', false);

        // A payout run skips the unverified shop
        $batch = $payouts->createBatch([$unverified->id, $verified->id], $admin);
        $this->assertInstanceOf(PayoutBatch::class, $batch);
        $this->assertSame(1, $batch->count);
        $this->assertSame([$verified->id], SellerPayout::pluck('seller_id')->all());
        $this->assertNull($parts[$unverified->id]->fresh()->payout_id);

        // Once verified, it is paid like everyone else
        $unverified->businessVerification->forceFill(['status' => SellerVerification::APPROVED])->save();
        $this->assertNotNull($payouts->createPayout($unverified->fresh(), [$parts[$unverified->id]->id], 'TRF-2', null, $admin));

        // Turning it off again is audited too
        $this->actingAs($admin)->put(route('admin.verifications.payout-rule'), ['required' => 0])->assertSessionHas('success');
        $this->assertFalse(SellerVerification::requiredForPayouts());
        $this->assertSame(2, AuditLog::where('action', 'payouts.verified_business_rule')->count());
    }

    public function test_the_staging_anonymiser_replaces_id_card_numbers(): void
    {
        $shop = $this->verifiedShop(SellerVerification::PENDING);

        $this->artisan('iruali:anonymise --force')->assertExitCode(0);

        $verification = $shop->businessVerification()->first();
        $this->assertNotSame('A123456', $verification->nationalIdNumber());
        $this->assertMatchesRegularExpression('/^A\d{6}$/', (string) $verification->nationalIdNumber());
    }

    /**
     * A delivered, paid order part worth MVR 90 to the shop.
     */
    protected function payablePart(User $seller): SellerOrder
    {
        $product = Product::factory()->create(['seller_id' => $seller->id, 'price' => 100]);
        $order = Order::factory()->create(['status' => 'delivered', 'payment_status' => 'paid']);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100]); // creates the shop's part

        $part = SellerOrder::where('order_id', $order->id)->where('seller_id', $seller->id)->firstOrFail();
        $part->update(['status' => 'delivered', 'delivered_at' => now(), 'commission_rate' => 10, 'commission_amount' => 10, 'seller_earnings' => 90]);

        return $part;
    }
}
