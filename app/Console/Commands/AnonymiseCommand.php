<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Makes a copy of the production database safe to use on the staging site: every non-staff
 * user gets a made-up name, an @example.test email, a fake phone and a new random password;
 * their tokens, 2FA secrets, sessions, saved addresses, shop bank accounts, messages, texts sent,
 * complaints, notes, order addresses and gift details, guest details, return and dispute notes,
 * newsletter and alert emails and OTPs go too. Staff accounts (admin, support, finance) are kept so
 * the team can still sign in. COVERED lists every personal column it handles; AnonymiseTest fails
 * when a new column that looks personal is neither covered nor listed as not personal there.
 */
class AnonymiseCommand extends Command
{
    protected $signature = 'iruali:anonymise
                            {--force : Actually change the data (without it, only shows what would happen)}
                            {--i-know-this-is-production : Required on a production install}';

    protected $description = 'Replace customer and seller personal data with fake values (for the staging site)';

    /** A small built-in list; name = first + last picked by user id, so re-runs are stable. */
    public const FIRST_NAMES = ['Aishath', 'Mohamed', 'Fathimath', 'Ahmed', 'Mariyam', 'Ibrahim', 'Hawwa', 'Ali', 'Aminath', 'Hassan', 'Khadheeja', 'Hussain', 'Zulaikha', 'Abdulla', 'Shifa', 'Adam'];

    public const LAST_NAMES = ['Rasheed', 'Waheed', 'Naseer', 'Shareef', 'Latheef', 'Manik', 'Didi', 'Fulhu', 'Saeed', 'Zahir', 'Nazim', 'Ibrahim', 'Hameed', 'Shakir', 'Riyaz', 'Moosa'];

    /** Every column this command rewrites, clears or deletes (table.column; table.* for rows deleted). */
    public const COVERED = [
        'users.name', 'users.email', 'users.phone', 'users.address', 'users.city', 'users.state', 'users.postal_code',
        'users.date_of_birth', 'users.birth_date', 'users.last_login_ip', 'users.payout_account_number', 'users.payout_account_name',
        'users.banned_reason', 'users.remember_token', 'users.avatar',
        'personal_access_tokens.*', 'sessions.*', 'password_reset_tokens.*', 'otps.*',
        'push_subscriptions.*', 'social_accounts.*',
        'orders.shipping_address', 'orders.shipping_city', 'orders.shipping_phone', 'orders.shipping_zip',
        'orders.guest_email', 'orders.guest_name', 'orders.gift_receiver_name', 'orders.gift_receiver_phone', 'orders.gift_message',
        'orders.buyer_business_name', 'orders.buyer_tin', 'orders.buyer_business_address',
        'seller_orders.gst_business_name', 'seller_orders.gst_tin', 'seller_orders.gst_business_address',
        'addresses.recipient_name', 'addresses.phone', 'addresses.house_name_or_street',
        'seller_bank_accounts.account_name', 'seller_bank_accounts.account_number',
        'sms_messages.to', 'sms_messages.message', 'sms_messages.provider_response',
        'messages.body', 'messages.original_body', 'messages.report_reason',
        'conversations.guest_email', 'conversations.guest_name', 'conversations.guest_phone', 'conversations.access_token',
        'complaints.name', 'complaints.email', 'complaints.phone', 'complaints.details', 'complaints.notes',
        'customer_notes.body', 'credit_accounts.note',
        'return_requests.details', 'return_requests.admin_note', 'disputes.details', 'disputes.resolution_note',
        'order_feedback.comment', 'item_requests.details',
        'gift_cards.recipient_email', 'gift_cards.recipient_name', 'gift_cards.message',
        'product_reviews.reviewer_email', 'product_reviews.reviewer_name',
        'newsletter_subscribers.email', 'newsletter_deliveries.email', 'stock_alerts.email', 'ordering_back_requests.email',
        'shop_discount_redemptions.email', 'seller_waitlist.email', 'shop_staff_invitations.email',
        'seller_verifications.national_id_number',
        'business_profiles.company_name', 'business_profiles.tin', 'business_profiles.business_address',
        'quote_requests.business_name', 'quote_requests.business_tin', 'quote_requests.business_address', 'quote_requests.notes',
        'quote_messages.body',
        'audit_logs.ip', 'seller_terms_agreements.ip',
    ];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('i-know-this-is-production')) {
            $this->error('This is a production install (APP_ENV=production). Refusing: this command destroys customer data. Run it on the staging copy, or pass --i-know-this-is-production if you really mean it.');

            return self::FAILURE;
        }

        $staffIds = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->whereIn('roles.name', config('staff.roles', ['admin']))
            ->pluck('user_roles.user_id')->unique()->values();

        $users = DB::table('users')->whereNotIn('id', $staffIds);
        $plan = [
            'users anonymised' => $users->count(),
            'staff accounts kept' => $staffIds->count(),
            'personal access tokens deleted' => DB::table('personal_access_tokens')->where('tokenable_type', \App\Models\User::class)->whereNotIn('tokenable_id', $staffIds)->count(),
            'order addresses rewritten' => DB::table('orders')->count(),
            'return request notes cleared' => DB::table('return_requests')->count(),
            'newsletter emails replaced' => DB::table('newsletter_subscribers')->count(),
            'OTP rows truncated' => DB::table('otps')->count(),
            'shop owners\' ID card numbers replaced' => DB::table('seller_verifications')->count(),
            'shop staff invitation emails replaced' => DB::table('shop_staff_invitations')->count(),
            'seller waiting list emails replaced' => DB::table('seller_waitlist')->count(),
            'business details (profiles, orders, quote requests) replaced' => DB::table('business_profiles')->count() + DB::table('orders')->whereNotNull('buyer_business_name')->count() + DB::table('quote_requests')->count(),
            'quote request messages replaced' => DB::table('quote_messages')->count(),
            'saved addresses replaced' => DB::table('addresses')->count(),
            'shop bank accounts replaced' => DB::table('seller_bank_accounts')->count(),
            'texts sent (numbers and wording) replaced' => DB::table('sms_messages')->count(),
            'messages and conversation guests replaced' => DB::table('messages')->count() + DB::table('conversations')->count(),
            'complaints, notes, feedback, dispute and request texts cleared' => DB::table('complaints')->count() + DB::table('customer_notes')->count() + DB::table('order_feedback')->count() + DB::table('disputes')->count() + DB::table('item_requests')->count(),
            'gift details, guest details and other emails replaced' => DB::table('gift_cards')->count() + DB::table('stock_alerts')->count() + DB::table('product_reviews')->whereNotNull('reviewer_email')->count(),
            'sessions, push subscriptions, social sign-ins and reset links deleted (non-staff)' => DB::table('sessions')->count() + DB::table('push_subscriptions')->count() + DB::table('social_accounts')->count() + DB::table('password_reset_tokens')->count(),
        ];

        if (! $this->option('force')) {
            $this->table(['What would change', 'Rows'], collect($plan)->map(fn ($n, $k) => [$k, $n])->values()->all());
            $this->warn('Dry run: nothing changed. Add --force to anonymise.');

            return self::FAILURE;
        }

        // Users: name, email, phone, password, tokens, 2FA, address, bank account
        $users->orderBy('id')->select('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('users')->where('id', $row->id)->update([
                    'name' => self::fakeName($row->id),
                    'email' => "user{$row->id}@example.test",
                    'phone' => (string) (7000000 + $row->id),
                    'password' => Hash::make(Str::random(32)),
                    'remember_token' => null,
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_enabled' => false,
                    'avatar' => null,
                    'address' => null,
                    'city' => null,
                    'state' => null,
                    'postal_code' => null,
                    'date_of_birth' => null,
                    'birth_date' => null,
                    'banned_reason' => null,
                    'last_login_ip' => null,
                    'payout_account_number' => null,
                    'payout_account_name' => null,
                ]);
            }
        });

        DB::table('personal_access_tokens')->where('tokenable_type', \App\Models\User::class)->whereNotIn('tokenable_id', $staffIds)->delete();

        // Orders: where things were delivered and the phone on the parcel
        DB::table('orders')->orderBy('id')->select('id')->chunkById(1000, function ($rows) {
            foreach ($rows as $row) {
                DB::table('orders')->where('id', $row->id)->update([
                    'shipping_address' => json_encode('Test address '.$row->id), // the column is JSON-cast; orders store a string in it
                    'shipping_city' => 'Malé',
                    'shipping_zip' => null,
                    'shipping_phone' => (string) (7000000 + $row->id),
                    'guest_email' => DB::raw("CASE WHEN guest_email IS NULL THEN NULL ELSE 'guest{$row->id}@example.test' END"),
                    'guest_name' => DB::raw("CASE WHEN guest_name IS NULL THEN NULL ELSE 'Guest {$row->id}' END"),
                    'gift_receiver_name' => DB::raw("CASE WHEN gift_receiver_name IS NULL THEN NULL ELSE 'Receiver {$row->id}' END"),
                    'gift_receiver_phone' => DB::raw('CASE WHEN gift_receiver_phone IS NULL THEN NULL ELSE '.(7000000 + $row->id).' END'),
                    'gift_message' => DB::raw("CASE WHEN gift_message IS NULL THEN NULL ELSE 'Gift message' END"),
                ]);
            }
        });

        DB::table('return_requests')->update(['details' => null, 'admin_note' => null]);

        DB::table('newsletter_subscribers')->orderBy('id')->select('id')->chunkById(1000, function ($rows) {
            foreach ($rows as $row) {
                DB::table('newsletter_subscribers')->where('id', $row->id)->update(['email' => "subscriber{$row->id}@example.test"]);
            }
        });
        // People waiting for shop applications to reopen ("Tell me when you reopen")
        DB::table('seller_waitlist')->orderBy('id')->select('id')->chunkById(1000, function ($rows) {
            foreach ($rows as $row) {
                DB::table('seller_waitlist')->where('id', $row->id)->update(['email' => "waiting{$row->id}@example.test"]);
            }
        });

        DB::table('otps')->delete(); // delete, not truncate: truncate would commit a surrounding transaction

        // Business verification: the shop owner's ID card number (stored encrypted, with this install's key)
        DB::table('seller_verifications')->orderBy('id')->select('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('seller_verifications')->where('id', $row->id)->update(['national_id_number' => \Illuminate\Support\Facades\Crypt::encryptString('A'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT))]);
            }
        });

        // Shop staff invitations: the invited email addresses (their links stop working too)
        DB::table('shop_staff_invitations')->orderBy('id')->select('id')->chunkById(1000, function ($rows) {
            foreach ($rows as $row) {
                DB::table('shop_staff_invitations')->where('id', $row->id)->update(['email' => "invitation{$row->id}@example.test", 'token_hash' => hash('sha256', 'anonymised-'.$row->id.'-'.\Illuminate\Support\Str::random(20))]);
            }
        });

        // Business buyers: company names, TINs and addresses on profiles, orders and bulk quote requests,
        // and what customers and shops wrote to each other about a quote
        $chunked = function (string $table, callable $values, ?callable $scope = null): void {
            $query = DB::table($table)->orderBy('id')->select('id');
            if ($scope) {
                $scope($query);
            }
            $query->chunkById(1000, function ($rows) use ($table, $values) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update($values((int) $row->id));
                }
            });
        };
        $chunked('business_profiles', fn (int $id) => ['company_name' => "Business {$id}", 'tin' => null, 'business_address' => "Test business address {$id}"]);
        $chunked('orders', fn (int $id) => ['buyer_business_name' => "Business {$id}", 'buyer_tin' => null, 'buyer_business_address' => "Test business address {$id}"], fn ($q) => $q->whereNotNull('buyer_business_name'));
        $chunked('quote_requests', fn (int $id) => ['business_name' => "Business {$id}", 'business_tin' => null, 'business_address' => "Test business address {$id}", 'notes' => null]);
        $chunked('quote_messages', fn (int $id) => ['body' => "Message {$id}"]);

        $chunked('seller_orders', fn (int $id) => ['gst_business_name' => "Business {$id}", 'gst_tin' => null, 'gst_business_address' => "Test business address {$id}"], fn ($q) => $q->whereNotNull('gst_business_name'));

        // Saved addresses, shop bank accounts, the texts sent (number and wording), messages and their guests
        $chunked('addresses', fn (int $id) => ['recipient_name' => self::fakeName($id), 'phone' => (string) (7000000 + $id), 'house_name_or_street' => "Test house {$id}"]);
        $chunked('seller_bank_accounts', fn (int $id) => ['account_name' => "Account {$id}", 'account_number' => '7730000'.str_pad((string) $id, 6, '0', STR_PAD_LEFT)]);
        $chunked('sms_messages', fn (int $id) => ['to' => (string) (7000000 + $id), 'message' => 'Text '.$id, 'provider_response' => null]);
        $chunked('messages', fn (int $id) => ['body' => "Message {$id}", 'original_body' => null, 'report_reason' => null]);
        $chunked('conversations', fn (int $id) => ['guest_email' => DB::raw("CASE WHEN guest_email IS NULL THEN NULL ELSE 'guest{$id}@example.test' END"), 'guest_name' => DB::raw("CASE WHEN guest_name IS NULL THEN NULL ELSE 'Guest {$id}' END"), 'guest_phone' => null, 'access_token' => DB::raw('CASE WHEN access_token IS NULL THEN NULL ELSE \''.Str::random(48).'\' END')]);

        // What people wrote: complaints, staff notes about customers, feedback, disputes, requests, returns
        $chunked('complaints', fn (int $id) => ['name' => self::fakeName($id), 'email' => "complaint{$id}@example.test", 'phone' => null, 'details' => "Complaint {$id}", 'notes' => null]);
        DB::table('customer_notes')->update(['body' => 'Note']);
        DB::table('credit_accounts')->update(['note' => null]);
        DB::table('order_feedback')->update(['comment' => null]);
        DB::table('disputes')->update(['details' => 'Dispute details', 'resolution_note' => null]);
        DB::table('item_requests')->update(['details' => null]);

        // Gift cards, product reviews by email, and the email lists
        $chunked('gift_cards', fn (int $id) => ['recipient_email' => DB::raw("CASE WHEN recipient_email IS NULL THEN NULL ELSE 'giftcard{$id}@example.test' END"), 'recipient_name' => DB::raw("CASE WHEN recipient_name IS NULL THEN NULL ELSE 'Receiver {$id}' END"), 'message' => null]);
        $chunked('product_reviews', fn (int $id) => ['reviewer_email' => "reviewer{$id}@example.test", 'reviewer_name' => self::fakeName($id)], fn ($q) => $q->whereNotNull('reviewer_email'));
        foreach (['newsletter_deliveries' => 'delivery', 'stock_alerts' => 'alert', 'ordering_back_requests' => 'back', 'shop_discount_redemptions' => 'redemption'] as $table => $label) {
            $chunked($table, fn (int $id) => ['email' => DB::raw("CASE WHEN email IS NULL THEN NULL ELSE '{$label}{$id}@example.test' END")]);
        }

        // Signed-in devices and other ways in, and where people signed in from (staff keep theirs)
        DB::table('sessions')->delete();
        DB::table('password_reset_tokens')->delete();
        DB::table('push_subscriptions')->whereNotIn('user_id', $staffIds)->delete();
        DB::table('social_accounts')->whereNotIn('user_id', $staffIds)->delete();
        DB::table('audit_logs')->update(['ip' => null]);
        DB::table('seller_terms_agreements')->update(['ip' => null]);

        $this->table(['Changed', 'Rows'], collect($plan)->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->info('Done. Anonymised users can no longer sign in (random passwords); staff accounts are unchanged.');

        return self::SUCCESS;
    }

    public static function fakeName(int $id): string
    {
        return self::FIRST_NAMES[$id % count(self::FIRST_NAMES)].' '.self::LAST_NAMES[intdiv($id, count(self::FIRST_NAMES)) % count(self::LAST_NAMES)];
    }
}
