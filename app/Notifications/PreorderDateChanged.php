<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\PreorderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The shop moved the expected date of the customer's pre-order: the new date, and a link to cancel
 * for a full refund for anyone who would rather not wait. Follows the order-updates preference.
 */
class PreorderDateChanged extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<int>  $itemIds  the order's pre-order lines that took the new date
     */
    public function __construct(public Order $order, public array $itemIds, public string $newDate, public ?string $oldDate = null)
    {
        // Sent only once the surrounding database transaction has committed
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? $notifiable->notificationChannels('order_updates') : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->order->order_number;
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('New date for your pre-order (order :number)', ['number' => $number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? $this->order->customerName()]))
            ->line(__('The shop has a new date for your pre-order:'));

        foreach ($this->items() as $item) {
            $mail->line('• '.$item->displayName().' × '.$item->quantity);
        }

        $mail->line($this->oldDate
            ? __('It is now expected to ship around :date (it was :old).', ['date' => $this->date($this->newDate), 'old' => $this->date($this->oldDate)])
            : __('It is now expected to ship around :date.', ['date' => $this->date($this->newDate)]));

        $preorders = app(PreorderService::class);
        if ($preorders->canCancel($this->order)) {
            $mail->line(__('Rather not wait? You can cancel the order for a full refund: :url', ['url' => $preorders->cancelUrl($this->order)]));
        }

        return $mail->action(__('View your order'), $this->order->customerUrl());
    }

    public function toSms(object $notifiable): string
    {
        return __('iruali: your pre-order (order :number) now ships around :date. To cancel for a full refund, see your order.', ['number' => $this->order->order_number, 'date' => $this->date($this->newDate)]);
    }

    protected function date(string $date): string
    {
        return Carbon::parse($date)->translatedFormat('j M Y');
    }

    /**
     * @return Collection<int, OrderItem>
     */
    protected function items(): Collection
    {
        return OrderItem::whereIn('id', $this->itemIds)->with('product')->orderBy('id')->get();
    }
}
