<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Voucher;
use App\Models\User;
use Illuminate\Support\Facades\Session;

class DiscountService
{
    /**
     * Calculate total discount for a cart
     */
    public function calculateTotalDiscount(Cart $cart): array
    {
        $voucherDiscount = $this->calculateVoucherDiscount($cart);
        $pointsDiscount = $this->calculatePointsDiscount($cart);
        
        $totalDiscount = $voucherDiscount['amount'] + $pointsDiscount['amount'];
        
        return [
            'voucher' => $voucherDiscount,
            'points' => $pointsDiscount,
            'total_discount' => $totalDiscount,
            'final_total' => max(0, $cart->total - $totalDiscount)
        ];
    }

    /**
     * Calculate voucher discount
     */
    public function calculateVoucherDiscount(Cart $cart): array
    {
        $voucherCode = Session::get('voucher_code');
        if (!$voucherCode) {
            return ['amount' => 0, 'voucher' => null];
        }

        $voucher = Voucher::where('code', $voucherCode)
            ->where('is_active', true)
            ->first();

        if (!$voucher) {
            return ['amount' => 0, 'voucher' => null];
        }

        $amount = $this->calculateVoucherAmount($cart, $voucher);

        return [
            'amount' => $amount,
            'voucher' => $voucher,
            'type' => $voucher->type,
            'description' => $this->getVoucherDescription($voucher, $amount)
        ];
    }

    /**
     * Calculate voucher discount amount
     */
    public function calculateVoucherAmount(Cart $cart, Voucher $voucher): float
    {
        if ($voucher->type === 'percent') {
            return round($cart->total * ($voucher->amount / 100), 2);
        }
        
        return min($voucher->amount, $cart->total);
    }

    /**
     * Calculate loyalty points discount
     */
    public function calculatePointsDiscount(Cart $cart): array
    {
        $pointsRedeemed = Session::get('points_redeemed', 0);
        
        return [
            'amount' => $pointsRedeemed,
            'points_redeemed' => $pointsRedeemed,
            'description' => "Loyalty Points Discount ({$pointsRedeemed} points)"
        ];
    }

    /**
     * Validate voucher for application
     */
    public function validateVoucher(string $voucherCode, Cart $cart): array
    {
        $voucher = Voucher::where('code', $voucherCode)
            ->where('is_active', true)
            ->first();

        if (!$voucher) {
            return ['valid' => false, 'message' => __('Invalid or inactive voucher.')];
        }

        if ($voucher->valid_from && now()->lt($voucher->valid_from)) {
            return ['valid' => false, 'message' => __('Voucher not yet valid.')];
        }

        if ($voucher->valid_until && now()->gt($voucher->valid_until)) {
            return ['valid' => false, 'message' => __('Voucher expired.')];
        }

        if ($voucher->max_uses && $voucher->used_count >= $voucher->max_uses) {
            return ['valid' => false, 'message' => __('Voucher usage limit reached.')];
        }

        if ($voucher->min_order && $cart->total < $voucher->min_order) {
            return ['valid' => false, 'message' => __('Order does not meet minimum amount for this voucher.')];
        }

        return ['valid' => true, 'voucher' => $voucher];
    }

    /**
     * Apply voucher to session
     */
    public function applyVoucher(string $voucherCode): bool
    {
        Session::put('voucher_code', $voucherCode);
        return true;
    }

    /**
     * Remove voucher from session
     */
    public function removeVoucher(): bool
    {
        Session::forget('voucher_code');
        return true;
    }

    /**
     * Validate and apply loyalty points
     */
    public function applyLoyaltyPoints(int $points, User $user, Cart $cart): array
    {
        $maxPoints = min($user->loyalty_points, $cart->total);
        
        if ($points > $maxPoints) {
            return ['valid' => false, 'message' => __('Insufficient points or amount exceeds cart total.')];
        }

        if ($points <= 0) {
            return ['valid' => false, 'message' => __('Points must be greater than 0.')];
        }

        Session::put('points_redeemed', $points);
        return ['valid' => true, 'points' => $points];
    }

    /**
     * Remove loyalty points from session
     */
    public function removeLoyaltyPoints(): bool
    {
        Session::forget('points_redeemed');
        return true;
    }

    /**
     * Calculate loyalty points to be earned
     */
    public function calculateLoyaltyPointsEarned(float $orderTotal): int
    {
        // 1 point per 100 MVR spent after all discounts
        return floor($orderTotal / 100);
    }

    /**
     * Process referral rewards
     */
    public function processReferralRewards(User $user): array
    {
        $rewards = ['referrer_points' => 0, 'user_points' => 0];

        // Only process referral rewards after first order
        if ($user->referred_by && $user->orders()->count() === 1) {
            $referrer = $user->referredBy;
            if ($referrer) {
                $referrer->increment('loyalty_points', 100); // 100 points for referrer
                $user->increment('loyalty_points', 50); // 50 points for referred user
                
                $rewards['referrer_points'] = 100;
                $rewards['user_points'] = 50;
            }
        }

        return $rewards;
    }

    /**
     * Get voucher description for display
     */
    private function getVoucherDescription(Voucher $voucher, float $amount): string
    {
        if ($voucher->type === 'percent') {
            return "Voucher Discount ({$voucher->amount}%)";
        }
        
        return "Voucher Discount (ރ{$voucher->amount})";
    }

    /**
     * Get available loyalty points for user
     */
    public function getAvailableLoyaltyPoints(User $user, Cart $cart): int
    {
        return min($user->loyalty_points, $cart->total);
    }

    /**
     * Get discount breakdown for display
     */
    public function getDiscountBreakdown(Cart $cart): array
    {
        $discounts = $this->calculateTotalDiscount($cart);
        
        return [
            'subtotal' => $cart->total,
            'voucher_discount' => $discounts['voucher']['amount'],
            'points_discount' => $discounts['points']['amount'],
            'total_discount' => $discounts['total_discount'],
            'final_total' => $discounts['final_total'],
            'voucher' => $discounts['voucher']['voucher'],
            'points_redeemed' => $discounts['points']['points_redeemed']
        ];
    }
} 