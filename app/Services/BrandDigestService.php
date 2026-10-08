<?php

namespace App\Services;

use App\Models\BrandFollow;
use App\Models\CampaignProduct;
use App\Models\Product;
use App\Models\User;
use App\Notifications\BrandFollowDigest;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The daily email to customers who follow brands (php artisan brands:notify-followers): products
 * of the brands they follow that went on sale, or were approved into a sale (campaign) that is
 * running now, since the last email. At most one email per customer, none when there is nothing
 * new, never the same news twice, and only for customers who want it (User::wantsBrandUpdates).
 */
class BrandDigestService
{
    /** Products listed in one email; the rest are behind each brand's link to its page. */
    public const MAX_PRODUCTS = 12;

    /**
     * Email every follower who has news. Returns how many emails went out.
     */
    public function sendAll(): int
    {
        // News up to a second ago: anything saved while this runs goes in the next email instead
        $until = now()->subSecond()->startOfSecond();
        $sent = 0;

        BrandFollow::query()->where('created_at', '<=', $until)->distinct()->orderBy('user_id')->pluck('user_id')
            ->chunk(100)
            ->each(function (Collection $ids) use ($until, &$sent) {
                foreach (User::query()->whereIn('id', $ids)->orderBy('id')->get() as $user) {
                    $sent += $this->sendTo($user, $until) ? 1 : 0;
                }
            });

        return $sent;
    }

    /**
     * One customer's email, covering news up to $until. Their follows are marked as covered whether
     * or not an email went out (switched off, or nothing new), so the same news is never sent twice.
     * Returns whether an email went out.
     */
    public function sendTo(User $user, CarbonInterface $until): bool
    {
        $follows = BrandFollow::query()->where('user_id', $user->id)->where('created_at', '<=', $until)->get();
        if ($follows->isEmpty()) {
            return false;
        }

        $news = $user->wantsBrandUpdates() ? $this->news($follows, $until) : collect();
        if ($news->isNotEmpty()) {
            $totals = [];
            foreach ($news as $item) {
                $totals[$item['brand']] = ($totals[$item['brand']] ?? 0) + 1;
            }

            try {
                $user->notify(new BrandFollowDigest($this->pick($news), $totals));
            } catch (Throwable $e) {
                report($e);

                return false; // the follows stay as they were, so tomorrow's run tries again
            }
        }

        BrandFollow::query()->whereKey($follows->modelKeys())->update(['notified_at' => $until]);

        return $news->isNotEmpty();
    }

    /**
     * What is new for one customer's follows, newest first and one entry per product: products that
     * went on sale (Product::trackSaleStart) and products approved into a campaign that is running
     * now, after each follow's newsSince() and up to $until. Only products on show and in stock.
     *
     * @param  Collection<int, BrandFollow>  $follows
     * @return Collection<int, array{product: int, brand: int, campaign: int|null, at: string}>
     */
    public function news(Collection $follows, CarbonInterface $until): Collection
    {
        $sales = Product::query()->active()->inStock()->onSale()
            ->where('sale_started_at', '<=', $until)
            ->where(function (Builder $any) use ($follows) {
                foreach ($follows as $follow) {
                    $any->orWhere(fn (Builder $one) => $one->where('brand_id', $follow->brand_id)->where('sale_started_at', '>', $follow->newsSince()));
                }
            })
            ->toBase()
            ->get(['id', 'brand_id', 'sale_started_at'])
            ->map(fn (object $row) => ['product' => (int) $row->id, 'brand' => (int) $row->brand_id, 'campaign' => null, 'at' => (string) $row->sale_started_at]);

        // A campaign product counts from when shoppers could first buy it at the campaign price:
        // its approval, or the campaign's start when it was approved before that
        $campaigns = CampaignProduct::query()
            ->join('campaigns', 'campaigns.id', '=', 'campaign_products.campaign_id')
            ->join('products', 'products.id', '=', 'campaign_products.product_id')
            ->whereNotNull('campaign_products.approved_at')
            ->where('campaign_products.approved_at', '<=', $until)
            ->where('campaigns.is_active', true)
            ->where('campaigns.starts_at', '<=', $until)
            ->where('campaigns.ends_at', '>=', now())
            ->where('products.is_active', true)
            ->whereNull('products.deleted_at')
            ->where('products.stock_quantity', '>', 0)
            ->where(function (Builder $any) use ($follows) {
                foreach ($follows as $follow) {
                    $since = $follow->newsSince();
                    $any->orWhere(fn (Builder $one) => $one->where('products.brand_id', $follow->brand_id)
                        ->where(fn (Builder $new) => $new->where('campaign_products.approved_at', '>', $since)->orWhere('campaigns.starts_at', '>', $since)));
                }
            })
            ->orderByDesc('campaign_products.approved_at')
            ->toBase()
            ->get(['campaign_products.product_id', 'campaign_products.campaign_id', 'products.brand_id', 'campaign_products.approved_at', 'campaigns.starts_at'])
            ->map(fn (object $row) => [
                'product' => (int) $row->product_id,
                'brand' => (int) $row->brand_id,
                'campaign' => (int) $row->campaign_id,
                'at' => max((string) $row->approved_at, (string) $row->starts_at),
            ]);

        // A product in a sale and a campaign is listed once, with the campaign
        return $campaigns->concat($sales)->unique('product')->sortByDesc('at')->values();
    }

    /**
     * The products to list: at most MAX_PRODUCTS, taken in turns from each brand (newest first), so
     * every brand with news shows something before one brand fills the email.
     *
     * @param  Collection<int, array{product: int, brand: int, campaign: int|null, at: string}>  $news  newest first
     * @return list<array{product: int, campaign: int|null}>
     */
    protected function pick(Collection $news): array
    {
        $byBrand = $news->groupBy('brand')->map(fn (Collection $items) => $items->values()->all())->values()->all();
        $picked = [];

        for ($round = 0; count($picked) < self::MAX_PRODUCTS; $round++) {
            $taken = false;
            foreach ($byBrand as $items) {
                if (isset($items[$round]) && count($picked) < self::MAX_PRODUCTS) {
                    $picked[] = ['product' => $items[$round]['product'], 'campaign' => $items[$round]['campaign']];
                    $taken = true;
                }
            }
            if (! $taken) {
                break;
            }
        }

        return $picked;
    }
}
