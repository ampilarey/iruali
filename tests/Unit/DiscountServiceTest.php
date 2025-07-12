<?php

namespace Tests\Unit;

use App\Models\Cart;
use App\Models\User;
use App\Models\Voucher;
use App\Services\DiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class DiscountServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $discountService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->discountService = new DiscountService();
    }

    public function test_calculate_voucher_discount_percentage()
    {
        $cart = Cart::factory()->create();
        $voucher = Voucher::factory()->create([
            'type' => 'percent',
            'amount' => 10, // 10% discount
            'is_active' => true
        ]);

        // Mock cart total to 100
        $cart->items()->create([
            'product_id' => 1,
            'quantity' => 1,
            'price' => 100
        ]);

        $discount = $this->discountService->calculateVoucherAmount($cart, $voucher);
        
        $this->assertEquals(10.0, $discount); // 10% of 100 = 10
    }

    public function test_calculate_voucher_discount_fixed()
    {
        $cart = Cart::factory()->create();
        $voucher = Voucher::factory()->create([
            'type' => 'fixed',
            'amount' => 15, // 15 MVR discount
            'is_active' => true
        ]);

        // Mock cart total to 100
        $cart->items()->create([
            'product_id' => 1,
            'quantity' => 1,
            'price' => 100
        ]);

        $discount = $this->discountService->calculateVoucherAmount($cart, $voucher);
        
        $this->assertEquals(15.0, $discount); // Fixed 15 MVR
    }

    public function test_calculate_voucher_discount_fixed_exceeds_total()
    {
        $cart = Cart::factory()->create();
        $voucher = Voucher::factory()->create([
            'type' => 'fixed',
            'amount' => 150, // 150 MVR discount
            'is_active' => true
        ]);

        // Mock cart total to 100
        $cart->items()->create([
            'product_id' => 1,
            'quantity' => 1,
            'price' => 100
        ]);

        $discount = $this->discountService->calculateVoucherAmount($cart, $voucher);
        
        $this->assertEquals(100.0, $discount); // Should not exceed cart total
    }

    public function test_calculate_loyalty_points_earned()
    {
        $points = $this->discountService->calculateLoyaltyPointsEarned(250.0);
        $this->assertEquals(2, $points); // 250 / 100 = 2 points

        $points = $this->discountService->calculateLoyaltyPointsEarned(99.0);
        $this->assertEquals(0, $points); // 99 / 100 = 0 points (floor)

        $points = $this->discountService->calculateLoyaltyPointsEarned(100.0);
        $this->assertEquals(1, $points); // 100 / 100 = 1 point
    }

    public function test_validate_voucher_valid()
    {
        $cart = Cart::factory()->create();
        $voucher = Voucher::factory()->create([
            'code' => 'TEST10',
            'is_active' => true,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
            'max_uses' => 100,
            'used_count' => 50,
            'min_order' => 50
        ]);

        // Mock cart total to 100
        $cart->items()->create([
            'product_id' => 1,
            'quantity' => 1,
            'price' => 100
        ]);

        $result = $this->discountService->validateVoucher('TEST10', $cart);
        
        $this->assertTrue($result['valid']);
        $this->assertInstanceOf(Voucher::class, $result['voucher']);
    }

    public function test_validate_voucher_invalid_code()
    {
        $cart = Cart::factory()->create();
        
        $result = $this->discountService->validateVoucher('INVALID', $cart);
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Invalid', $result['message']);
    }

    public function test_validate_voucher_inactive()
    {
        $cart = Cart::factory()->create();
        Voucher::factory()->create([
            'code' => 'TEST10',
            'is_active' => false
        ]);

        $result = $this->discountService->validateVoucher('TEST10', $cart);
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Invalid', $result['message']);
    }

    public function test_validate_voucher_expired()
    {
        $cart = Cart::factory()->create();
        Voucher::factory()->create([
            'code' => 'TEST10',
            'is_active' => true,
            'valid_until' => now()->subDay()
        ]);

        $result = $this->discountService->validateVoucher('TEST10', $cart);
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('expired', $result['message']);
    }

    public function test_validate_voucher_usage_limit_reached()
    {
        $cart = Cart::factory()->create();
        Voucher::factory()->create([
            'code' => 'TEST10',
            'is_active' => true,
            'max_uses' => 10,
            'used_count' => 10
        ]);

        $result = $this->discountService->validateVoucher('TEST10', $cart);
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('usage limit', $result['message']);
    }

    public function test_validate_voucher_min_order_not_met()
    {
        $cart = Cart::factory()->create();
        Voucher::factory()->create([
            'code' => 'TEST10',
            'is_active' => true,
            'min_order' => 100
        ]);

        // Mock cart total to 50
        $cart->items()->create([
            'product_id' => 1,
            'quantity' => 1,
            'price' => 50
        ]);

        $result = $this->discountService->validateVoucher('TEST10', $cart);
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('minimum amount', $result['message']);
    }

    public function test_apply_and_remove_voucher()
    {
        $this->discountService->applyVoucher('TEST10');
        $this->assertEquals('TEST10', Session::get('voucher_code'));

        $this->discountService->removeVoucher();
        $this->assertNull(Session::get('voucher_code'));
    }

    public function test_apply_and_remove_loyalty_points()
    {
        $this->discountService->removeLoyaltyPoints(); // Clear any existing
        $this->assertNull(Session::get('points_redeemed'));

        $user = User::factory()->create(['loyalty_points' => 100]);
        $cart = Cart::factory()->create();
        $cart->items()->create([
            'product_id' => 1,
            'quantity' => 1,
            'price' => 100
        ]);

        $result = $this->discountService->applyLoyaltyPoints(50, $user, $cart);
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(50, Session::get('points_redeemed'));

        $this->discountService->removeLoyaltyPoints();
        $this->assertNull(Session::get('points_redeemed'));
    }

    public function test_apply_loyalty_points_exceeds_available()
    {
        $user = User::factory()->create(['loyalty_points' => 50]);
        $cart = Cart::factory()->create();
        $cart->items()->create([
            'product_id' => 1,
            'quantity' => 1,
            'price' => 100
        ]);

        $result = $this->discountService->applyLoyaltyPoints(100, $user, $cart);
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Insufficient', $result['message']);
    }

    public function test_get_available_loyalty_points()
    {
        $user = User::factory()->create(['loyalty_points' => 100]);
        $cart = Cart::factory()->create();
        $cart->items()->create([
            'product_id' => 1,
            'quantity' => 1,
            'price' => 50
        ]);

        $available = $this->discountService->getAvailableLoyaltyPoints($user, $cart);
        
        $this->assertEquals(50, $available); // Should be limited by cart total
    }
} 