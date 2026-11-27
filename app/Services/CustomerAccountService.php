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
        ['customer_notes', 'user_id'], ['complaints', 'user_id'], ['order_feedback', 'user_id'],
        ['item_requests', 'user_id'],
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
            // One of each per customer: the duplicate's moves over when the kept account has none
            foreach (['business_profiles', 'credit_accounts'] as $table) {
                if (! DB::table($table)->where('user_id', $keep->id)->exists()) {
                    DB::table($table)->where('user_id', $duplicate->id)->update(['user_id' => $keep->id]);
                }
            }
            // People the duplicate referred count as the kept account's
            DB::table('users')->where('referred_by', $duplicate->id)->update(['referred_by' => $keep->id]);

            $keep->forceFill([
                'loyalty_points' => (int) $keep->loyalty_points + (int) $duplicate->loyalty_points,
                'wallet_balance' => round((float) $keep->wallet_balance + (float) $duplicate->wallet_balance, 2),
                'staff_tags' => array_values(array_unique(array_merge((array) $keep->staff_tags, (array) $duplicate->staff_tags))) ?: null,
            ])->save();
            DB::table('carts')->where('user_id', $duplicate->id)->delete();
            DB::table('push_subscriptions')->where('user_id', $duplicate->id)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $duplicate->id)->delete();
            DB::table('sessions')->where('user_id', $duplicate->id)->delete(); // a browser still signed in to it is signed out

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
        if (app(CreditService::class)->openInvoices($user)->exists()) {
            return __('There are pay-later invoices still to pay. The account can be deleted once they are paid.');
        }
        if (\App\Models\ReturnRequest::where('user_id', $user->id)->whereIn('status', ['requested', 'approved'])->exists()
            || \App\Models\Dispute::where('customer_id', $user->id)->whereIn('status', \App\Enums\DisputeStatus::openValues())->exists()) {
            return __('A return or dispute is still being decided. The account can be deleted once it is closed.');
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
        $phone = \App\Services\Sms\PhoneNumber::normalize($user->phone);
        // Files of theirs to delete once the rows are changed: [disk, path]
        $files = collect([['public', $user->avatar]])
            ->merge(Order::where('user_id', $user->id)->get(['payment_slip_path', 'deposit_slip_path'])->flatMap(fn ($o) => [[\App\Services\ManualPaymentService::SLIP_DISK, $o->payment_slip_path], [\App\Services\ManualPaymentService::SLIP_DISK, $o->deposit_slip_path]]))
            ->merge(DB::table('messages')->where('sender_id', $user->id)->whereNotNull('attachment_path')->pluck('attachment_path')->map(fn ($p) => [\App\Services\MessagingService::DISK, $p]))
            ->merge(DB::table('return_requests')->where('user_id', $user->id)->whereNotNull('photo_path')->pluck('photo_path')->map(fn ($p) => [ReturnService::DISK, $p]))
            ->merge(DB::table('item_requests')->where('user_id', $user->id)->whereNotNull('photo_path')->pluck('photo_path')->map(fn ($p) => ['public', $p]))
            // Their review photos and the photos of the products made for their item requests (with their WebP copies)
            ->merge(DB::table('review_photos')->whereIn('product_review_id', DB::table('product_reviews')->where('user_id', $user->id)->select('id'))->pluck('path')->map(fn ($p) => ['public', $p]))
            ->merge(DB::table('product_images')->whereIn('product_id', $this->requestProducts($user))->pluck('url')->map(fn ($p) => ['public', $p]))
            ->merge(DB::table('complaints')->where('user_id', $user->id)->whereNotNull('photos')->pluck('photos')
                ->flatMap(fn ($photos) => (array) json_decode((string) $photos, true))->map(fn ($p) => [\App\Models\Complaint::DISK, $p]))
            ->filter(fn ($file) => filled($file[1]) && is_string($file[1]));
        // Phones the texts went to: their own, and the delivery and gift numbers on their orders
        $phones = collect([$phone])
            ->merge(Order::where('user_id', $user->id)->get(['shipping_phone', 'gift_receiver_phone'])->flatMap(fn ($o) => [$o->shipping_phone, $o->gift_receiver_phone]))
            ->map(fn ($p) => \App\Services\Sms\PhoneNumber::normalize($p))->filter()->unique()->values()->all();
        $requestProducts = $this->requestProducts($user);

        DB::transaction(function () use ($user, $by, $reason, $email, $phones, $requestProducts) {
            $erasedEmail = 'erased-'.$user->id.'@iruali.invalid';
            foreach (['addresses', 'social_accounts', 'push_subscriptions', 'business_profiles', 'customer_notes', 'carts'] as $table) {
                DB::table($table)->where('user_id', $user->id)->delete();
            }
            foreach (self::OWNED_UNIQUE as [$table, $column]) {
                DB::table($table)->where($column, $user->id)->delete();
            }
            // (a phone-only account has no email: never match rows by an empty one)
            DB::table('stock_alerts')->where('user_id', $user->id)->when(filled($email), fn ($q) => $q->orWhere('email', $email))->delete();
            if (filled($email)) {
                DB::table('newsletter_subscribers')->where('email', $email)->delete();
                DB::table('ordering_back_requests')->where('email', $email)->delete();
                DB::table('password_reset_tokens')->where('email', $email)->delete();
            }
            DB::table('complaints')->where('user_id', $user->id)->when(filled($email), fn ($q) => $q->orWhere('email', $email))
                ->update(['name' => 'Erased customer', 'email' => $erasedEmail, 'phone' => null, 'details' => 'Removed', 'photos' => null]);
            DB::table('newsletter_deliveries')->where('user_id', $user->id)->when(filled($email), fn ($q) => $q->orWhere('email', $email))->update(['email' => $erasedEmail]);
            DB::table('shop_discount_redemptions')->where('user_id', $user->id)->update(['email' => null]);
            // Threads they started as a visitor before having an account
            DB::table('conversations')->where('customer_id', $user->id)->when(filled($email), fn ($q) => $q->orWhere('guest_email', $email))
                ->update(['guest_name' => null, 'guest_email' => null, 'guest_phone' => null]);
            // What they wrote elsewhere: reviews (kept for the rating, without their name, email, words or
            // photos), questions, quote chat, dispute and feedback text
            $reviews = DB::table('product_reviews')->where('user_id', $user->id);
            DB::table('review_photos')->whereIn('product_review_id', (clone $reviews)->select('id'))->delete();
            (clone $reviews)->update(['reviewer_name' => 'Erased customer', 'reviewer_email' => $erasedEmail, 'title' => null, 'comment' => '']);
            DB::table('product_questions')->where('user_id', $user->id)->update(['question' => 'Question removed']);
            DB::table('quote_messages')->where('sender_id', $user->id)->update(['body' => 'Message removed']);
            DB::table('disputes')->where('customer_id', $user->id)->update(['details' => null]);
            DB::table('order_feedback')->where('user_id', $user->id)->update(['comment' => null]);
            // Gift cards they bought: their message, and the recipient's details once the card is used up
            DB::table('gift_cards')->where('purchaser_id', $user->id)->update(['message' => null]);
            DB::table('gift_cards')->where('purchaser_id', $user->id)->whereNotIn('status', ['pending', 'active'])->update(['recipient_name' => null, 'recipient_email' => $erasedEmail]);
            // The products made for their item requests carry a copy of the request and its photo
            DB::table('products')->whereIn('id', $requestProducts)->update(['description' => json_encode(['en' => ''])]);
            DB::table('product_images')->whereIn('product_id', $requestProducts)->delete();
            // The old and new numbers staff wrote down when they changed the phone
            DB::table('audit_logs')->where('action', 'customer.phone_changed')->where('subject_type', User::class)->where('subject_id', $user->id)->update(['changes' => null]);
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete(); // signed out everywhere

            // What they wrote stays in its conversation (conversations are never deleted), without the words
            DB::table('messages')->where('sender_id', $user->id)->update(['body' => 'Message removed', 'original_body' => null, 'attachment_path' => null]);
            // The texts sent to their number (kept for the SMS costs), without the number or the wording
            if ($phones !== []) {
                DB::table('sms_messages')->whereIn('to', $phones)->update(['to' => 'erased', 'message' => 'Removed', 'provider_response' => null]);
            }
            DB::table('quote_requests')->where('customer_id', $user->id)->update(['business_name' => 'Erased customer', 'business_tin' => null, 'business_address' => 'Erased', 'notes' => null]);
            DB::table('return_requests')->where('user_id', $user->id)->update(['details' => null, 'photo_path' => null]);
            DB::table('item_requests')->where('user_id', $user->id)->update(['details' => null, 'photo_path' => null]);

            Order::where('user_id', $user->id)->update([
                'shipping_address' => json_encode('Erased'), 'shipping_phone' => null, 'shipping_zip' => null,
                'guest_email' => null, 'guest_name' => null, 'gift_receiver_name' => null, 'gift_receiver_phone' => null, 'gift_message' => null,
                'buyer_business_name' => null, 'buyer_tin' => null, 'buyer_business_address' => null,
                'payment_slip_path' => null, 'deposit_slip_path' => null, 'boat' => null,
            ]);

            $user->forceFill([
                'name' => 'Erased customer', 'email' => $erasedEmail, 'phone' => null, 'phone_verified_at' => null,
                'password' => Hash::make(Str::random(40)), 'remember_token' => null, 'avatar' => null,
                'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_enabled' => false,
                'address' => null, 'city' => null, 'state' => null, 'postal_code' => null, 'date_of_birth' => null, 'birth_date' => null,
                'last_login_ip' => null, 'notification_preferences' => null, 'staff_tags' => null, 'team_alerts' => null,
                'marketing_opt_out_at' => now(), 'is_active' => false, 'erased_at' => now(),
            ])->save();

            // The audit row keeps who and why, not the data that was removed
            Audit::record('customer.erased', $user, ['by' => $by ? 'staff' : 'customer', 'reason' => $reason]);
        });

        // Their photo, bank slips, message attachments and return and request photos
        foreach ($files as [$disk, $path]) {
            try {
                \Illuminate\Support\Facades\Storage::disk($disk)->delete($path);
                if ($disk === \App\Support\ImageVariants::DISK) {
                    \App\Support\ImageVariants::delete($path); // its WebP copies
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return null;
    }

    /**
     * The (never listed) products made for this customer's item requests.
     *
     * @return list<int>
     */
    protected function requestProducts(User $user): array
    {
        return DB::table('products')->whereIn('item_request_id', DB::table('item_requests')->where('user_id', $user->id)->select('id'))->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
