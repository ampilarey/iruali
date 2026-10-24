<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CustomerNote;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The staff customer page (tags, notes, phone, merging duplicates, erasing), the customer report,
 * and customers deleting their own account.
 */
class CustomerAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function paidOrder(User $user, float $total, array $extra = []): Order
    {
        return Order::factory()->create($extra + ['user_id' => $user->id, 'total_amount' => $total, 'status' => 'delivered', 'payment_status' => 'paid']);
    }

    protected function address(User $user): void
    {
        DB::table('addresses')->insert(['user_id' => $user->id, 'recipient_name' => $user->name, 'island' => 'Malé', 'house_name_or_street' => 'Blue Villa', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_staff_find_a_customer_and_see_everything_on_one_page(): void
    {
        $customer = User::factory()->create(['name' => 'Mariyam Shifa', 'phone' => '777 1234']);
        $order = $this->paidOrder($customer, 450);
        $support = $this->staff('support');

        $this->actingAs($support)->get(route('admin.users', ['q' => '7771234']))->assertOk()->assertSee('Mariyam Shifa')->assertSee(route('admin.customers.show', $customer));
        $this->get(route('admin.users', ['q' => 'nobody-like-this']))->assertDontSee('Mariyam Shifa');

        $this->get(route('admin.customers.show', $customer))->assertOk()->assertSee($order->order_number)->assertSee('MVR 450.00')
            ->assertDontSee('data-fold="merge"', false)->assertDontSee('data-fold="erase"', false);

        // Support adds tags and notes; merging, erasing and the phone are for admins
        $this->put(route('admin.customers.tags', $customer), ['tags' => 'VIP, business, VIP'])->assertSessionHas('success');
        $this->assertSame(['VIP', 'business'], $customer->fresh()->staff_tags);
        $this->post(route('admin.customers.notes', $customer), ['body' => 'Prefers a call before delivery.'])->assertSessionHas('success');
        $this->assertSame($support->id, CustomerNote::sole()->author_id);
        $this->put(route('admin.customers.phone', $customer), ['phone' => '7779999'])->assertForbidden();
        $this->post(route('admin.customers.erase', $customer), ['reason' => 'x', 'confirm' => 1])->assertForbidden();

        // Finance sees the page but can't write notes
        $this->actingAs($this->staff('finance'))->get(route('admin.customers.show', $customer))->assertOk()->assertSee('Prefers a call before delivery.');
        $this->post(route('admin.customers.notes', $customer), ['body' => 'x'])->assertForbidden();
    }

    public function test_admins_change_a_phone_and_it_must_be_verified_again(): void
    {
        $customer = User::factory()->create(['phone' => '7771234', 'phone_verified_at' => now()]);
        User::factory()->create(['phone' => '7775555']);

        $this->actingAs($this->staff('admin'))->put(route('admin.customers.phone', $customer), ['phone' => '7775555'])->assertSessionHasErrors('phone');
        $this->put(route('admin.customers.phone', $customer), ['phone' => '777 9999'])->assertSessionHas('success');
        $customer->refresh();
        $this->assertSame('7779999', $customer->phone);
        $this->assertNull($customer->phone_verified_at);
        $this->assertTrue(AuditLog::where('action', 'customer.phone_changed')->exists());
    }

    public function test_merging_moves_everything_into_the_account_kept(): void
    {
        $keep = User::factory()->create(['email' => 'keep@example.com', 'loyalty_points' => 100, 'wallet_balance' => 20, 'staff_tags' => ['VIP']]);
        $duplicate = User::factory()->create(['loyalty_points' => 50, 'wallet_balance' => 5, 'staff_tags' => ['business']]);
        $order = $this->paidOrder($duplicate, 300);
        $this->address($duplicate);
        $shared = Product::factory()->create();
        Wishlist::factory()->create(['user_id' => $keep->id, 'product_id' => $shared->id]);
        Wishlist::factory()->create(['user_id' => $duplicate->id, 'product_id' => $shared->id]);
        Wishlist::factory()->create(['user_id' => $duplicate->id]);

        $admin = $this->staff('admin');
        $this->actingAs($admin)->post(route('admin.customers.merge', $duplicate), ['into' => 'keep@example.com'])->assertSessionHasErrors('confirm');
        $this->post(route('admin.customers.merge', $duplicate), ['into' => $admin->email, 'confirm' => 1])->assertSessionHas('error', 'This is a staff account.');

        $this->post(route('admin.customers.merge', $duplicate), ['into' => 'keep@example.com', 'confirm' => 1])
            ->assertRedirect(route('admin.customers.show', $keep));

        $this->assertSame($keep->id, $order->fresh()->user_id);
        $this->assertSame(1, DB::table('addresses')->where('user_id', $keep->id)->count());
        $this->assertSame(2, Wishlist::where('user_id', $keep->id)->count()); // the shared product once
        $keep->refresh();
        $this->assertSame(150, (int) $keep->loyalty_points);
        $this->assertEqualsWithDelta(25, (float) $keep->wallet_balance, 0.001);
        $this->assertSame(['VIP', 'business'], $keep->staff_tags);

        $duplicate->refresh();
        $this->assertFalse((bool) $duplicate->is_active);
        $this->assertSame($keep->id, $duplicate->merged_into_id);
        $this->assertStringEndsWith('@iruali.invalid', $duplicate->email);
        $this->assertTrue(AuditLog::where('action', 'customer.merged')->exists());
    }

    public function test_admins_erase_a_customer_and_orders_stay_without_their_details(): void
    {
        $customer = User::factory()->create(['name' => 'Ahmed Naseem', 'email' => 'ahmed@example.com', 'phone' => '7771234']);
        $order = $this->paidOrder($customer, 200, ['shipping_phone' => '7771234']);
        $this->address($customer);
        $open = $this->paidOrder($customer, 100, ['status' => 'processing']);

        $this->actingAs($this->staff('admin'))->post(route('admin.customers.erase', $customer), ['reason' => 'Asked by email', 'confirm' => 1])
            ->assertSessionHas('error');
        $this->assertNull($customer->fresh()->erased_at);

        $open->update(['status' => 'delivered']);
        $this->post(route('admin.customers.erase', $customer), ['reason' => 'Asked by email', 'confirm' => 1])->assertSessionHas('success');

        $customer->refresh();
        $this->assertNotNull($customer->erased_at);
        $this->assertSame('Erased customer', $customer->name);
        $this->assertNull($customer->phone);
        $this->assertFalse((bool) $customer->is_active);
        $this->assertSame(0, DB::table('addresses')->where('user_id', $customer->id)->count());
        $order->refresh();
        $this->assertSame($customer->id, $order->user_id);
        $this->assertNull($order->shipping_phone);
        $this->assertEqualsWithDelta(200, (float) $order->total_amount, 0.001);
        $audit = AuditLog::where('action', 'customer.erased')->sole();
        $this->assertStringNotContainsString('ahmed@example.com', json_encode($audit->toArray()));
    }

    public function test_the_customer_report_shows_repeat_customers_cohorts_and_a_csv(): void
    {
        $loyal = User::factory()->create(['name' => 'Loyal Buyer']);
        $once = User::factory()->create(['name' => 'One Time']);
        $this->paidOrder($loyal, 1000)->forceFill(['created_at' => now()->subMonth()->startOfMonth()->addDays(2)])->save();
        $this->paidOrder($loyal, 500);
        $this->paidOrder($once, 200);
        $this->paidOrder($once, 999, ['status' => 'cancelled']); // not counted

        $finance = $this->staff('finance');
        $this->actingAs($finance)->get(route('admin.customer-report'))->assertOk()
            ->assertSeeInOrder(['Loyal Buyer', 'One Time'])->assertSee('50%')->assertSee('MVR 850.00');

        $csv = $this->get(route('admin.customer-report.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Loyal Buyer', $csv);
        $this->assertStringContainsString('1500.00', $csv);
        $this->assertStringNotContainsString(',999.00', $csv, 'the cancelled order is not counted');

        $this->actingAs($this->staff('catalogue'))->get(route('admin.customer-report'))->assertForbidden();
    }

    public function test_customers_delete_their_own_account_with_their_password(): void
    {
        $customer = User::factory()->create(['password' => bcrypt('Secret#123')]);
        $this->paidOrder($customer, 100);

        $this->actingAs($customer)->get(route('account'))->assertOk()->assertSee('data-delete-account', false);
        $this->delete(route('account.delete'), ['password' => 'wrong', 'confirm' => 1])->assertSessionHasErrors('password', null, 'deleteAccount');
        $this->assertNull($customer->fresh()->erased_at);

        $this->delete(route('account.delete'), ['password' => 'Secret#123', 'confirm' => 1])->assertRedirect(route('home'));
        $this->assertGuest();
        $this->assertNotNull($customer->fresh()->erased_at);
    }

    public function test_shops_and_customers_with_open_orders_cannot_delete_themselves(): void
    {
        $customer = User::factory()->create(['password' => bcrypt('Secret#123')]);
        $this->paidOrder($customer, 100, ['status' => 'shipped']);
        $this->actingAs($customer)->get(route('account'))->assertSee(__('There is an order still on its way. The account can be deleted once it is delivered or cancelled.'))->assertDontSee('data-delete-account', false);
        $this->delete(route('account.delete'), ['password' => 'Secret#123', 'confirm' => 1]);
        $this->assertAuthenticated();
        $this->assertNull($customer->fresh()->erased_at);

        $seller = User::factory()->create(['password' => bcrypt('Secret#123'), 'is_seller' => true]);
        $this->actingAs($seller)->delete(route('account.delete'), ['password' => 'Secret#123', 'confirm' => 1]);
        $this->assertNull($seller->fresh()->erased_at);
    }
}
