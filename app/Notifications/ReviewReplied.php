<?php

namespace App\Notifications;

use App\Models\ProductReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the customer the shop has answered their review.
 */
class ReviewReplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ProductReview $review) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $review = $this->review->loadMissing(['product', 'replier']);
        $product = $review->product?->name ?? __('Product');

        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__(':shop replied to your review', ['shop' => $review->replyShopName()]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':shop has replied to your review of :product:', ['shop' => $review->replyShopName(), 'product' => $product]))
            ->line('“'.$review->seller_reply.'”')
            ->action(__('See the reply'), $review->product ? route('products.show', $review->product).'#review-'.$review->id : route('home'));
    }
}
