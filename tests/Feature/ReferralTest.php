<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        // Seed roles, permissions, users, products, etc.
        $this->artisan('db:seed');
    }

    public function test_referral_registration_sets_fields()
    {
        $referrer = User::factory()->create([
            'loyalty_points' => 0,
            'referral_code' => 'REF12345',
        ]);
        $response = $this->post('/register', [
            'name' => 'Referee User',
            'email' => 'referee@example.com',
            'phone' => '7770001',
            'password' => 'password',
            'password_confirmation' => 'password',
            'address' => 'Somewhere',
            'city' => 'Male',
            'state' => 'Male',
            'country' => 'Maldives',
            'postal_code' => '20001',
            'date_of_birth' => '2000-01-01',
            'gender' => 'male',
            'referral_code' => 'REF12345',
            'agree_terms' => 'on',
        ]);
        $response->assertRedirect(route('verification.notice'));
        $referee = User::where('email', 'referee@example.com')->first();
        $this->assertNotNull($referee);
        $this->assertEquals($referrer->id, $referee->referred_by);
        $this->assertNotNull($referee->referral_code);
    }

    public function test_first_order_triggers_referral_reward()
    {
        $referrer = User::factory()->create(['loyalty_points' => 0, 'referral_code' => 'REF12345']);
        $referee = User::factory()->create(['loyalty_points' => 0, 'referred_by' => $referrer->id, 'referral_code' => 'REF54321']);
        $this->actingAs($referee);
        $product = Product::factory()->create(['price' => 1000]);
        $cart = Cart::factory()->create(['user_id' => $referee->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $response = $this->post('/orders', [
            'shipping_address' => 'Somewhere',
            'shipping_city' => 'Male',
            'shipping_state' => 'Male',
            'shipping_zip' => '20001',
            'shipping_country' => 'Maldives',
        ]);
        $response->assertRedirect();
        $referrer->refresh();
        $referee->refresh();
        $this->assertEquals(100, $referrer->loyalty_points);
        $this->assertEquals(60, $referee->loyalty_points); // 50 referral + 10 order points
    }

    public function test_second_order_does_not_trigger_referral_reward()
    {
        $referrer = User::factory()->create(['loyalty_points' => 0, 'referral_code' => 'REF12345']);
        $referee = User::factory()->create(['loyalty_points' => 0, 'referred_by' => $referrer->id, 'referral_code' => 'REF54321']);
        $this->actingAs($referee);
        $product = Product::factory()->create(['price' => 1000]);
        $cart = Cart::factory()->create(['user_id' => $referee->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        // First order
        $this->post('/orders', [
            'shipping_address' => 'Somewhere',
            'shipping_city' => 'Male',
            'shipping_state' => 'Male',
            'shipping_zip' => '20001',
            'shipping_country' => 'Maldives',
        ]);
        $referrer->refresh();
        $referee->refresh();
        // Second order
        $cart2 = Cart::factory()->create(['user_id' => $referee->id]);
        CartItem::factory()->create(['cart_id' => $cart2->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->post('/orders', [
            'shipping_address' => 'Somewhere',
            'shipping_city' => 'Male',
            'shipping_state' => 'Male',
            'shipping_zip' => '20001',
            'shipping_country' => 'Maldives',
        ]);
        $referrer->refresh();
        $referee->refresh();
        $this->assertEquals(100, $referrer->loyalty_points); // No additional points
        $this->assertEquals(70, $referee->loyalty_points); // Only order points added
    }

    public function test_registration_without_referral_gives_no_reward()
    {
        $response = $this->post('/register', [
            'name' => 'No Referral',
            'email' => 'noref@example.com',
            'phone' => '7770002',
            'password' => 'password',
            'password_confirmation' => 'password',
            'address' => 'Somewhere',
            'city' => 'Male',
            'state' => 'Male',
            'country' => 'Maldives',
            'postal_code' => '20001',
            'date_of_birth' => '2000-01-01',
            'gender' => 'male',
            'agree_terms' => 'on',
        ]);
        $response->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'noref@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->referred_by);
        $this->assertNotNull($user->referral_code);
        // Place first order
        $this->actingAs($user);
        $product = Product::factory()->create(['price' => 1000]);
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->post('/orders', [
            'shipping_address' => 'Somewhere',
            'shipping_city' => 'Male',
            'shipping_state' => 'Male',
            'shipping_zip' => '20001',
            'shipping_country' => 'Maldives',
        ]);
        $user->refresh();
        $this->assertEquals(10, $user->loyalty_points); // Only order points
    }
} 