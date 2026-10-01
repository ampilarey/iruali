<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\SellerPayout;
use App\Models\User;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerBankAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function seller(array $attributes = []): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Island Crafts'] + $attributes);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
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

    public function test_seller_adds_a_bml_account_and_sees_it_masked(): void
    {
        $seller = $this->seller();

        $this->actingAs($seller)->get('/seller/settings/bank')->assertOk()->assertSee('Add bank account');

        $this->put('/seller/settings/bank', ['bank' => 'bml', 'account_name' => 'Island Crafts', 'account_number' => '7730 000 123456'])
            ->assertRedirect('/seller/settings/bank');

        $account = $seller->fresh()->bankAccount;
        $this->assertSame('7730000123456', $account->account_number);
        $this->assertSame('MVR', $account->currency);
        $this->assertNull($account->verified_at);

        $this->get('/seller/settings/bank')->assertOk()->assertSee('•••••••••3456')->assertDontSee('7730000123456');
        $this->get('/seller/earnings')->assertOk()->assertSee('•••••••••3456');
        $this->get('/seller/profile')->assertOk()->assertSee('•••••••••3456');
    }

    public function test_account_numbers_are_validated_per_bank(): void
    {
        $this->actingAs($this->seller());

        $this->put('/seller/settings/bank', ['bank' => 'bml', 'account_name' => 'A', 'account_number' => '7731000123456'])->assertSessionHasErrors('account_number');
        $this->put('/seller/settings/bank', ['bank' => 'bml', 'account_name' => 'A', 'account_number' => '773000012345'])->assertSessionHasErrors('account_number');
        $this->put('/seller/settings/bank', ['bank' => 'mib', 'account_name' => 'A', 'account_number' => '7730000123456'])->assertSessionHasErrors('account_number');
        $this->put('/seller/settings/bank', ['bank' => 'mib', 'account_name' => 'A', 'account_number' => '9001234567890123'])->assertSessionHasNoErrors();
        $this->put('/seller/settings/bank', ['bank' => 'other', 'account_name' => 'A', 'account_number' => 'AB-12345'])->assertSessionHasErrors('bank_name_other');
        $this->put('/seller/settings/bank', ['bank' => 'other', 'bank_name_other' => 'HSBC', 'account_name' => 'A', 'account_number' => 'AB-12345'])->assertSessionHasNoErrors();

        $this->assertSame('HSBC', auth()->user()->fresh()->bankAccount->bankName());
    }

    public function test_changing_the_account_clears_verification_and_admin_can_verify(): void
    {
        $seller = $this->seller();
        $seller->bankAccount()->create(['bank' => 'bml', 'account_name' => 'Island Crafts', 'account_number' => '7770000123456', 'verified_at' => now()]);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/payouts')->assertOk()->assertSee('7770000123456')->assertSee('Verified');

        $this->post(route('admin.sellers.bank.verify', $seller))->assertRedirect();
        $this->assertNull($seller->fresh()->bankAccount->verified_at);
        $this->post(route('admin.sellers.bank.verify', $seller));
        $this->assertNotNull($seller->fresh()->bankAccount->verified_at);

        $this->actingAs($seller)->put('/seller/settings/bank', ['bank' => 'bml', 'account_name' => 'Island Crafts', 'account_number' => '7770000654321']);
        $this->assertNull($seller->fresh()->bankAccount->verified_at, 'A changed account needs checking again');

        // Sellers can't touch the verified flag, and only admins can toggle it
        $this->post(route('admin.sellers.bank.verify', $seller))->assertForbidden();
    }

    public function test_a_shop_without_bank_details_cannot_be_paid_out(): void
    {
        $seller = $this->seller();
        $part = $this->payablePart($seller);
        $admin = $this->admin();
        $payouts = app(PayoutService::class);

        $this->assertNotNull($payouts->payoutBlockedReason($seller));
        $this->assertNull($payouts->createPayout($seller, [$part->id], 'TRF-1', null, $admin));
        $this->assertSame(0, SellerPayout::count());

        $this->actingAs($admin)->get('/admin/payouts')->assertOk()->assertSee('No bank account')->assertDontSee('Pay out</a>', false);
        $this->get(route('admin.payouts.create', $seller))->assertOk()->assertSee('has not added a bank account')->assertDontSee('Record payout');
        $this->post(route('admin.payouts.store', $seller), ['parts' => [$part->id], 'reference' => 'TRF-1'])->assertSessionHas('error');

        $seller->bankAccount()->create(['bank' => 'bml', 'account_name' => 'Island Crafts', 'account_number' => '7730000123456']);
        $this->assertNull($payouts->payoutBlockedReason($seller->fresh()));
        $this->post(route('admin.payouts.store', $seller), ['parts' => [$part->id], 'reference' => 'TRF-1'])->assertSessionHas('success');
        $this->assertEquals(90, SellerPayout::sole()->amount);
    }

    public function test_only_sellers_see_the_bank_settings_page(): void
    {
        $this->get('/seller/settings/bank')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/seller/settings/bank')->assertForbidden();
    }
}
