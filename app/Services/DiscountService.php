<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\Session;

class DiscountService
{
    /**
     * Calculate total discount for a cart.
     *
     * Shop-funded discounts come first (multi-buy offers, then each shop's code: see
     * ShopDiscountService), then the iruali voucher on what is left, then loyalty points.
     */
    public function calculateTotalDiscount(Cart $cart): array
    {
        $shopDiscount = app(ShopDiscountService::class)->forCart($cart);
        $afterShop = round(max(0, (float) $cart->total - $shopDiscount['amount']), 2);
        $afterShop = app(QuoteService::class)->voucherBase($cart, $afterShop); // lines bought on a bulk quote get no voucher
        $voucherDiscount = $this->calculateVoucherDiscount($cart, $afterShop);
        $pointsDiscount = $this->calculatePointsDiscount($cart);

        $totalDiscount = round($shopDiscount['amount'] + $voucherDiscount['amount'] + $pointsDiscount['amount'], 2);

        return [
            'shop' => $shopDiscount,
            'voucher' => $voucherDiscount,
            'points' => $pointsDiscount,
            'total_discount' => $totalDiscount,
            'final_total' => round(max(0, $cart->total - $totalDiscount), 2),
        ];
    }

    /**
     * Calculate voucher discount. $base is what the voucher works on: the goods after the shops'
     * own discounts (worked out here when not given).
     */
    public function calculateVoucherDiscount(Cart $cart, ?float $base = null): array
    {
        // Web checkout keeps the voucher in the session; API clients store it on the cart.
        $voucherCode = Session::get('voucher_code') ?: $cart->voucher_code;
        if (! $voucherCode) {
            return ['amount' => 0, 'voucher' => null];
        }

        $base ??= $this->goodsAfterShopDiscounts($cart);
        $check = $this->validateVoucher($voucherCode, $cart, $base);
        if (! $check['valid']) {
            // Expired, used up or below the minimum since it was applied: drop it quietly
            Session::forget('voucher_code');

            return ['amount' => 0, 'voucher' => null, 'error' => $check['message']];
        }
        $voucher = $check['voucher'];

        $amount = $this->calculateVoucherAmount($cart, $voucher, $base);

        return [
            'amount' => $amount,
            'voucher' => $voucher,
            'type' => $voucher->type,
            'description' => $this->getVoucherDescription($voucher, $amount),
        ];
    }

    /**
     * Calculate voucher discount amount, on $base when given (else the cart's total). Never more
     * than the base, so the total can't go below zero.
     */
    public function calculateVoucherAmount(Cart $cart, Voucher $voucher, ?float $base = null): float
    {
        $base ??= (float) $cart->total;

        if ($voucher->type === 'percent') {
            return round($base * ((float) $voucher->amount / 100), 2);
        }

        return round(min((float) $voucher->amount, $base), 2);
    }

    /**
     * The cart's goods after the shops' own discounts (multi-buy offers and shop codes): what an
     * iruali voucher is worked out on. Lines bought on a bulk quote are left out (QuoteService).
     */
    public function goodsAfterShopDiscounts(Cart $cart): float
    {
        $goods = round(max(0, (float) $cart->total - app(ShopDiscountService::class)->forCart($cart)['amount']), 2);

        return app(QuoteService::class)->voucherBase($cart, $goods); // lines bought on a bulk quote get no voucher
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
            'description' => "Loyalty Points Discount ({$pointsRedeemed} points)",
        ];
    }

    /**
     * Validate voucher for application. The minimum order is checked on $base: the goods after the
     * shops' own discounts (worked out here when not given).
     */
    public function validateVoucher(string $voucherCode, Cart $cart, ?float $base = null): array
    {
        $voucher = Voucher::where('code', $voucherCode)
            ->where('is_active', true)
            ->first();

        if (! $voucher) {
            return ['valid' => false, 'message' => __('Invalid or inactive voucher.')];
        }

        // A voucher issued to one customer (e.g. an abandoned-cart nudge) can't be used by anyone else
        if (! $voucher->usableBy(auth()->id())) {
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

        $base ??= $this->goodsAfterShopDiscounts($cart);
        if ($quoteProblem = app(QuoteService::class)->voucherProblem($cart, $base)) {
            return ['valid' => false, 'message' => $quoteProblem]; // everything in the cart is bought on a quote
        }

        if ($voucher->min_order && $base < (float) $voucher->min_order) {
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
        // 1 point per N MVR spent after all discounts (N is configurable, default 100)
        $spendPerPoint = max(1, (float) Setting::get('loyalty_spend_per_point'));

        return (int) floor($orderTotal / $spendPerPoint);
    }

    /**
     * Process referral rewards
     */
    public function processReferralRewards(User $user, ?\App\Models\Order $order = null): array
    {
        $rewards = ['referrer_points' => 0, 'user_points' => 0];

        // One reward per referred customer, given when their first order is paid.
        if (! $user->referred_by || $user->referral_rewarded_at) {
            return $rewards;
        }

        $referrer = $user->referredBy;
        if ($referrer) {
            $referrerPoints = (int) Setting::get('referral_referrer_points');
            $userPoints = (int) Setting::get('referral_referee_points');

            $points = app(PointsService::class);
            $points->record($referrer, $referrerPoints, 'referral', $order, __('Referral reward: :name placed a first order', ['name' => PointsService::maskName($user->name)]));
            $points->record($user, $userPoints, 'referral', $order, __('Welcome reward for signing up with a referral'));

            $rewards = ['referrer_points' => $referrerPoints, 'user_points' => $userPoints];
        }

        $user->forceFill(['referral_rewarded_at' => now()])->save();

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
            'points_redeemed' => $discounts['points']['points_redeemed'],
        ];
    }
}
