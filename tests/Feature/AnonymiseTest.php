<?php

namespace Tests\Feature;

use App\Console\Commands\AnonymiseCommand;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnonymiseTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(string $role): User
    {
        $user = User::factory()->create(['two_factor_enabled' => true, 'two_factor_secret' => encrypt('s')]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    public function test_it_replaces_personal_data_but_keeps_staff(): void
    {
        $admin = $this->staff('admin');
        $finance = $this->staff('finance');
        $customer = User::factory()->create(['name' => 'Real Person', 'email' => 'real@gmail.com', 'phone' => '7771234', 'address' => 'H. Real House', 'two_factor_enabled' => true, 'two_factor_secret' => encrypt('x'), 'two_factor_recovery_codes' => encrypt('[]')]);
        $customer->createToken('phone');
        $admin->createToken('laptop');
        $order = Order::factory()->create(['user_id' => $customer->id, 'shipping_address' => 'H. Real House, Majeedhee Magu', 'shipping_city' => 'Hithadhoo', 'shipping_phone' => '7771234']);
        $part = \App\Models\SellerOrder::create(['order_id' => $order->id, 'seller_id' => $finance->id, 'status' => 'delivered', 'subtotal' => 10, 'commission_rate' => 10, 'commission_amount' => 1, 'seller_earnings' => 9]);
        DB::table('return_requests')->insert(['order_id' => $order->id, 'seller_order_id' => $part->id, 'user_id' => $customer->id, 'status' => 'requested', 'reason' => 'damaged', 'details' => 'My neighbour Ali saw it', 'admin_note' => 'Called her', 'items_value' => 10, 'created_at' => now(), 'updated_at' => now()]);
        NewsletterSubscriber::create(['email' => 'reader@gmail.com', 'locale' => 'en']);
        DB::table('otps')->insert(['email' => 'real@gmail.com', 'code' => '123456', 'type' => 'email', 'purpose' => 'verification', 'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now()]);
        $oldPassword = $customer->password;

        $this->artisan('iruali:anonymise --force')->expectsOutputToContain('Done.')->assertExitCode(0);

        $customer->refresh();
        $this->assertSame(AnonymiseCommand::fakeName($customer->id), $customer->name);
        $this->assertSame("user{$customer->id}@example.test", $customer->email);
        $this->assertSame((string) (7000000 + $customer->id), $customer->phone);
        $this->assertNull($customer->address);
        $this->assertNotSame($oldPassword, $customer->password);
        $this->assertNull($customer->remember_token);
        $this->assertFalse($customer->two_factor_enabled);
        $this->assertNull($customer->two_factor_secret);
        $this->assertNull($customer->two_factor_recovery_codes);
        $this->assertSame(0, $customer->tokens()->count());

        $this->assertSame($admin->email, $admin->fresh()->email);
        $this->assertSame($finance->name, $finance->fresh()->name);
        $this->assertTrue($admin->fresh()->two_factor_enabled);
        $this->assertSame(1, $admin->tokens()->count());

        $order->refresh();
        $this->assertSame('Test address '.$order->id, $order->shipping_address);
        $this->assertSame('Malé', $order->shipping_city);
        $this->assertSame((string) (7000000 + $order->id), $order->shipping_phone);
        $this->assertNull(DB::table('return_requests')->value('details'));
        $this->assertNull(DB::table('return_requests')->value('admin_note'));
        $this->assertStringEndsWith('@example.test', NewsletterSubscriber::first()->email);
        $this->assertSame(0, DB::table('otps')->count());
    }

    public function test_business_details_and_quote_requests_are_replaced(): void
    {
        $customer = \App\Models\User::factory()->create();
        $shop = \App\Models\User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        $product = \App\Models\Product::factory()->create(['seller_id' => $shop->id]);
        \Illuminate\Support\Facades\DB::table('business_profiles')->insert(['user_id' => $customer->id, 'company_name' => 'Sun Island Resort Pvt Ltd', 'tin' => '1012345GST501', 'business_address' => 'M. Sunny Building', 'created_at' => now(), 'updated_at' => now()]);
        $order = \App\Models\Order::factory()->create(['user_id' => $customer->id]);
        $order->forceFill(['buyer_business_name' => 'Sun Island Resort Pvt Ltd', 'buyer_tin' => '1012345GST501', 'buyer_business_address' => 'M. Sunny Building'])->save();
        $quote = new \App\Models\QuoteRequest(['product_id' => $product->id, 'product_name' => 'Rope', 'quantity' => 40, 'delivery_island' => 'Hithadhoo', 'delivery_atoll' => 'Addu', 'needed_by' => today()->addDays(10)->toDateString(), 'notes' => 'Ask for Aisha at reception', 'business_name' => 'Sun Island Resort Pvt Ltd', 'business_tin' => '1012345GST501', 'business_address' => 'M. Sunny Building']);
        $quote->forceFill(['customer_id' => $customer->id, 'seller_id' => $shop->id, 'status' => 'new'])->save();
        \Illuminate\Support\Facades\DB::table('quote_messages')->insert(['quote_request_id' => $quote->id, 'sender_id' => $customer->id, 'sender_role' => 'customer', 'body' => 'Call me on 7771234', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('iruali:anonymise --force')->assertExitCode(0);

        $this->assertDatabaseMissing('business_profiles', ['company_name' => 'Sun Island Resort Pvt Ltd']);
        $this->assertDatabaseMissing('business_profiles', ['tin' => '1012345GST501']);
        $this->assertDatabaseMissing('orders', ['buyer_business_name' => 'Sun Island Resort Pvt Ltd']);
        $this->assertDatabaseMissing('orders', ['buyer_tin' => '1012345GST501']);
        $this->assertDatabaseMissing('quote_requests', ['business_name' => 'Sun Island Resort Pvt Ltd']);
        $this->assertNull($quote->fresh()->notes);
        $this->assertDatabaseMissing('quote_messages', ['body' => 'Call me on 7771234']);
    }

    /**
     * Columns that look personal but are not, or that the command doesn't need to touch, with why.
     * A new column whose name looks personal must be added to AnonymiseCommand::COVERED or here.
     */
    protected const NOT_PERSONAL = [
        // Shop and catalogue text that is public anyway
        'brands.name', 'brands.name_dv', 'campaigns.name', 'categories.name', 'islands.name', 'multibuy_offers.name',
        'products.name', 'products.name_dv', 'product_variants.name', 'order_items.variant_name', 'quote_requests.product_name',
        'quote_requests.variant_name', 'product_reviews.comment', 'users.business_name', 'users.business_description',
        'users.holiday_message', 'users.delivery_notes', 'users.payout_bank_name', 'seller_delivery_settings.pickup_address',
        'seller_orders.pickup_address', 'seller_orders.tracking_note', 'sms_campaigns.name', 'sms_campaigns.message',
        'social_posts.message', 'newsletter_issues.content', 'banners.button_text', 'campaigns.cta_text',
        // A shop's own tax registration is printed on its invoices; iruali's on every order
        'shop_tax_profiles.registered_name', 'shop_tax_profiles.tin', 'shop_tax_profiles.business_address', 'orders.gst_platform_tin',
        // System names, codes and notes written by the team, not by or about a customer
        'roles.name', 'roles.display_name', 'permissions.name', 'permissions.display_name', 'job_batches.name',
        'personal_access_tokens.name', 'error_events.message', 'payout_batches.notes', 'seller_payouts.note',
        'seller_adjustments.reason', 'orders.refund_reason', 'refunds.reason', 'return_requests.reason', 'item_requests.declined_reason',
        'item_requests.quote_message', 'quote_requests.decline_reason', 'quote_requests.shop_message',
        'product_change_requests.rejection_reason', 'seller_verifications.rejection_reason', 'wallet_transactions.note',
        'points_transactions.note', 'bank_statement_lines.description', 'expenses.description', 'shop_discount_codes.applies_to',
        'seller_bank_accounts.bank_name_other', 'item_requests.title', 'product_reviews.title', 'banners.title',
        'categories.meta_title', 'products.meta_title', 'products.preorder_note', 'seller_payouts.invoice_details',
        // Counts and years, not people
        'newsletter_issues.recipients_count', 'sms_campaigns.recipients_count', 'users.birthday_offer_year',
        // Kept as-is: hashed or already random, or files that are never copied to the staging site
        'shop_staff_invitations.token_hash', 'orders.guest_token', 'push_subscriptions.auth_token',
    ];

    public function test_every_column_that_looks_personal_is_covered_or_explained(): void
    {
        $pattern = '/(^|_)(name|phone|email|address|account_number|message|body|tin|ip|ip_address|comment|note|notes|details|reason|title|to)$|birth|recipient|guest_|gift_receiver|national_id/';
        $covered = array_merge(AnonymiseCommand::COVERED, self::NOT_PERSONAL);
        $deletedTables = collect(AnonymiseCommand::COVERED)->filter(fn ($c) => str_ends_with($c, '.*'))->map(fn ($c) => substr($c, 0, -2))->all();

        $missing = [];
        foreach (array_unique(\Illuminate\Support\Facades\Schema::getTableListing(DB::connection()->getDatabaseName(), false)) as $table) {
            if (in_array($table, ['migrations', 'cache', 'cache_locks', 'jobs', 'failed_jobs'], true) || in_array($table, $deletedTables, true)) {
                continue;
            }
            foreach (\Illuminate\Support\Facades\Schema::getColumnListing($table) as $column) {
                if (preg_match($pattern, $column) && ! in_array("{$table}.{$column}", $covered, true)) {
                    $missing[] = "{$table}.{$column}";
                }
            }
        }

        $this->assertSame([], $missing, 'Personal-looking columns the anonymiser neither covers nor explains: add each to AnonymiseCommand::COVERED (and handle it) or to NOT_PERSONAL here');
    }

    public function test_addresses_bank_accounts_texts_messages_and_guest_details_are_replaced(): void
    {
        $customer = User::factory()->create(['birth_date' => '1990-01-01']);
        DB::table('addresses')->insert(['user_id' => $customer->id, 'recipient_name' => 'Real Person', 'phone' => '7771234', 'house_name_or_street' => 'M. Real House', 'island' => 'Malé', 'island_id' => \App\Models\Island::query()->value('id'), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('seller_bank_accounts')->insert(['user_id' => $customer->id, 'bank' => 'bml', 'account_name' => 'Real Person', 'account_number' => '7730000999999', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sms_messages')->insert(['to' => '9601234', 'message' => 'Your code is 123456', 'type' => 'login_code', 'status' => 'sent', 'created_at' => now()]);
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $customer->id, 'ip_address' => '1.2.3.4', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time()]);
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'real@person.mv', 'guest_name' => 'Real Person', 'gift_receiver_name' => 'Real Friend', 'gift_receiver_phone' => '7779999', 'gift_message' => 'Happy birthday Ali']);

        $this->artisan('iruali:anonymise', ['--force' => true])->assertSuccessful();

        $dump = json_encode([DB::table('addresses')->get(), DB::table('seller_bank_accounts')->get(), DB::table('sms_messages')->get(), DB::table('orders')->where('id', $order->id)->get(), DB::table('users')->where('id', $customer->id)->get()]);
        foreach (['Real Person', 'M. Real House', '7730000999999', '123456', '9601234', 'real@person.mv', 'Real Friend', '7779999', 'Happy birthday Ali', '1990-01-01'] as $real) {
            $this->assertStringNotContainsString($real, $dump);
        }
        $this->assertSame(0, DB::table('sessions')->count());
    }

    public function test_without_force_it_is_a_dry_run(): void
    {
        $customer = User::factory()->create(['email' => 'keep@gmail.com']);

        $this->artisan('iruali:anonymise')->expectsOutputToContain('Dry run')->assertExitCode(1);

        $this->assertSame('keep@gmail.com', $customer->fresh()->email);
    }

    public function test_it_refuses_on_production_without_the_extra_flag(): void
    {
        $customer = User::factory()->create(['email' => 'keep@gmail.com']);
        app()->detectEnvironment(fn () => 'production');

        try {
            $this->artisan('iruali:anonymise --force')->expectsOutputToContain('Refusing')->assertExitCode(1);
            $this->assertSame('keep@gmail.com', $customer->fresh()->email);

            $this->artisan('iruali:anonymise --force --i-know-this-is-production')->assertExitCode(0);
            $this->assertSame("user{$customer->id}@example.test", $customer->fresh()->email);
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_fake_names_are_stable_and_maldivian(): void
    {
        $this->assertSame(AnonymiseCommand::fakeName(5), AnonymiseCommand::fakeName(5));
        $this->assertNotSame(AnonymiseCommand::fakeName(5), AnonymiseCommand::fakeName(6));
        $this->assertMatchesRegularExpression('/^[A-Z][a-z]+ [A-Z][a-z]+$/', AnonymiseCommand::fakeName(123));
    }
}
