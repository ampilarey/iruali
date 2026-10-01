<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Once a day (08:00 Maldives time): the shop's products and variants at or below their low-stock
 * threshold. Only sent when there is something to list.
 */
class LowStockDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /** Used when a product has no reorder point of its own. */
    public const DEFAULT_THRESHOLD = 3;

    /**
     * @param  array<int, array{name: string, sku: ?string, stock: int, threshold: int, url: string}>  $lines
     */
    public function __construct(public array $lines) {}

    public function via(object $notifiable): array
    {
        if (method_exists($notifiable, 'wantsNotification') && ! $notifiable->wantsNotification('low_stock')) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(trans_choice(':count item is running low|:count items are running low', count($this->lines), ['count' => count($this->lines)]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->business_name ?: $notifiable->name]))
            ->line(__('These items are at or below their low-stock level. Restock them or they will show as sold out:'));

        foreach ($this->lines as $line) {
            $mail->line('• '.$line['name'].($line['sku'] ? ' ('.$line['sku'].')' : '').' — '.__(':stock left, low-stock level :threshold', ['stock' => $line['stock'], 'threshold' => $line['threshold']]));
        }

        return $mail->line(__('You can change the low-stock level of each product when you edit it, or turn this email off under Settings → Notifications.'))
            ->action(__('Open your products'), route('seller.products.index'));
    }
}
