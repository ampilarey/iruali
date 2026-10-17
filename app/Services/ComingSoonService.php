<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockAlert;
use App\Notifications\NowOnSale;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * "Coming soon": a shop lists a product before it goes on sale (product form → "Coming soon"),
 * with an optional launch day. Until then it shows with a "Coming soon" label (and "On sale from
 * <date>"), can't be added to a cart or ordered (CartService, OrderService) and takes no
 * pre-orders; shoppers can ask to be emailed when it goes on sale (stock_alerts.for_launch).
 *
 * It goes on sale when the shop switches "coming soon" off, or by itself on the launch day:
 * Product::isComingSoon() is false from that day, and the daily `products:launch` clears the flag,
 * which emails those who asked (Product::booted() → launched()). If it has no stock then (and no
 * pre-orders), their emails wait for the stock instead (StockAlertService).
 *
 * Switching "coming soon" on or off and its date apply at once, like stock: they don't change what
 * the product is, so they don't wait for a review (ProductReviewService).
 */
class ComingSoonService
{
    /**
     * Rules for the product form's "Coming soon" fieldset; a form without it changes nothing.
     *
     * @return array<string, mixed>
     */
    public static function formRules(): array
    {
        return [
            'coming_soon_form' => 'nullable|boolean',
            'coming_soon' => 'nullable|boolean',
            'available_on' => ['exclude_unless:coming_soon,1', 'nullable', 'date', 'after:today', 'before_or_equal:'.today()->addYear()->toDateString()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function formMessages(): array
    {
        return [
            'available_on.after' => __('The launch day must be after today. Untick "Coming soon" to put it on sale now.'),
            'available_on.before_or_equal' => __('Choose a launch day within the next year.'),
        ];
    }

    /**
     * The product's "coming soon" columns from the validated form (nothing when the form had no
     * fieldset). Switching it off clears the launch day.
     *
     * @param  array<string, mixed>  $data
     * @return array{coming_soon?: bool, available_on?: ?string}
     */
    public function attributesFromForm(array $data): array
    {
        if (! array_key_exists('coming_soon_form', $data)) {
            return [];
        }
        if (empty($data['coming_soon'])) {
            return ['coming_soon' => false, 'available_on' => null];
        }

        return ['coming_soon' => true, 'available_on' => filled($data['available_on'] ?? null) ? (string) $data['available_on'] : null];
    }

    /**
     * The product page's "coming soon" box, or null when it is on sale.
     *
     * @return array{date: ?\Carbon\CarbonInterface}|null
     */
    public function offer(Product $product): ?array
    {
        return $product->isComingSoon() ? ['date' => $product->available_on] : null;
    }

    /**
     * Products whose launch day has come: their "coming soon" is cleared (which emails those who
     * asked). Returns how many went on sale.
     */
    public function launchDue(): int
    {
        $count = 0;
        Product::query()->where('coming_soon', true)->whereNotNull('available_on')->whereDate('available_on', '<=', today())
            ->orderBy('id')->each(function (Product $product) use (&$count) {
                $product->forceFill(['coming_soon' => false])->save();
                $count++;
            });

        return $count;
    }

    /**
     * The product has gone on sale: email everyone who asked, once, if it can be bought now (in
     * stock, or open for pre-orders). Otherwise their emails wait for its stock.
     */
    public function launched(Product $product): void
    {
        if (! $product->is_active || ! $this->buyable($product)) {
            return;
        }

        $this->notify($product);
    }

    /** Email the shoppers waiting for this product to go on sale. */
    public function notify(Product $product): void
    {
        StockAlert::where('product_id', $product->id)->where('for_launch', true)->whereNull('notified_at')->get()
            ->each(function (StockAlert $alert) use ($product) {
                try {
                    Notification::route('mail', $alert->email)->notify((new NowOnSale($product))->locale($alert->locale));
                    $alert->update(['notified_at' => now()]);
                } catch (Throwable $e) {
                    report($e);
                }
            });
    }

    protected function buyable(Product $product): bool
    {
        return $product->effectiveStock() > 0 || app(PreorderService::class)->isPreorderable($product);
    }
}
