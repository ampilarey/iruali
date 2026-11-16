<?php

namespace Tests\Unit;

use App\Models\Cart;
use App\Models\User;
use App\Services\CartService;
use App\Services\DiscountService;
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
        $this->cartService = new CartService;
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

    public function test_cart_totals_come_from_the_discount_service()
    {
        $user = User::factory()->create();
        Auth::login($user);

        $cart = $this->cartService->getOrCreateCart();

        // The cart page and checkout take their discounts from DiscountService (shop deals first,
        // then the iruali voucher); an empty cart has none
        $discounts = app(DiscountService::class)->calculateTotalDiscount($cart);
        $this->assertEquals(0, $discounts['voucher']['amount']);
        $this->assertEquals(0, $discounts['shop']['amount']);

        $summary = $this->cartService->getCartSummary($cart);
        $this->assertEquals(0, $summary['subtotal']);
        $this->assertEquals(0, $summary['total']);
        $this->assertSame($discounts['voucher']['amount'], $summary['voucher_discount']);
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
