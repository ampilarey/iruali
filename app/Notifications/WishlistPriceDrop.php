<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Once a day (wishlist:price-drops, see WishlistPriceDropService): products on the customer's
 * wishlist that got cheaper, with the price before and now. By email, and as a push on devices
 * that allowed notifications. Written in the customer's language (User::preferredLocale).
 */
class WishlistPriceDrop extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{wishlist: int, product: int, variant: int|null, was: float, now: float}>  $drops  biggest drop first
     */
    public function __construct(public array $drops) {}

    public function via(object $notifiable): array
    {
        // Switched off while this waited in the queue: nothing goes out
        if (! $notifiable instanceof User) {
            return ['mail'];
        }
        if (! $notifiable->wantsWishlistPriceDrops()) {
            return [];
        }

        $channels = ['mail'];
        if (WebPushChannel::configured() && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /** @return list<int> */
    public function productIds(): array
    {
        return array_column($this->drops, 'product');
    }

    /**
     * The listed products with their prices, in order (a product removed since is left out).
     *
     * @return list<array{product: Product, variant: ProductVariant|null, was: float, now: float}>
     */
    public function lines(): array
    {
        $products = Product::query()->with('mainImage')->whereIn('id', $this->productIds())->get()->keyBy('id');
        $variants = ProductVariant::query()->whereIn('id', array_filter(array_column($this->drops, 'variant')))->get()->keyBy('id');

        $lines = [];
        foreach ($this->drops as $drop) {
            if ($product = $products->get($drop['product'])) {
                $lines[] = ['product' => $product, 'variant' => $drop['variant'] ? $variants->get($drop['variant']) : null, 'was' => $drop['was'], 'now' => $drop['now']];
            }
        }

        return $lines;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lines = $this->lines();
        $first = $lines[0]['product'] ?? null;

        $subject = count($lines) === 1 && $first
            ? __('Price drop: :product', ['product' => $first->name])
            : __('Prices dropped on your wishlist');

        return (new MailMessage)
            ->subject($subject)
            ->markdown('mail.wishlist-price-drop', [
                'name' => $notifiable instanceof User ? $notifiable->name : null,
                'lines' => $lines,
                'wishlistUrl' => route('wishlist'),
                'settingsUrl' => route('account.notifications'),
            ]);
    }

    /**
     * Browser notification: the product when there is one, else how many got cheaper.
     */
    public function toWebPush(object $notifiable): array
    {
        $lines = $this->lines();
        $body = count($lines) === 1
            ? __(':product is now :price (was :was).', ['product' => $lines[0]['product']->name, 'price' => Money::format($lines[0]['now']), 'was' => Money::format($lines[0]['was'])])
            : __(':count items on your wishlist are cheaper now.', ['count' => count($lines)]);

        return [
            'title' => __('Price drop on your wishlist'),
            'body' => $body,
            'url' => route('wishlist'),
            'icon' => asset('images/icons/icon-192.png'),
            'tag' => 'wishlist-price-drop',
        ];
    }
}
