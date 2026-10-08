<?php

namespace App\Notifications;

use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Product;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Once a day (brands:notify-followers, see BrandDigestService): what the brands a customer follows
 * put on sale, or into a running sale, since the last email. Grouped by brand, at most
 * BrandDigestService::MAX_PRODUCTS products, each brand with a link to its page for the rest.
 * Written in the customer's language (User::preferredLocale).
 */
class BrandFollowDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{product: int, campaign: int|null}>  $items  the products to list, newest first
     * @param  array<int, int>  $totals  brand id => how many of its products are new, listed or not
     */
    public function __construct(public array $items, public array $totals) {}

    public function via(object $notifiable): array
    {
        // Switched off while this waited in the queue: nothing goes out
        return $notifiable instanceof User && ! $notifiable->wantsBrandUpdates() ? [] : ['mail'];
    }

    /** @return list<int> */
    public function productIds(): array
    {
        return array_column($this->items, 'product');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $products = Product::query()->active()->with('mainImage')->whereIn('id', $this->productIds())->get()->keyBy('id');
        $campaigns = Campaign::query()->whereIn('id', array_filter(array_column($this->items, 'campaign')))->get()->keyBy('id');
        $brands = Brand::query()->whereIn('id', array_keys($this->totals))->get()->keyBy('id');

        // One section per brand, in the order the news came in
        $groups = [];
        foreach ($this->totals as $brandId => $total) {
            if ($brand = $brands->get($brandId)) {
                $groups[$brandId] = ['brand' => $brand, 'lines' => [], 'total' => $total];
            }
        }
        foreach ($this->items as $item) {
            $product = $products->get($item['product']);
            if ($product && isset($groups[$product->brand_id])) {
                $groups[$product->brand_id]['lines'][] = ['product' => $product, 'campaign' => $item['campaign'] ? $campaigns->get($item['campaign']) : null];
            }
        }

        $names = array_map(fn (array $group) => $group['brand']->localizedName(), array_values($groups));
        $subject = count($names) === 1
            ? __('New deals from :brand', ['brand' => $names[0]])
            : __('New deals from brands you follow');

        return (new MailMessage)
            ->subject($subject)
            ->markdown('mail.brand-digest', [
                'name' => $notifiable instanceof User ? $notifiable->name : null,
                'listed' => array_values(array_filter($groups, fn (array $group) => $group['lines'] !== [])),
                'others' => array_values(array_filter($groups, fn (array $group) => $group['lines'] === [])),
                'followingUrl' => route('account.brands'),
                'settingsUrl' => route('account.notifications'),
            ]);
    }
}
