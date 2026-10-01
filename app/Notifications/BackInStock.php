<?php

namespace App\Notifications;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class BackInStock extends Notification implements ShouldQueue
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
            ->subject(__(':product is back in stock', ['product' => $this->product->name]))
            ->line(__('Good news: :product is back in stock at :price.', ['product' => $this->product->name, 'price' => Money::format($this->product->final_price)]))
            ->line(__('Stock can go quickly, so order soon if you still want it.'))
            ->action(__('View product'), route('products.show', $this->product));
    }
}
