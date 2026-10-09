<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\SellerPayout;
use App\Models\Setting;
use App\Models\ShopTaxProfile;
use App\Rules\MiraTin;
use App\Support\Company;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * GST-ready: iruali's own GST settings (Admin → Tax), each shop part's GST frozen when the order
 * is placed, invoice numbers, iruali's commission invoices and a business buyer's details.
 *
 * Prices on iruali include GST, so the GST inside an amount is amount × rate ÷ (100 + rate),
 * rounded to the laari. Nothing here changes a price, a total, a delivery fee or what a shop earns;
 * until iruali or a shop is marked GST-registered, no GST shows anywhere.
 */
class GstService
{
    public const DEFAULT_RATE = 8.0;

    public const DEFAULT_PREFIX = 'INV';

    /** MIRA GST TIN: 7 digits, "GST", 3 digits. */
    public const TIN_PATTERN = '/^\d{7}GST\d{3}$/';

    /** iruali's commission invoices; each shop has its own series "shop:<id>" ("shop:0" for iruali's own sales). */
    public const COMMISSION_SERIES = 'commission';

    // ---- iruali's settings (Admin → Tax) ------------------------------------------------------

    public function platformRegistered(): bool
    {
        return (bool) Setting::get('gst_registered', 0) && $this->platformTin() !== null;
    }

    public function platformTin(): ?string
    {
        $tin = self::normaliseTin(Setting::get('gst_tin', ''));

        return self::isValidTin($tin) ? $tin : null;
    }

    public function rate(): float
    {
        $rate = Setting::get('gst_rate', self::DEFAULT_RATE);

        return is_numeric($rate) ? round((float) $rate, 2) : self::DEFAULT_RATE;
    }

    public function invoicePrefix(): string
    {
        $prefix = strtoupper(trim((string) Setting::get('invoice_prefix', self::DEFAULT_PREFIX)));

        return preg_match('/^[A-Z0-9][A-Z0-9-]{0,9}$/', $prefix) ? $prefix : self::DEFAULT_PREFIX;
    }

    /**
     * @return array{gst_registered: bool, gst_tin: string, gst_rate: float, invoice_prefix: string}
     */
    public function settings(): array
    {
        return [
            'gst_registered' => (bool) Setting::get('gst_registered', 0),
            'gst_tin' => self::normaliseTin(Setting::get('gst_tin', '')),
            'gst_rate' => $this->rate(),
            'invoice_prefix' => $this->invoicePrefix(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function settingsRules(): array
    {
        return [
            'gst_registered' => 'required|boolean',
            'gst_tin' => ['nullable', 'required_if:gst_registered,1', 'string', 'max:30', new MiraTin],
            'gst_rate' => 'required|numeric|min:0|max:100',
            'invoice_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9][A-Z0-9-]*$/'],
        ];
    }

    // ---- Maths ----------------------------------------------------------------------------------

    /**
     * The GST inside a GST-inclusive amount: amount × rate ÷ (100 + rate), rounded half up to the
     * laari. Worked in whole laari so floating point never tips a rounding.
     */
    public static function gstIncluded(float|int|string|null $amount, float|int|string|null $rate): float
    {
        $laari = (int) round((float) $amount * 100);
        $basisPoints = (int) round((float) $rate * 100);
        if ($laari === 0 || $basisPoints <= 0) {
            return 0.0;
        }

        $numerator = abs($laari) * $basisPoints;
        $denominator = 10000 + $basisPoints;
        $gst = intdiv(2 * $numerator + $denominator, 2 * $denominator);

        return ($laari < 0 ? -$gst : $gst) / 100;
    }

    /**
     * "1012345 gst 501" → "1012345GST501": spaces and letter case are forgiven.
     */
    public static function normaliseTin(mixed $tin): string
    {
        return is_scalar($tin) ? strtoupper((string) preg_replace('/\s+/', '', (string) $tin)) : '';
    }

    public static function isValidTin(?string $tin): bool
    {
        return $tin !== null && preg_match(self::TIN_PATTERN, $tin) === 1;
    }

    // ---- The order-time snapshot --------------------------------------------------------------

    /**
     * Freeze a shop part's GST (FulfilmentService::refreshPart calls this whenever a part is created
     * or refreshed). The shop's registration, TIN, name, address and the rate are taken the first
     * time only; the amounts follow the part's goods total until it is invoiced, then never change.
     */
    public function capturePart(SellerOrder $part, ?Order $order = null): void
    {
        try {
            $this->snapshot($part, $order ?? $part->order);
        } catch (Throwable $e) {
            report($e); // never stops an order being placed: the snapshot is completed when it is paid
        }
    }

    /**
     * A paid order was cancelled: the shop's sale is reversed (it shows as a refund in that month's
     * GST report, and on the invoice).
     */
    public function partCancelled(SellerOrder $part): void
    {
        try {
            if (! $part->gst_reversed_at && $part->order?->payment_status === 'paid') {
                $this->persist($part, ['gst_reversed_at' => now()]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * iruali's delivery fee on the order, GST included. Delivery belongs to iruali, not the shops
     * (PayoutService pays shops their goods less commission only).
     */
    public function deliveryFee(Order $order): float
    {
        return round((float) $order->shipping_amount, 2);
    }

    protected function snapshot(SellerOrder $part, ?Order $order): void
    {
        if (! $order || $part->invoice_number) {
            return;
        }

        $this->captureOrder($order);

        $values = [];
        if (! $part->gst_captured_at) {
            $shop = $this->shopDetails($part);
            $values = $shop + ['gst_rate' => $shop['gst_registered'] ? $this->rate() : null, 'gst_captured_at' => now()];
        }
        $registered = (bool) ($values['gst_registered'] ?? $part->gst_registered);
        $rate = $values['gst_rate'] ?? $part->gst_rate;

        // Goods after any shop discount: worked out in one place, SellerOrder::taxableGoodsTotal()
        $taxable = $part->taxableGoodsTotal();
        $values += [
            'gst_taxable' => $taxable,
            'gst_amount' => $registered ? self::gstIncluded($taxable, $rate) : 0,
            'commission_gst' => $order->gst_platform_registered ? self::gstIncluded($part->commission_amount, $order->gst_rate) : 0,
        ];

        $this->persist($part, $values);
    }

    /**
     * iruali's side of the order: registered or not, TIN and rate when it was placed, and the GST
     * in the delivery fee.
     */
    protected function captureOrder(Order $order): void
    {
        // Another copy of this order may already have frozen it: what is stored wins
        $frozen = ['gst_platform_registered', 'gst_platform_tin', 'gst_rate', 'gst_captured_at'];
        $stored = DB::table('orders')->where('id', $order->getKey())->first($frozen);
        if ($stored !== null && $stored->gst_captured_at !== null) {
            $order->forceFill((array) $stored)->syncOriginalAttributes($frozen);
        }

        $values = [];
        if (! $order->gst_captured_at) {
            $tin = $this->platformRegistered() ? $this->platformTin() : null;
            $values = [
                'gst_platform_registered' => $tin !== null,
                'gst_platform_tin' => $tin,
                'gst_rate' => $tin !== null ? $this->rate() : null,
                'gst_captured_at' => now(),
            ];
        }
        $registered = (bool) ($values['gst_platform_registered'] ?? $order->gst_platform_registered);
        $rate = array_key_exists('gst_rate', $values) ? $values['gst_rate'] : $order->gst_rate;

        $values['delivery_gst'] = $registered ? self::gstIncluded($this->deliveryFee($order), $rate) : 0;

        $this->persist($order, $values);
    }

    /**
     * The seller of a part as it is now. A part without a shop is iruali's own sale.
     *
     * @return array{gst_registered: bool, gst_tin: ?string, gst_business_name: ?string, gst_business_address: ?string}
     */
    protected function shopDetails(SellerOrder $part): array
    {
        if (! $part->seller_id) {
            $tin = $this->platformRegistered() ? $this->platformTin() : null;

            return [
                'gst_registered' => $tin !== null,
                'gst_tin' => $tin,
                'gst_business_name' => self::limit(Company::legalName() ?? Company::tradingName(), 150),
                'gst_business_address' => self::limit(Company::address(), 300),
            ];
        }

        $profile = ShopTaxProfile::forUser($part->seller_id);
        $seller = $part->seller;
        $tin = $profile !== null && $profile->isRegistered() ? $profile->tin : null;
        $address = collect([$seller?->address, $seller?->city, $seller?->state])->filter()->join(', ');

        return [
            'gst_registered' => $tin !== null,
            'gst_tin' => $tin,
            'gst_business_name' => self::limit($profile?->registered_name ?: $seller?->shopName(), 150),
            'gst_business_address' => self::limit($profile?->business_address ?: $address, 300),
        ];
    }

    // ---- Shop invoices --------------------------------------------------------------------------

    /**
     * The order has been paid (PaymentService::confirm): once the payment is committed, every shop
     * part gets its invoice number. Never holds up or undoes the payment.
     */
    public function orderPaid(Order $order): void
    {
        $orderId = $order->getKey();

        DB::afterCommit(function () use ($orderId) {
            try {
                if ($order = Order::withTrashed()->find($orderId)) {
                    $this->assignInvoiceNumbers($order);
                }
            } catch (Throwable $e) {
                report($e); // the invoice page numbers any part still missing one when it is opened
            }
        });
    }

    /**
     * Number each shop part of a paid order: the next number in that shop's own series, taken with
     * the series row locked so payments landing at the same moment never share or skip a number.
     * Unpaid orders and gift-card orders get none. Safe to call again.
     */
    public function assignInvoiceNumbers(Order $order): void
    {
        if ($order->payment_status !== 'paid' || $order->isGiftCardOrder()) {
            return;
        }

        $parts = $order->sellerOrders()->whereNull('invoice_number')->orderBy('id')->get(['id', 'seller_id']);
        foreach ($parts->pluck('seller_id')->unique() as $sellerId) {
            $this->ensureSeries('shop:'.($sellerId ?: 0)); // before locking, so the row lock is never upgraded
        }

        foreach ($parts->pluck('id') as $partId) {
            DB::transaction(function () use ($partId, $order) {
                $part = SellerOrder::whereKey($partId)->lockForUpdate()->first();
                if (! $part || $part->invoice_number) {
                    return;
                }

                // Final amounts from the frozen details (an order placed before GST existed is captured now)
                $this->snapshot($part, $order);
                $sellerId = $part->seller_id ?: null;
                $sequence = $this->nextNumber('shop:'.($sellerId ?? 0), fn () => SellerOrder::where('seller_id', $sellerId)->max('invoice_sequence'));
                $this->persist($part, [
                    'invoice_sequence' => $sequence,
                    'invoice_number' => $this->shopInvoiceNumber($sellerId, $sequence),
                    'invoiced_at' => $order->paid_at ?? now(),
                ]);
            }, 3);
        }
    }

    /**
     * "INV-12-000034": prefix, the shop (IR for iruali's own sales), its own running number.
     */
    public function shopInvoiceNumber(?int $sellerId, int $sequence): string
    {
        return sprintf('%s-%s-%06d', $this->invoicePrefix(), $sellerId ? (string) $sellerId : 'IR', $sequence);
    }

    /**
     * A tax invoice when the shop was GST-registered when the order was placed, else a receipt.
     */
    public function isTaxInvoice(SellerOrder $part): bool
    {
        return (bool) $part->gst_registered;
    }

    /**
     * Where the customer opens a shop's invoice: My Orders, or the signed guest link.
     */
    public function customerInvoiceUrl(Order $order, SellerOrder $part): string
    {
        if ($order->isGuest() && $order->guest_token) {
            return URL::signedRoute('guest.orders.invoice', ['order' => $order->getKey(), 'token' => $order->guest_token, 'part' => $part->getKey()]);
        }

        return route('orders.invoice', [$order, $part]);
    }

    // ---- iruali's commission invoices -----------------------------------------------------------

    /**
     * A payout has been paid (recorded directly, or its batch marked paid): number iruali's invoice
     * to the shop for the commission and keep the shop's tax details as they are today. Never
     * blocks the payout; a payout left without a number is numbered when its invoice is opened.
     */
    public function payoutPaid(SellerPayout $payout): void
    {
        try {
            if (! $payout->isPaid() || $payout->invoice_number) {
                return;
            }

            DB::transaction(function () use ($payout) {
                $locked = SellerPayout::whereKey($payout->getKey())->lockForUpdate()->first();
                if (! $locked || ! $locked->isPaid() || $locked->invoice_number) {
                    return;
                }

                $sequence = $this->nextNumber(self::COMMISSION_SERIES, fn () => SellerPayout::max('invoice_sequence'));
                $values = [
                    'invoice_sequence' => $sequence,
                    'invoice_number' => sprintf('%s-C-%06d', $this->invoicePrefix(), $sequence),
                    'invoiced_at' => $locked->paid_at ?? now(),
                    'invoice_details' => $this->payoutRecipient($locked),
                ];
                $this->persist($locked, $values);
                $payout->forceFill($values)->syncOriginalAttributes(array_keys($values));
            });
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * What iruali's commission invoice for a payout shows: one line per order part with its
     * commission and the GST in it (fixed when each order was placed), and the deductions settled.
     *
     * @return array{lines: \Illuminate\Database\Eloquent\Collection<int, SellerOrder>, sales: float, commission: float, gst: float, adjustments: float, tax_invoice: bool}
     */
    public function commissionInvoice(SellerPayout $payout): array
    {
        $lines = $payout->sellerOrders()->with('order')->orderBy('id')->get();
        $gst = round((float) $lines->sum('commission_gst'), 2);

        return [
            'lines' => $lines,
            // What the shop's earnings and the commission were taken from, so the settlement adds up
            'sales' => round((float) $lines->sum(fn (SellerOrder $line) => (float) $line->seller_earnings + (float) $line->commission_amount), 2),
            'commission' => round((float) $lines->sum('commission_amount'), 2),
            'gst' => $gst,
            'adjustments' => round((float) $payout->adjustments()->sum('amount'), 2),
            'tax_invoice' => $gst > 0,
        ];
    }

    /**
     * @return array{name: string, tin: ?string, address: ?string, platform_tin: ?string}
     */
    protected function payoutRecipient(SellerPayout $payout): array
    {
        $seller = $payout->seller;
        $profile = ShopTaxProfile::forUser($payout->seller_id);
        $address = collect([$seller?->address, $seller?->city, $seller?->state])->filter()->join(', ');

        return [
            'name' => (string) ($profile?->registered_name ?: $seller?->shopName()),
            'tin' => $profile !== null && $profile->isRegistered() ? $profile->tin : null,
            'address' => self::limit($profile?->business_address ?: $address, 300),
            'platform_tin' => $this->platformRegistered() ? $this->platformTin() : null,
        ];
    }

    // ---- Business buyers ------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public static function buyerRules(): array
    {
        return [
            'buyer_business_name' => 'required|string|max:150',
            'buyer_tin' => ['nullable', 'string', 'max:30', new MiraTin],
            'buyer_business_address' => 'required|string|max:300',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function buyerAttributes(): array
    {
        return [
            'buyer_business_name' => __('company name'),
            'buyer_tin' => __('TIN'),
            'buyer_business_address' => __('business address'),
        ];
    }

    /**
     * Checkout's "Buying for a business?" block: the checked details, or [] when it was left off.
     * Incomplete details send the buyer back to checkout with the errors (before any order exists).
     *
     * @return array<string, ?string>
     */
    public function buyerFromRequest(Request $request): array
    {
        if (! $request->boolean('business_invoice')) {
            return [];
        }

        $request->merge(['buyer_tin' => self::normaliseTin($request->input('buyer_tin')) ?: null]);
        $data = $request->validate(self::buyerRules(), [], self::buyerAttributes());

        return [
            'buyer_business_name' => trim((string) $data['buyer_business_name']),
            'buyer_tin' => $data['buyer_tin'] ?? null,
            'buyer_business_address' => trim((string) $data['buyer_business_address']),
        ];
    }

    /**
     * Keep the buyer's business details on the order (printed on every shop's invoice).
     *
     * @param  array<string, ?string>  $buyer
     */
    public function recordBuyer(Order $order, array $buyer): void
    {
        if ($buyer !== []) {
            $this->persist($order, array_intersect_key($buyer, array_flip(['buyer_business_name', 'buyer_tin', 'buyer_business_address'])));
        }
    }

    // ---- Helpers --------------------------------------------------------------------------------

    /**
     * The next number in an invoice series. Runs inside a transaction: the series row stays locked
     * until it commits, so concurrent callers queue behind each other. Never goes below a number
     * already issued (e.g. after a database restore).
     *
     * @param  Closure(): mixed  $highestIssued
     */
    protected function nextNumber(string $series, Closure $highestIssued): int
    {
        $this->ensureSeries($series);

        $last = (int) DB::table('invoice_sequences')->where('series', $series)->lockForUpdate()->value('last_number');
        $next = max($last, (int) $highestIssued()) + 1;
        DB::table('invoice_sequences')->where('series', $series)->update(['last_number' => $next, 'updated_at' => now()]);

        return $next;
    }

    /**
     * A series' counter row (a shop's first invoice creates it). Taking a number then only ever
     * locks an existing row.
     */
    protected function ensureSeries(string $series): void
    {
        if (! DB::table('invoice_sequences')->where('series', $series)->exists()) {
            DB::table('invoice_sequences')->insertOrIgnore(['series' => $series, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /**
     * Write only these columns (never a caller's unsaved edits) and keep the model in step.
     *
     * @param  array<string, mixed>  $values
     */
    protected function persist(Model $model, array $values): void
    {
        $model->forceFill($values);
        $dirty = array_intersect_key($model->getDirty(), $values);
        if ($dirty === []) {
            return;
        }

        $model->newQueryWithoutScopes()->whereKey($model->getKey())->update($dirty);
        $model->syncOriginalAttributes(array_keys($dirty));
    }

    protected static function limit(?string $value, int $length): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }
}
