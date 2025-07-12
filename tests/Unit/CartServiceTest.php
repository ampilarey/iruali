<?php

namespace Tests\Unit;

use App\Models\Cart;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $cartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartService = new CartService();
    }

    public function test_get_or_create_cart_for_authenticated_user()
    {
        $user = User::factory()->create();
        Auth::login($user);

        $cart = $this->cartService->getOrCreateCart();

        $this->assertInstanceOf(Cart::class, $cart);
        $this->assertEquals($user->id, $cart->user_id);
        $this->assertEquals('active', $cart->status);
    }

    public function test_get_or_create_cart_for_guest_user()
    {
        $cart = $this->cartService->getOrCreateCart();

        $this->assertInstanceOf(Cart::class, $cart);
        $this->assertNull($cart->user_id);
        $this->assertNotNull($cart->session_id);
        $this->assertEquals('active', $cart->status);
    }

    public function test_cart_totals_calculation()
    {
        $user = User::factory()->create();
        Auth::login($user);
        
        $cart = $this->cartService->getOrCreateCart();
        
        // Add some items to cart (this would be done through addToCart method)
        // For now, we'll test the totals calculation with an empty cart
        
        $totals = $this->cartService->getCartTotals($cart);
        
        $this->assertArrayHasKey('subtotal', $totals);
        $this->assertArrayHasKey('voucher_discount', $totals);
        $this->assertArrayHasKey('points_discount', $totals);
        $this->assertArrayHasKey('total', $totals);
        $this->assertEquals(0, $totals['subtotal']);
        $this->assertEquals(0, $totals['total']);
    }

    public function test_is_cart_empty()
    {
        $user = User::factory()->create();
        Auth::login($user);
        
        $cart = $this->cartService->getOrCreateCart();
        
        $this->assertTrue($this->cartService->isCartEmpty($cart));
    }

    public function test_cart_summary()
    {
        $user = User::factory()->create();
        Auth::login($user);
        
        $cart = $this->cartService->getOrCreateCart();
        
        $summary = $this->cartService->getCartSummary($cart);
        
        $this->assertArrayHasKey('item_count', $summary);
        $this->assertArrayHasKey('subtotal', $summary);
        $this->assertArrayHasKey('voucher_discount', $summary);
        $this->assertArrayHasKey('points_discount', $summary);
        $this->assertArrayHasKey('total', $summary);
        $this->assertArrayHasKey('voucher', $summary);
    }
} 