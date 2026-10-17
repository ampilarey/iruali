<?php

namespace App\Notifications;

use App\Models\ProductChangeRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * iruali has reviewed an edit a shop made to a live product (Admin → Products → Changes waiting):
 * approved, so shoppers now see it, or rejected with the reason, so the product stays as it was.
 * Always sent: it is about the shop's listings, not one of the shop emails it can switch off.
 */
class ProductChangeReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ProductChangeRequest $change) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->change->product;
        $name = $product ? $product->getTranslation('name', 'en', false) : __('your product');
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->greeting(__('Hello :name,', ['name' => $notifiable instanceof User ? $notifiable->shopName() : '']));

        if ($this->change->status === ProductChangeRequest::APPROVED) {
            return $mail->subject(__('Your changes to :product are live', ['product' => $name]))
                ->line(__('We have approved the changes you made to :product. Shoppers see the new version now.', ['product' => $name]))
                ->action(__('View your products'), route('seller.products.index'));
        }

        return $mail->subject(__('Your changes to :product were not approved', ['product' => $name]))
            ->line(__('We have looked at the changes you made to :product and could not approve them. Shoppers still see the version that was live before.', ['product' => $name]))
            ->line(__('Reason: :reason', ['reason' => (string) $this->change->rejection_reason]))
            ->line(__('You can edit the product again and save it to send new changes for review.'))
            ->action(__('Edit the product'), $product ? route('seller.products.edit', $product) : route('seller.products.index'));
    }
}
