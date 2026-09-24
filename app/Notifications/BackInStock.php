<?php

namespace App\Notifications;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackInStock extends Notification
{
    public function __construct(public Product $product) {}

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
