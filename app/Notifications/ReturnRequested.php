<?php

namespace App\Notifications;

use App\Models\ReturnRequest;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * A customer asked to return items (sent to the shop and to iruali's contact email). Queued; the shop
 * can turn it off under Settings → Notifications, iruali's address always gets it.
 */
class ReturnRequested extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ReturnRequest $request) {}

    public function via(object $notifiable): array
    {
        if (method_exists($notifiable, 'wantsNotification') && ! $notifiable->wantsNotification('return')) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->request;
        $order = $request->order;
        $isShop = $notifiable instanceof \App\Models\User && $notifiable->id === $request->sellerOrder?->seller_id;

        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Return requested on order :number', ['number' => $order->order_number]))
            ->line(__(':customer asked to return items from order :number.', ['customer' => $request->user->name ?? __('A customer'), 'number' => $order->order_number]))
            ->line(__('Reason: :reason', ['reason' => $request->reasonLabel()]));

        foreach ($request->items()->with('orderItem.product')->get() as $line) {
            $mail->line('• '.($line->orderItem?->product?->name ?? __('Product')).' × '.$line->quantity);
        }

        $mail->line(__('Value of the items: :amount', ['amount' => Money::format($request->items_value)]));

        if ($request->details) {
            $mail->line(__('Customer notes: :details', ['details' => $request->details]));
        }

        return $isShop
            ? $mail->line(__('iruali will review the request and let you know. Please keep the items aside if they are sent back to you.'))
                ->action(__('View returns'), route('seller.returns'))
            : $mail->action(__('Review the return'), route('admin.returns.show', $request));
    }
}
