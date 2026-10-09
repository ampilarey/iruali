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
