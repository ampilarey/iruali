<?php

namespace App\Support;

use App\Models\SmsTypeSetting;
use App\Services\Sms\SmsText;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Every text message iruali sends, in one list (Admin → SMS → Messages): what it is for, its built-in
 * wording, the {placeholders} it can use with an example, and whether it is urgent (sent at night
 * and over the monthly limit, e.g. a verification code).
 *
 * The built-in wording is the English source text of the translation (`:number` style), so Dhivehi
 * comes from dv.json as before. An admin can switch a message off, or replace its wording in
 * English and/or Dhivehi with `{number}` style placeholders (SmsTypeSetting). A notification builds
 * its text with SmsTypes::render(type, data); an empty text means "not sent".
 */
class SmsTypes
{
    public const GROUPS = [
        'account' => 'Account',
        'orders' => 'Orders',
        'delivery' => 'Delivery and pickup',
        'preorders' => 'Pre-orders',
        'messages' => 'Messages',
        'quotes' => 'Bulk quotes',
        'disputes' => 'Disputes',
        'marketing' => 'Offers',
    ];

    /**
     * text: the built-in English wording (also the translation key); data: placeholder => example;
     * required: placeholders a custom wording must keep; extra: a placeholder added after the text
     * when a custom wording leaves it out.
     *
     * @return array<string, array{group: string, label: string, text: string, data: array<string, string>, required?: list<string>, urgent?: bool, extra?: string}>
     */
    public static function all(): array
    {
        $order = ['number' => 'ORD-7K2M9QX'];

        return [
            'phone_code' => [
                'group' => 'account', 'label' => 'Phone verification code', 'urgent' => true,
                'text' => 'iruali: your verification code is :code. It expires in 10 minutes.',
                'data' => ['code' => '482913'], 'required' => ['code'],
            ],
            'login_code' => [
                'group' => 'account', 'label' => 'Sign-in code', 'urgent' => true,
                'text' => 'iruali: your sign-in code is :code. It expires in 10 minutes. Never share it with anyone.',
                'data' => ['code' => '615204'], 'required' => ['code'],
            ],
            'payment_link' => [
                'group' => 'orders', 'label' => 'Payment link',
                'text' => 'iruali: to pay :amount for order :number, open :url (works for 48 hours).',
                'data' => ['number' => 'ORD-7K2M9QX', 'amount' => 'MVR 450.00', 'url' => 'https://iruali.mv/pay/123?signature=…'], 'required' => ['url'],
            ],
            'order_placed' => [
                'group' => 'orders', 'label' => 'Order placed',
                'text' => 'iruali: we have your order :number (:amount). We will text you when it is on its way.',
                'data' => $order + ['amount' => 'MVR 450.00'],
            ],
            'payment_received' => [
                'group' => 'orders', 'label' => 'Payment received',
                'text' => 'iruali: payment of :amount received for order :number. Thank you.',
                'data' => $order + ['amount' => 'MVR 450.00'],
            ],
            'order_processing' => [
                'group' => 'orders', 'label' => 'Order being prepared',
                'text' => 'iruali: order :number is being prepared by the shop.', 'data' => $order,
            ],
            'order_cancelled' => [
                'group' => 'orders', 'label' => 'Order cancelled',
                'text' => 'iruali: order :number has been cancelled.', 'data' => $order,
            ],
            'order_status' => [
                'group' => 'orders', 'label' => 'Order status changed (other)',
                'text' => 'iruali: order :number is now :status.', 'data' => $order + ['status' => 'pending'],
            ],
            'order_shipped' => [
                'group' => 'delivery', 'label' => 'Order on its way', 'extra' => 'details',
                'text' => 'iruali: order :number is on its way.',
                'data' => $order + ['details' => 'Reef Goods: by boat, expected 24 Oct.'],
            ],
            'order_out_for_delivery' => [
                'group' => 'delivery', 'label' => 'Out for delivery today', 'extra' => 'details',
                'text' => 'iruali: order :number is out for delivery today.',
                'data' => $order + ['details' => 'Reef Goods: courier Raajje Express.'],
            ],
            'order_delivered' => [
                'group' => 'delivery', 'label' => 'Order delivered',
                'text' => 'iruali: order :number has been delivered. Enjoy!', 'data' => $order,
            ],
            'part_shipped' => [
                'group' => 'delivery', 'label' => 'A shop sent its part', 'extra' => 'details',
                'text' => 'iruali: :shop has sent their part of order :number.',
                'data' => $order + ['shop' => 'Reef Goods', 'details' => 'Tracking RX123456.'],
            ],
            'pickup_ready' => [
                'group' => 'delivery', 'label' => 'Ready for pickup (with the code)',
                'text' => 'iruali: order :number is ready for pickup at :shop, :address. Your pickup code is :code.',
                'data' => $order + ['shop' => 'Reef Goods', 'address' => 'M. Blue Villa, Malé', 'code' => '731904'], 'required' => ['code'],
            ],
            'preorder_arrived' => [
                'group' => 'preorders', 'label' => 'Pre-order stock arrived',
                'text' => 'iruali: the stock for your pre-order (order :number) has arrived. The shop will send it soon.', 'data' => $order,
            ],
            'preorder_date_changed' => [
                'group' => 'preorders', 'label' => 'Pre-order date moved',
                'text' => 'iruali: your pre-order (order :number) now ships around :date. To cancel for a full refund, see your order.',
                'data' => $order + ['date' => '12 Nov 2026'],
            ],
            'booking_fee_received' => [
                'group' => 'preorders', 'label' => 'Booking fee received',
                'text' => 'iruali: booking fee :amount received for order :number. We will text you when your item arrives.',
                'data' => $order + ['amount' => 'MVR 1,500.00'],
            ],
            'booking_balance_due' => [
                'group' => 'preorders', 'label' => 'Pre-order arrived: pay the balance',
                'text' => 'iruali: your pre-order (order :number) has arrived. Please pay the balance of :amount by :date: :url',
                'data' => $order + ['amount' => 'MVR 3,500.00', 'date' => '12 Nov', 'url' => 'https://iruali.mv/pay/123?signature=…'], 'required' => ['url'],
            ],
            'booking_balance_reminder' => [
                'group' => 'preorders', 'label' => 'Balance reminder',
                'text' => 'iruali: reminder: please pay the balance of :amount for order :number by :date: :url',
                'data' => $order + ['amount' => 'MVR 3,500.00', 'date' => '12 Nov', 'url' => 'https://iruali.mv/pay/123?signature=…'], 'required' => ['url'],
            ],
            'preorder_confirmed' => [
                'group' => 'preorders', 'label' => 'Pre-order confirmed by the shop: please pay',
                'text' => 'iruali: good news, the shop can get your pre-order (order :number). Please pay :amount by :date: :url',
                'data' => $order + ['amount' => 'MVR 5,000.00', 'date' => '12 Nov', 'url' => 'https://iruali.mv/pay/123?signature=…'], 'required' => ['url'],
            ],
            'preorder_pay_reminder' => [
                'group' => 'preorders', 'label' => 'Confirmed pre-order: payment reminder',
                'text' => 'iruali: reminder: please pay :amount for order :number by :date, or it is cancelled: :url',
                'data' => $order + ['amount' => 'MVR 5,000.00', 'date' => '12 Nov', 'url' => 'https://iruali.mv/pay/123?signature=…'], 'required' => ['url'],
            ],
            'preorder_unavailable' => [
                'group' => 'preorders', 'label' => 'The shop can\'t get a pre-order',
                'text' => 'iruali: sorry, the shop can\'t get your pre-order (order :number), so it is cancelled. You have not been charged.', 'data' => $order,
            ],
            'new_message' => [
                'group' => 'messages', 'label' => 'New message about an order',
                'text' => 'iruali: new message from :sender about order :number. Reply at :url',
                'data' => $order + ['sender' => 'Reef Goods', 'url' => 'https://iruali.mv/orders/123'],
            ],
            'quote_received' => [
                'group' => 'quotes', 'label' => 'Quote received',
                'text' => 'iruali: :shop sent you a quote for :product: :total, until :date. :url',
                'data' => ['shop' => 'Reef Goods', 'product' => 'Coir rope, 10 m', 'total' => 'MVR 3,200.00', 'date' => '30 Oct', 'url' => 'https://iruali.mv/quotes/12'],
            ],
            'quote_message' => [
                'group' => 'quotes', 'label' => 'Message about a quote',
                'text' => 'iruali: new message from :sender about :label. Reply at :url',
                'data' => ['sender' => 'Reef Goods', 'label' => 'quote request #12', 'url' => 'https://iruali.mv/quotes/12'],
            ],
            'quote_declined' => [
                'group' => 'quotes', 'label' => 'Quote request declined',
                'text' => 'iruali: your quote request #:number for :product was declined. :url',
                'data' => ['number' => '12', 'product' => 'Coir rope, 10 m', 'url' => 'https://iruali.mv/quotes/12'],
            ],
            'birthday_offer' => [
                'group' => 'marketing', 'label' => 'Birthday offer',
                'text' => 'iruali: happy birthday! :percent% off your next order with code :code, until :date.',
                'data' => ['percent' => '10', 'code' => 'BDAY-7Q2K', 'date' => '24 Oct'], 'required' => ['code'],
            ],
            'cart_reminder' => [
                'group' => 'marketing', 'label' => 'Items left in the cart',
                'text' => 'iruali: you left items in your cart. Finish your order: :url',
                'data' => ['url' => 'https://iruali.mv/cart'],
            ],
            'cart_reminder_offer' => [
                'group' => 'marketing', 'label' => 'Items left in the cart, with a code',
                'text' => 'iruali: your cart is waiting with :percent% off, code :code until :date: :url',
                'data' => ['percent' => '10', 'code' => 'BACK-7Q2K9X', 'date' => '31 Oct', 'url' => 'https://iruali.mv/cart'], 'required' => ['code'],
            ],
            'dispute_refund' => [
                'group' => 'disputes', 'label' => 'Dispute decided: refund',
                'text' => 'iruali: your dispute on order :number is resolved. A refund of :amount is on its way.',
                'data' => $order + ['amount' => 'MVR 120.00'],
            ],
            'dispute_rejected' => [
                'group' => 'disputes', 'label' => 'Dispute decided: not upheld',
                'text' => 'iruali: your dispute on order :number was reviewed and not upheld. See the order page for the reason.', 'data' => $order,
            ],
        ];
    }

    /**
     * @return array{group: string, label: string, text: string, data: array<string, string>, required?: list<string>, urgent?: bool, extra?: string}
     */
    public static function get(string $type): array
    {
        return self::all()[$type] ?? throw new \InvalidArgumentException("Unknown SMS type {$type}");
    }

    public static function urgent(string $type): bool
    {
        return (bool) (self::all()[$type]['urgent'] ?? false);
    }

    /**
     * The built-in wording with {placeholders}, as shown to admins.
     */
    public static function builtIn(string $type, string $locale = 'en'): string
    {
        $text = $locale === 'en' ? self::get($type)['text'] : __(self::get($type)['text'], [], $locale);

        return (string) preg_replace('/:([a-z_]+)/', '{$1}', $text);
    }

    /**
     * The message for this type in the current language, or an empty text when it is switched off.
     *
     * @param  array<string, string|int|float|null>  $data
     */
    public static function render(string $type, array $data): SmsText
    {
        $definition = self::get($type);
        $setting = self::settings()[$type] ?? null;
        if ($setting && ! $setting['enabled']) {
            return new SmsText($type, '');
        }

        $data = array_map(fn ($value) => trim((string) $value), $data);
        $extra = $definition['extra'] ?? null;
        $custom = trim((string) ($setting['template_'.(app()->getLocale() === 'dv' ? 'dv' : 'en')] ?? ''));

        if ($custom !== '') {
            $text = strtr($custom, collect($data)->mapWithKeys(fn ($value, $key) => ['{'.$key.'}' => $value])->all());
            $appendExtra = $extra && ! str_contains($custom, '{'.$extra.'}');
        } else {
            $text = __($definition['text'], collect($data)->except($extra ? [$extra] : [])->all());
            $appendExtra = (bool) $extra;
        }

        if ($appendExtra && ($data[$extra] ?? '') !== '') {
            $text .= ' '.$data[$extra];
        }

        return new SmsText($type, trim((string) preg_replace('/\{[a-z_]+\}/', '', $text)), self::urgent($type));
    }

    /**
     * An example of this message with made-up values (admin preview and test).
     */
    public static function example(string $type, string $locale = 'en'): SmsText
    {
        $previous = app()->getLocale();
        app()->setLocale($locale);
        try {
            $text = self::render($type, self::get($type)['data']);
        } finally {
            app()->setLocale($previous);
        }

        return $text;
    }

    /**
     * Saved switches and wordings, by type.
     *
     * @return array<string, array{enabled: bool, template_en: ?string, template_dv: ?string}>
     */
    public static function settings(): array
    {
        try {
            return Cache::rememberForever('sms_type_settings', fn () => SmsTypeSetting::query()->get()
                ->mapWithKeys(fn (SmsTypeSetting $s) => [$s->key => ['enabled' => (bool) $s->enabled, 'template_en' => $s->template_en, 'template_dv' => $s->template_dv]])
                ->all());
        } catch (Throwable) {
            return []; // before the migration has run
        }
    }

    public static function forget(): void
    {
        Cache::forget('sms_type_settings');
    }
}
