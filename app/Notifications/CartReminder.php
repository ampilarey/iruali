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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cart = $this->cart->loadMissing(['items.product.mainImage', 'items.variant']);
        $items = $cart->items->filter(fn ($item) => $item->product);

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
