<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\LaunchChecks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The launch checklist on the admin dashboard: the business side of going live, each line red,
 * amber or green with what to do.
 */
class LaunchChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    /** @return array<string, string> check name => status */
    protected function statuses(): array
    {
        return collect((new LaunchChecks)->run())->pluck('status', 'name')->all();
    }

    public function test_an_empty_site_is_not_ready_and_says_what_to_do(): void
    {
        $statuses = $this->statuses();
        $this->assertSame('fail', $statuses['Company details']);
        $this->assertSame('fail', $statuses['Shops and products']);
        $this->assertSame('fail', $statuses['SMS for sign-in codes']);
        $this->assertSame('pass', $statuses['Sample data removed']);
        $this->assertCount(10, $statuses);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('data-launch-checklist', false)->assertSee('to do before launch')
            ->assertSee('Registered business name')->assertSee(route('admin.settings'), false);
    }

    public function test_lines_turn_green_once_done(): void
    {
        Setting::set([
            'company_legal_name' => 'Iruali Pvt Ltd', 'company_registration_no' => 'C-1234/2026', 'company_address' => 'Malé',
            'contact_email' => 'hello@iruali.mv', 'contact_phone' => '+960 777 0000',
            'payment_transfer_enabled' => 1, 'transfer_account_name' => 'Iruali', 'transfer_account_number' => '7730000123456',
        ]);
        $shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'email' => 'real@shop.mv']);
        Product::factory()->create(['seller_id' => $shop->id, 'is_active' => true]);

        $statuses = $this->statuses();
        $this->assertSame('pass', $statuses['Company details']);
        $this->assertSame('pass', $statuses['Shops and products']);
        $this->assertNotSame('fail', $statuses['Payment methods']);

        // Transfer on without an account number is a problem
        Setting::set(['transfer_account_number' => '']);
        $this->assertSame('fail', $this->statuses()['Payment methods']);
    }

    public function test_only_admins_see_it(): void
    {
        $support = User::factory()->create();
        $support->roles()->attach(Role::firstOrCreate(['name' => 'support'], ['display_name' => 'Support'])->id);
        $this->actingAs($support)->get(route('admin.dashboard'))->assertOk()->assertDontSee('data-launch-checklist', false);
    }
}
