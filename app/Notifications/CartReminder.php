<?php

namespace App\Notifications;

use App\Models\Cart;
use App\Models\Voucher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * "You left something in your cart": the items with pictures, a link back to the cart and,
 * on the second nudge, a voucher if the owner set one up.
 */
class CartReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Cart $cart, public int $stage = 1, public ?Voucher $voucher = null) {}

    public function via(object $notifiable): array
    {
        // By SMS too (or instead) when it is switched on and the customer chose SMS for offers
        $sms = filter_var(\App\Models\Setting::get('abandoned_cart_sms_enabled', 0), FILTER_VALIDATE_BOOL);

        return $sms && method_exists($notifiable, 'notificationChannels') ? $notifiable->notificationChannels('marketing') : ['mail'];
    }

    public function toSms(object $notifiable): \App\Services\Sms\SmsText
    {
        return $this->voucher
            ? \App\Support\SmsTypes::render('cart_reminder_offer', ['percent' => self::percent($this->voucher), 'code' => $this->voucher->code, 'date' => $this->voucher->valid_until?->translatedFormat('j M'), 'url' => route('cart')])
            : \App\Support\SmsTypes::render('cart_reminder', ['url' => route('cart')]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cart = $this->cart->loadMissing(['items.product.mainImage', 'items.variant']);
        $items = $cart->items->whereNotNull('product');

        $subject = $this->voucher
            ? __('Your cart is waiting, with :percent% off', ['percent' => self::percent($this->voucher)])
            : __('You left something in your cart');

        return (new MailMessage)
            ->subject($subject)
            ->markdown('mail.cart-reminder', [
                'name' => $notifiable->name,
                'items' => $items,
                'stage' => $this->stage,
                'voucher' => $this->voucher,
                'percent' => $this->voucher ? self::percent($this->voucher) : null,
                'cartUrl' => route('cart'),
                'unsubscribeUrl' => URL::signedRoute('marketing.unsubscribe', ['user' => $notifiable->id]),
            ]);
    }

    /** "10" rather than "10.00". */
    public static function percent(Voucher $voucher): string
    {
        return rtrim(rtrim(number_format((float) $voucher->amount, 2, '.', ''), '0'), '.');
    }
}
