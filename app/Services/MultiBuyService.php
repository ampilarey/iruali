<?php

namespace App\Services;

use App\Models\MultiBuyOffer;
use App\Models\Product;
use App\Models\ShopDiscountCode;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

/**
 * Multi-buy offers, set on the shop's product form: up to three quantity tiers ("Buy 2, save 5%"),
 * for this product only or for a mix-and-match group of the shop's products, whose units count
 * together. The shop pays for the saving, like a discount code; the cart applies it by itself
 * (ShopDiscountService), before any shop code.
 *
 * Form fields: multibuy[tiers][n][min_qty|percent]; multibuy[group]: '' for this product only,
 * 'new', or the id of one of the shop's groups; multibuy[group_name] for a new group. With a group
 * chosen, the tiers entered become the group's tiers (left empty, the group keeps its own).
 */
class MultiBuyService
{
    /**
     * @return array<string, string>
     */
    public static function formRules(): array
    {
        return [
            'multibuy' => 'nullable|array',
            'multibuy.tiers' => 'nullable|array|max:'.MultiBuyOffer::MAX_TIERS,
            'multibuy.tiers.*.min_qty' => 'nullable|integer|min:2|max:999',
            'multibuy.tiers.*.percent' => 'nullable|numeric|min:1|max:90',
            'multibuy.group' => 'nullable|string|max:20',
            'multibuy.group_name' => 'nullable|string|max:80',
        ];
    }

    /**
     * The checks the field rules can't make: complete tiers that go up, and a group that is the shop's.
     *
     * @param  array<string, mixed>  $input  the multibuy[...] fields
     */
    public function validateForm(Validator $validator, ?User $seller, array $input): void
    {
        if ($input === [] || collect($validator->errors()->keys())->contains(fn ($key) => str_starts_with($key, 'multibuy'))) {
            return; // nothing sent, or the field rules already failed
        }

        $rows = collect((array) ($input['tiers'] ?? []))->filter(fn ($row) => is_array($row) && (filled($row['min_qty'] ?? null) || filled($row['percent'] ?? null)));
        if ($rows->contains(fn ($row) => blank($row['min_qty'] ?? null) || blank($row['percent'] ?? null))) {
            $validator->errors()->add('multibuy.tiers', __('Each multi-buy tier needs a quantity and a discount.'));

            return;
        }

        $tiers = $this->tiersFromInput((array) ($input['tiers'] ?? []));
        if (count($tiers) !== $rows->count() || ! $this->tiersGoUp($tiers)) {
            $validator->errors()->add('multibuy.tiers', __('Each multi-buy tier needs a bigger quantity and a bigger discount than the one before.'));
        }

        $group = trim((string) ($input['group'] ?? ''));
        if ($group === 'new') {
            $name = trim((string) ($input['group_name'] ?? ''));
            if ($name === '') {
                $validator->errors()->add('multibuy.group_name', __('Give the new mix-and-match group a name.'));
            } elseif ($seller && MultiBuyOffer::where('seller_id', $seller->id)->whereNotNull('name')->get()->contains(fn ($offer) => mb_strtolower((string) $offer->name) === mb_strtolower($name))) {
                $validator->errors()->add('multibuy.group_name', __('You already have a mix-and-match group called :name.', ['name' => $name]));
            }
            if ($tiers === []) {
                $validator->errors()->add('multibuy.tiers', __('Add at least one tier for the new group.'));
            }
        } elseif ($group !== '' && (! ctype_digit($group) || ! $seller || ! MultiBuyOffer::where('seller_id', $seller->id)->whereNotNull('name')->whereKey((int) $group)->exists())) {
            $validator->errors()->add('multibuy.group', __('Choose one of your own mix-and-match groups.'));
        }
    }

    /**
     * The filled tier rows, cleaned and smallest quantity first.
     *
     * @param  array<mixed>  $rows
     * @return list<array{min_qty: int, percent: float}>
     */
    public function tiersFromInput(array $rows): array
    {
        $tiers = [];
        foreach ($rows as $row) {
            if (! is_array($row) || blank($row['min_qty'] ?? null) || blank($row['percent'] ?? null)) {
                continue;
            }
            $tiers[(int) $row['min_qty']] = ['min_qty' => (int) $row['min_qty'], 'percent' => round((float) $row['percent'], 2)];
        }
        ksort($tiers);

        return array_values(array_slice($tiers, 0, MultiBuyOffer::MAX_TIERS));
    }

    /**
     * @param  list<array{min_qty: int, percent: float}>  $tiers
     */
    protected function tiersGoUp(array $tiers): bool
    {
        for ($i = 1; $i < count($tiers); $i++) {
            if ($tiers[$i]['percent'] <= $tiers[$i - 1]['percent']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Save the product form's multi-buy fieldset (already validated). The product's own offer is
     * kept up to date; an offer no product uses any more is deleted.
     *
     * @param  array<string, mixed>  $input  the multibuy[...] fields
     */
    public function saveFromForm(Product $product, array $input): void
    {
        if (! $product->seller_id) {
            return;
        }

        $tiers = $this->tiersFromInput((array) ($input['tiers'] ?? []));
        $group = trim((string) ($input['group'] ?? ''));
        $previous = $product->multibuyOffer;

        DB::transaction(function () use ($product, $tiers, $group, $input, $previous) {
            $offer = null;
            if ($group === 'new') {
                $offer = new MultiBuyOffer(['name' => trim((string) ($input['group_name'] ?? '')), 'tiers' => $tiers]);
            } elseif (ctype_digit($group)) {
                $offer = MultiBuyOffer::where('seller_id', $product->seller_id)->whereNotNull('name')->findOrFail((int) $group);
                if ($tiers !== []) {
                    $offer->tiers = $tiers;
                }
            } elseif ($tiers !== []) {
                $offer = $previous && ! $previous->isShared() ? $previous : new MultiBuyOffer;
                $offer->fill(['name' => null, 'tiers' => $tiers]);
            }

            if ($offer) {
                $offer->seller_id = $product->seller_id;
                $offer->save();
            }

            $product->forceFill(['multibuy_offer_id' => $offer?->id])->saveQuietly();
            $product->setRelation('multibuyOffer', $offer);

            if ($previous && $previous->id !== $offer?->id && ! $previous->products()->exists()) {
                $previous->delete();
            }
        });
    }

    /**
     * The shop's mix-and-match groups, with how many products are in each.
     *
     * @return Collection<int, MultiBuyOffer>
     */
    public function groupsFor(User $seller): Collection
    {
        return MultiBuyOffer::where('seller_id', $seller->id)->whereNotNull('name')->withCount('products')->orderBy('name')->get();
    }

    /**
     * "Buy 2, save 5%".
     *
     * @param  array{min_qty: int, percent: float}  $tier
     */
    public static function tierLabel(array $tier): string
    {
        return __('Buy :qty, save :percent%', ['qty' => $tier['min_qty'], 'percent' => ShopDiscountCode::percentText($tier['percent'])]);
    }
}
