<?php

namespace App\Notifications;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * A "coming soon" product the shopper asked about has gone on sale (App\Services\ComingSoonService).
 */
class NowOnSale extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Product $product)
    {
        // Sent only once the surrounding database transaction has committed
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__(':product is now on sale', ['product' => $this->product->name]))
            ->line(__('You asked us to tell you: :product is now on sale at :price.', ['product' => $this->product->name, 'price' => Money::format($this->product->final_price)]))
            ->line(__('New arrivals can go quickly, so order soon if you want one.'))
            ->action(__('View product'), route('products.show', $this->product));
    }
}
