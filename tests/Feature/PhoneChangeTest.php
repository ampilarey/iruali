<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A changed mobile number loses its confirmation on every route that can change it, and a number
 * confirmed on one account can't be put on another (sign-in by text trusts the confirmed holder).
 */
class PhoneChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function verified(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['phone' => '7771111', 'phone_verified_at' => now()], $attributes));
    }

    protected function seller(array $attributes = []): User
    {
        $user = $this->verified(array_merge(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Island Crafts'], $attributes));
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    protected function application(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Island Crafts',
            'business_description' => 'Handmade coir rope, woven mats and lacquer work from Thulhaadhoo.',
            'phone' => '7772222',
            'address' => 'M. Blue House',
            'home_island_id' => \App\Models\Island::where('code', 'kaafu-male')->value('id'),
            'delivery_coverage' => 'all',
            'agree_seller_terms' => '1',
        ], $overrides);
    }

    public function test_the_model_clears_the_confirmation_when_the_number_changes(): void
    {
        $user = $this->verified();

        $user->update(['phone' => '+960 777 1111']); // the same number, typed another way
        $this->assertNotNull($user->fresh()->phone_verified_at);

        $user->update(['phone' => '7772222']);
        $this->assertNull($user->fresh()->phone_verified_at);

        // A save that sets the confirmation itself (a code was just checked) keeps it
        $user->forceFill(['phone' => '7773333', 'phone_verified_at' => now()])->save();
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_api_profile_update_clears_the_confirmation(): void
    {
        $user = $this->verified();
        Sanctum::actingAs($user, ['*']);

        $this->putJson('/api/v1/user', ['phone' => '7772222'])->assertOk();

        $this->assertSame('7772222', $user->fresh()->phone);
        $this->assertNull($user->fresh()->phone_verified_at);
        $this->assertNull(User::findByVerifiedPhone('7772222'));
    }

    public function test_api_refuses_a_number_confirmed_on_another_account(): void
    {
        $victim = $this->verified(['phone' => '7779999']);
        $attacker = $this->verified();
        Sanctum::actingAs($attacker, ['*']);

        $this->putJson('/api/v1/user', ['phone' => '+960 777-9999'])->assertStatus(422);

        $this->assertSame('7771111', $attacker->fresh()->phone);
        $this->assertSame($victim->id, User::findByVerifiedPhone('7779999')->id);
    }

    public function test_api_keeps_the_sign_in_number_of_a_phone_only_account(): void
    {
        $user = $this->verified(['email' => null]);
        Sanctum::actingAs($user, ['*']);

        $this->putJson('/api/v1/user', ['phone' => '7772222'])->assertStatus(422);
        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->assertSame('7771111', $user->fresh()->phone);

        $this->putJson('/api/v1/user', ['phone' => '+9607771111', 'name' => 'Aisha'])->assertOk();
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_api_sign_up_refuses_a_confirmed_number(): void
    {
        $this->verified(['phone' => '7779999']);

        $this->postJson('/api/v1/register', [
            'name' => 'Someone', 'email' => 'someone@example.com', 'phone' => '7779999',
            'password' => 'Str0ng!Pass#2026', 'password_confirmation' => 'Str0ng!Pass#2026',
        ])->assertStatus(422);
    }

    public function test_seller_profile_update_clears_the_confirmation(): void
    {
        $seller = $this->seller();

        $this->actingAs($seller)->put('/seller/profile', [
            'name' => $seller->name, 'business_name' => 'Island Crafts', 'phone' => '7772222',
        ])->assertRedirect(route('seller.profile'));

        $this->assertSame('7772222', $seller->fresh()->phone);
        $this->assertNull($seller->fresh()->phone_verified_at);
    }

    public function test_seller_profile_refuses_a_number_confirmed_on_another_account(): void
    {
        $this->verified(['phone' => '7779999']);
        $seller = $this->seller();

        $this->actingAs($seller)->put('/seller/profile', [
            'name' => $seller->name, 'business_name' => 'Island Crafts', 'phone' => '777 9999',
        ])->assertSessionHasErrors('phone');

        $this->assertSame('7771111', $seller->fresh()->phone);
    }

    public function test_seller_application_clears_the_confirmation_and_refuses_a_taken_number(): void
    {
        $this->verified(['phone' => '7779999']);
        $applicant = $this->verified();

        $this->actingAs($applicant)->post('/seller/apply', $this->application(['phone' => '9607779999']))
            ->assertSessionHasErrors('phone');
        $this->assertSame('7771111', $applicant->fresh()->phone);

        $this->actingAs($applicant)->post('/seller/apply', $this->application());
        $this->assertSame('7772222', $applicant->fresh()->phone);
        $this->assertNull($applicant->fresh()->phone_verified_at);
    }

    public function test_web_profile_refuses_a_number_confirmed_on_another_account(): void
    {
        $this->verified(['phone' => '7779999']);
        $user = $this->verified();

        $this->actingAs($user)->put(route('account.update'), ['name' => 'Aisha', 'phone' => '+960 7779999'])
            ->assertSessionHasErrors('phone');
        $this->assertSame('7771111', $user->fresh()->phone);
    }
}
