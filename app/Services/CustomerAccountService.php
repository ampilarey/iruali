<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Staff tools on customer accounts: merging a duplicate account into another, and erasing a
 * customer's personal data (on their request, or by staff). Only plain customer accounts: never
 * staff, shops or shop staff. Orders stay for the books, with the customer's details removed.
 */
class CustomerAccountService
{
    /** Tables where a row simply belongs to the customer: [table, column]. */
    public const OWNED = [
        ['orders', 'user_id'], ['addresses', 'user_id'], ['conversations', 'customer_id'], ['disputes', 'customer_id'],
        ['gift_cards', 'purchaser_id'], ['gift_cards', 'redeemed_by'], ['messages', 'sender_id'],
        ['points_transactions', 'user_id'], ['wallet_transactions', 'user_id'], ['product_questions', 'user_id'],
        ['product_reviews', 'user_id'], ['quote_messages', 'sender_id'], ['quote_requests', 'customer_id'],
        ['return_requests', 'user_id'], ['shop_discount_redemptions', 'user_id'], ['stock_alerts', 'user_id'],
        ['vouchers', 'user_id'], ['newsletter_deliveries', 'user_id'], ['social_accounts', 'user_id'],
        ['customer_notes', 'user_id'],
    ];

    /** Tables with one row per customer and thing: [table, column, other key]. */
    public const OWNED_UNIQUE = [
        ['wishlists', 'user_id', 'product_id'], ['saved_items', 'user_id', 'product_id'],
        ['brand_follows', 'user_id', 'brand_id'], ['review_votes', 'user_id', 'product_review_id'],
    ];

    /**
     * Why this account can't be merged or erased, or null.
     */
    public function notACustomer(User $user): ?string
    {
        return match (true) {
            $user->isStaff() => 'This is a staff account.',
            $user->isSeller() || $user->is_seller => 'This account has a shop.',
            \App\Support\CurrentShop::membershipOf($user) !== null => 'This account works for a shop.',
            $user->erased_at !== null => 'This account was already erased.',
            default => null,
        };
    }

    /**
     * Move everything from the duplicate account into the one to keep, and close the duplicate.
     */
    public function merge(User $duplicate, User $keep, ?User $by): ?string
    {
        if ($duplicate->is($keep)) {
            return 'Choose two different accounts.';
        }
        if ($problem = $this->notACustomer($duplicate) ?? $this->notACustomer($keep)) {
            return $problem;
        }

        DB::transaction(function () use ($duplicate, $keep, $by) {
            foreach (self::OWNED as [$table, $column]) {
                DB::table($table)->where($column, $duplicate->id)->update([$column => $keep->id]);
            }
            foreach (self::OWNED_UNIQUE as [$table, $column, $other]) {
                $already = DB::table($table)->where($column, $keep->id)->pluck($other);
                DB::table($table)->where($column, $duplicate->id)->whereIn($other, $already)->delete();
                DB::table($table)->where($column, $duplicate->id)->update([$column => $keep->id]);
            }
            if (! DB::table('business_profiles')->where('user_id', $keep->id)->exists()) {
                DB::table('business_profiles')->where('user_id', $duplicate->id)->update(['user_id' => $keep->id]);
            }

            $keep->forceFill([
                'loyalty_points' => (int) $keep->loyalty_points + (int) $duplicate->loyalty_points,
                'wallet_balance' => round((float) $keep->wallet_balance + (float) $duplicate->wallet_balance, 2),
                'staff_tags' => array_values(array_unique(array_merge((array) $keep->staff_tags, (array) $duplicate->staff_tags))) ?: null,
            ])->save();
            DB::table('carts')->where('user_id', $duplicate->id)->delete();
            DB::table('push_subscriptions')->where('user_id', $duplicate->id)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $duplicate->id)->delete();

            $duplicate->forceFill([
                'loyalty_points' => 0, 'wallet_balance' => 0, 'is_active' => false, 'merged_into_id' => $keep->id,
                'email' => 'merged-'.$duplicate->id.'@iruali.invalid', 'phone' => null, 'phone_verified_at' => null,
                'password' => Hash::make(Str::random(40)), 'remember_token' => null,
            ])->save();

            Audit::record('customer.merged', $keep, ['from' => $duplicate->id, 'by' => $by?->id]);
        });

        return null;
    }

    /**
     * Why erasing now is not possible (money or orders still open), or null.
     */
    public function eraseProblem(User $user): ?string
    {
        if ($problem = $this->notACustomer($user)) {
            return $problem;
        }
        if (Order::where('user_id', $user->id)->whereIn('status', ['pending', 'processing', 'shipped', 'out_for_delivery'])->exists()) {
            return __('There is an order still on its way. The account can be deleted once it is delivered or cancelled.');
        }
        if (Order::where('user_id', $user->id)->where('refund_status', 'due')->exists()) {
            return __('A refund is still being sent. The account can be deleted once it has gone through.');
        }
        if ((float) $user->wallet_balance > 0) {
            return __('There is store credit left in the wallet. Please spend it or ask us to refund it first.');
        }

        return null;
    }

    /**
     * Remove the customer's personal data. Orders, amounts and invoices stay (the shop's and iruali's
     * records), without the name, email, phone and address.
     */
    public function erase(User $user, ?User $by, string $reason): ?string
    {
        if ($problem = $this->eraseProblem($user)) {
            return $problem;
        }
        $email = $user->email;

        DB::transaction(function () use ($user, $by, $reason, $email) {
            foreach (['addresses', 'social_accounts', 'push_subscriptions', 'business_profiles', 'customer_notes', 'carts'] as $table) {
                DB::table($table)->where('user_id', $user->id)->delete();
            }
            foreach (self::OWNED_UNIQUE as [$table, $column]) {
                DB::table($table)->where($column, $user->id)->delete();
            }
            DB::table('stock_alerts')->where('user_id', $user->id)->orWhere('email', $email)->delete();
            DB::table('newsletter_subscribers')->where('email', $email)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $user->id)->delete();

            Order::where('user_id', $user->id)->update([
                'shipping_address' => json_encode('Erased'), 'shipping_phone' => null, 'shipping_zip' => null,
                'guest_email' => null, 'guest_name' => null, 'gift_receiver_name' => null, 'gift_receiver_phone' => null, 'gift_message' => null,
                'buyer_business_name' => null, 'buyer_tin' => null, 'buyer_business_address' => null,
            ]);

            $user->forceFill([
                'name' => 'Erased customer', 'email' => 'erased-'.$user->id.'@iruali.invalid', 'phone' => null, 'phone_verified_at' => null,
                'password' => Hash::make(Str::random(40)), 'remember_token' => null, 'avatar' => null,
                'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_enabled' => false,
                'address' => null, 'city' => null, 'state' => null, 'postal_code' => null, 'date_of_birth' => null, 'birth_date' => null,
                'last_login_ip' => null, 'notification_preferences' => null, 'staff_tags' => null, 'team_alerts' => null,
                'marketing_opt_out_at' => now(), 'is_active' => false, 'erased_at' => now(),
            ])->save();

            // The audit row keeps who and why, not the data that was removed
            Audit::record('customer.erased', $user, ['by' => $by ? 'staff' : 'customer', 'reason' => $reason]);
        });

        return null;
    }
}
