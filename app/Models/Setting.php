<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected const CACHE_KEY = 'app_settings';

    /**
     * Editable settings and their defaults.
     */
    public const DEFAULTS = [
        'announcement_text' => 'Shop local sellers from every island, delivered across the Maldives.',
        // The Dhivehi side of the site (language button, /dv pages, Dhivehi emails); off: English only
        'dhivehi_enabled' => 1,
        // SMS control centre (Admin → SMS): all SMS on/off, monthly limit (0: none), quiet hours "HH:MM"
        'sms_enabled' => 1,
        'sms_monthly_limit' => 0,
        'sms_quiet_from' => '',
        'sms_quiet_until' => '',
        // Team alerts (Admin → Team alerts): the Telegram group's chat id and the events posted there
        'telegram_chat_id' => '',
        // Loyalty tiers by 12-month spend (App\Support\LoyaltyTiers) and the birthday offer
        'loyalty_tiers_enabled' => 1,
        'loyalty_tier_lagoon_min' => 5000,
        'loyalty_tier_lagoon_multiplier' => 1.25,
        'loyalty_tier_atoll_min' => 15000,
        'loyalty_tier_atoll_multiplier' => 1.5,
        'birthday_offer_enabled' => 0,
        'birthday_offer_percent' => 10,
        'birthday_offer_days' => 7,
        'telegram_events' => 'new_order,bank_slip,refund_due,dispute_opened,return_requested,shop_application,payment_error,backup_failed,sms_limit',
        // Admin staff must use two-step sign-in before opening /admin (also needs STAFF_REQUIRE_2FA on)
        'staff_require_two_factor' => 1,
        'contact_email' => '',
        'contact_phone' => '',
        // Business details BML asks to see on the website (policies, footer, checkout)
        'company_legal_name' => '',
        'company_trading_name' => 'iruali',
        'company_registration_no' => '',
        'company_address' => '',
        'company_postal_address' => '',
        'customer_service_hours' => 'Sunday to Thursday, 9:00 to 17:00 (Maldives time)',
        'return_window_days' => 7,
        // Legal pages: optional owner-written text that replaces the built-in policy, and the "Last updated" date
        'legal_last_updated_date' => '',
        'legal_terms_body' => '',
        'legal_refunds_body' => '',
        'legal_delivery_body' => '',
        'legal_privacy_body' => '',
        'legal_security_body' => '',
        'legal_seller_terms_body' => '',
        'whatsapp_number' => '',
        'loyalty_spend_per_point' => 100,
        'referral_referrer_points' => 100,
        'referral_referee_points' => 50,
        'delivery_fee_greater_male' => 25,
        'delivery_fee_islands' => 75,
        'free_delivery_over' => 1000,
        // Ordering rules (App\Support\OrderRules): minimum order and small-order fee in MVR (0: off);
        // pausing ordering: off, all, delivery or cod, an optional window (Maldives time) and message
        'abandoned_cart_sms_enabled' => 0,
        // Admin → Social posts: draft a post when a product is approved; the Telegram channel (@name or id)
        'social_auto_drafts' => 0,
        // Admin → Home page: the blocks as JSON (empty: the built-in order)
        'home_layout_published' => '',
        // Admin → Finance: the BML card fee (%) for the estimate, and the codes Xero imports use
        'card_fee_percent' => 0,
        // Day of the month the GST filing reminder goes out (gst:filing-reminder)
        'gst_reminder_day' => 21,
        // A small prayer-times bar for the shopper's island (App\Support\PrayerTimes), and its sun angles
        'prayer_times_enabled' => 0,
        'prayer_fajr_angle' => 18,
        'prayer_isha_angle' => 18,
        'xero_sales_account' => '200',
        'xero_expense_account' => '400',
        'xero_tax_sales' => 'GST on Income',
        'xero_tax_purchases' => 'GST on Expenses',
        'home_layout_draft' => '',
        'telegram_channel_id' => '',
        'min_order_amount' => 0,
        'small_order_fee' => 0,
        'small_order_threshold' => 0,
        'ordering_pause_mode' => 'off',
        'ordering_pause_from' => '',
        'ordering_pause_until' => '',
        'ordering_pause_message' => '',
        // Shops may set their own delivery fees (and deliver their parts themselves), up to this much
        'shop_own_fees_enabled' => 1,
        'shop_delivery_fee_max' => 500,
        // Marketplace: percent of each shop's item sales iruali keeps (a shop can have its own rate)
        'default_commission_rate' => 10,
        // Marketplace: a shop part not shipped within this many days of payment counts as late
        'late_shipment_days' => 3,
        // Hours after payment before a shop part not started counts as late (orders:alert-late)
        'late_start_hours' => 24,
        // Refunds over this many MVR need a second staff member to approve (0: never)
        'refund_approval_over' => 0,
        // Marketplace: how often and on which day shops are paid (shown in the seller terms)
        'payout_schedule' => 'weekly',
        'payout_day' => 'Sunday',
        // Checkout without an account (orders carry the guest's email and a token for signed links)
        // Payment methods (Admin → Settings → Payment methods): card needs BML set up as well;
        // bank transfer needs the account; cash on delivery is for one shop's order at a time
        'payment_card_enabled' => 1,
        'payment_transfer_enabled' => 0,
        'transfer_bank_name' => 'Bank of Maldives',
        'transfer_account_name' => '',
        'transfer_account_number' => '',
        'transfer_note' => '',
        'transfer_pay_within_hours' => 24,
        'payment_cod_enabled' => 0,
        'cod_max_amount' => 0,
        'cod_greater_male_only' => 0,
        // Site analytics: none, plausible (needs the domain) or ga4 (needs the G-XXXX measurement id)
        'analytics_provider' => 'none',
        'analytics_id' => '',
        'analytics_domain' => '',
        // Social profiles (footer / Organization structured data) and the product feed secret
        'social_facebook' => '',
        'social_instagram' => '',
        'social_tiktok' => '',
        'social_x' => '',
        'feed_token' => '',
        // Abandoned-cart emails (3 h and 48 h nudges); the second nudge carries a voucher when the percent is > 0
        'abandoned_cart_emails_enabled' => 1,
        'abandoned_cart_voucher_percent' => 0,
        // Loyalty points expire this many months after they were earned (0 = never)
        'points_expire_months' => 0,
        // New shop applications: open, paused or hidden, an optional reopening day (Y-m-d) and the
        // message would-be sellers see meanwhile (Admin → Sellers → New shop applications)
        'seller_applications' => 'open',
        'seller_applications_until' => '',
        'seller_applications_message_en' => '',
        'seller_applications_message_dv' => '',
    ];

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $values = static::values();

        // Only fall back when the setting was never saved; a saved blank value stays blank.
        if (! array_key_exists($key, $values)) {
            return $default ?? (static::DEFAULTS[$key] ?? null);
        }

        return $values[$key];
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value ?? '']);
        }

        Cache::forget(static::CACHE_KEY);
    }

    /**
     * All stored settings as key => value, cached.
     */
    protected static function values(): array
    {
        if (Cache::has(static::CACHE_KEY)) {
            return Cache::get(static::CACHE_KEY);
        }

        // Before the key/value migration has run, fall back to defaults without caching.
        if (! Schema::hasColumn('settings', 'key')) {
            return [];
        }

        return Cache::rememberForever(static::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
    }
}
