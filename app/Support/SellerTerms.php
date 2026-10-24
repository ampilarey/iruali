<?php

namespace App\Support;

use App\Models\SellerTermsAgreement;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The marketplace terms a shop signs up to, as placeholders filled from Settings at render time, so
 * the seller terms page (built-in text or the owner's own wording) always shows the current values.
 *
 * The terms have versions. A shop agrees to the current one when it applies, and every time a new one
 * is published it must agree again before it can use the Seller Centre (App\Http\Middleware\ShopAccess,
 * Seller\SellerTermsController). A new version comes either from the code (BUILT_IN_VERSION, raised
 * when the built-in text changes in substance) or from an admin publishing one in Admin → Legal pages
 * (the setting seller_terms_version, a timestamp); the later of the two is current. Changing a
 * placeholder value in Settings (commission, payouts) is not a new version: the terms say those
 * settings can change for orders placed afterwards.
 */
class SellerTerms
{
    public const SCHEDULES = ['weekly', 'fortnightly', 'monthly'];

    /** The date of the built-in text's last change in substance. */
    public const BUILT_IN_VERSION = '2026-10-24';

    /** What changed in the built-in version, shown to shops when they are asked to agree again. */
    public const BUILT_IN_CHANGES = [
        'Pre-orders and "coming soon": only for shops iruali allows, with the rules for booking fees, paying once you confirm, delivery dates and refunds.',
        'Payments by card, bank transfer and cash on delivery, and that you never send an order before it is paid (except cash on delivery).',
        'Photos, text and brands: only what you have the right to use, including what "Import from a link" fills in; no fakes or copies.',
        'Customer details: only for the customer\'s own order, never for marketing or selling outside iruali.',
        'GST and your own tax, chargebacks, liability and how new versions of these terms work.',
    ];

    /**
     * The current version: the built-in one, or a later one an admin published.
     */
    public static function version(): string
    {
        $published = trim((string) Setting::get('seller_terms_version'));

        return $published !== '' && $published > self::BUILT_IN_VERSION ? $published : self::BUILT_IN_VERSION;
    }

    /** "24 October 2026", for a version (the current one by default). */
    public static function versionLabel(?string $version = null): string
    {
        $version ??= self::version();

        try {
            return Carbon::parse($version)->translatedFormat('j F Y');
        } catch (\Throwable) {
            return $version;
        }
    }

    /**
     * What changed in the current version, one line each: the admin's note for a version they
     * published, or the built-in list.
     *
     * @return list<string>
     */
    public static function changes(): array
    {
        if (self::version() !== self::BUILT_IN_VERSION) {
            $lines = preg_split('/\R+/', trim((string) Setting::get('seller_terms_changes'))) ?: [];

            return array_values(array_filter(array_map('trim', $lines), fn (string $line) => $line !== ''));
        }

        return array_map(fn (string $line) => __($line), self::BUILT_IN_CHANGES);
    }

    /**
     * The shops the terms apply to: every account with a shop (pending, approved or suspended).
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public static function shops(): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()->where('is_seller', true)->whereHas('roles', fn ($roles) => $roles->where('name', 'seller'));
    }

    /**
     * An admin publishes a new version (Admin → Legal pages): every shop must agree again, and each
     * active shop's owner is emailed what changed.
     */
    public static function publish(string $changes): string
    {
        $version = now()->format('Y-m-d H:i:s');
        Setting::set(['seller_terms_version' => $version, 'seller_terms_changes' => trim($changes)]);
        Audit::record('seller_terms.published', null, ['version' => $version]);

        self::shops()->where('status', '!=', 'suspended')
            ->chunkById(200, fn ($shops) => \Illuminate\Support\Facades\Notification::send($shops, new \App\Notifications\SellerTermsUpdated($version)));

        return $version;
    }

    /** Whether the shop has agreed to the current version. */
    public static function agreed(User $shop): bool
    {
        return $shop->seller_terms_version === self::version();
    }

    /**
     * The shop agrees to the current version: kept on the shop and as a row of its own, with who
     * pressed "I agree" and from where.
     */
    public static function recordAgreement(User $shop, ?User $by = null, ?Request $request = null): void
    {
        $version = self::version();
        $shop->forceFill(['seller_terms_version' => $version, 'seller_terms_agreed_at' => now()])->save();
        SellerTermsAgreement::create([
            'user_id' => $shop->getKey(),
            'agreed_by' => ($by ?? $shop)->getKey(),
            'version' => $version,
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
        Audit::record('seller_terms.agreed', $shop, ['version' => $version, 'shop' => $shop->shopName()]);
    }

    /**
     * Placeholder => current value, e.g. '{commission_rate}' => '10%'.
     *
     * @return array<string, string>
     */
    public static function placeholders(): array
    {
        $rate = (float) Setting::get('default_commission_rate');
        $day = trim((string) Setting::get('payout_day'));

        return [
            '{commission_rate}' => rtrim(rtrim(number_format($rate, 2), '0'), '.').'%',
            '{payout_schedule}' => self::scheduleLabel((string) Setting::get('payout_schedule')),
            '{payout_day}' => $day !== '' ? $day : __('the scheduled payout day'),
            '{late_shipment_days}' => (string) (int) Setting::get('late_shipment_days'),
            '{return_window_days}' => (string) Company::returnWindowDays(),
            '{trading_name}' => Company::tradingName(),
        ];
    }

    /**
     * What each placeholder means, for the admin legal-page editor.
     *
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return [
            '{commission_rate}' => __('Default commission (Settings → Marketplace)'),
            '{payout_schedule}' => __('How often shops are paid: weekly, every two weeks or monthly'),
            '{payout_day}' => __('The day payouts are made'),
            '{late_shipment_days}' => __('Days a shop has to ship after payment'),
            '{return_window_days}' => __('Days a customer has to ask for a return'),
            '{trading_name}' => __('The marketplace trading name'),
        ];
    }

    public static function replace(string $text): string
    {
        return strtr($text, self::placeholders());
    }

    public static function scheduleLabel(string $schedule): string
    {
        return [
            'weekly' => __('every week'),
            'fortnightly' => __('every two weeks'),
            'monthly' => __('every month'),
        ][$schedule] ?? $schedule;
    }
}
