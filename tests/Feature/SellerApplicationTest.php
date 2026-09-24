<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function validApplication(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Island Crafts',
            'business_description' => 'Handmade coir rope, woven mats and lacquer work from Thulhaadhoo.',
            'phone' => '7771234',
            'address' => 'M. Blue House',
            'city' => 'Male',
            'state' => 'Kaafu',
            'agree_seller_terms' => '1',
        ], $overrides);
    }

    public function test_guests_are_sent_to_login_and_returned_to_the_form(): void
    {
        $this->get('/seller/apply')->assertRedirect('/login');

        $user = User::factory()->create([
            'password' => bcrypt('Secret#123'),
            'status' => 'active',
            'is_active' => true,
            'email_verified' => true,
            'phone_verified' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret#123'])
            ->assertRedirect('/seller/apply');
    }

    public function test_customer_can_apply_and_becomes_pending_seller(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/seller/apply')->assertOk()->assertSee('Open your shop');

        $this->post('/seller/apply', $this->validApplication())
            ->assertRedirect(route('seller.dashboard'));

        $user->refresh();
        $this->assertTrue($user->hasRole('seller'));
        $this->assertTrue($user->is_seller);
        $this->assertFalse($user->seller_approved);
        $this->assertNotNull($user->seller_applied_at);
        $this->assertSame('Island Crafts', $user->business_name);
        $this->assertSame('7771234', $user->phone);

        // Immediately has Seller Centre access, with the pending banner
        $this->get('/seller/dashboard')->assertOk()->assertSee('awaiting admin approval');
    }

    public function test_application_is_validated(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/seller/apply', $this->validApplication([
                'business_name' => '',
                'business_description' => 'too short',
                'agree_seller_terms' => null,
            ]))
            ->assertSessionHasErrors(['business_name', 'business_description', 'agree_seller_terms']);
    }

    public function test_existing_sellers_are_redirected_to_seller_centre(): void
    {
        $seller = User::factory()->create();
        $seller->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        $this->actingAs($seller)->get('/seller/apply')->assertRedirect(route('seller.dashboard'));
    }

    public function test_admin_sees_pending_application_and_can_approve_or_reject(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post('/seller/apply', $this->validApplication());

        $this->actingAs($admin)
            ->get('/admin/sellers')
            ->assertOk()
            ->assertSee('Island Crafts')
            ->assertSee('Pending');

        $this->post(route('admin.sellers.approve', $applicant->id));
        $this->assertTrue($applicant->fresh()->seller_approved);

        $this->post(route('admin.sellers.reject', $applicant->id))->assertRedirect();
        $applicant->refresh();
        $this->assertFalse($applicant->hasRole('seller'));
        $this->assertFalse($applicant->is_seller);

        $this->actingAs($applicant)->get('/seller/dashboard')->assertForbidden();
    }

    public function test_menu_shows_sell_link_for_customers(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertSee('Sell on iruali')
            ->assertSee(route('seller.apply'), false);
    }
}
