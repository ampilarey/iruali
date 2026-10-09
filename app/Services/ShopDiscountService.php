<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\ShopDiscountCode;
use App\Models\ShopDiscountRedemption;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Session;
use LogicException;
use RuntimeException;

/**
 * Shop-funded discounts: multi-buy offers and shop discount codes.
 *
 * How an order's discounts are taken, in this order (amounts are worked in laari, whole numbers, so
 * nothing drifts; every amount shown or stored is MVR to 2 decimals):
 *  1. Each line is priced at today's unit price: a live campaign or markdown price is the base.
 *  2. Multi-buy: a line whose product has an offer gets the percent of the best tier reached by the
 *     units counted together (a product's variants, and every product of a mix-and-match group),
 *     rounded half up per line.
 *  3. Shop codes, at most one per shop: the code's percent of the shop's eligible lines after their
 *     multi-buy savings (rounded half up), or its fixed amount but never more than those lines. The
 *     code's discount is shared over the eligible lines in proportion to what they cost after
 *     multi-buy, by the largest-remainder method (each share rounded down to the laari, the laari
 *     left over go to the lines with the biggest remainders), so the shares add up to it exactly.
 *  4. DiscountService then takes the iruali voucher off what is left (its minimum order is checked
 *     on that too), then loyalty points (at most what is left). Nothing goes below zero.
 *
 * The shop pays for 2 and 3: each order item keeps its multibuy_discount and shop_code_discount,
 * each shop's part sums them into shop_discount, and commission and earnings are worked out on the
 * part's subtotal less shop_discount (FulfilmentService::refreshPart). Returns refund what the
 * customer actually paid for the returned units (ReturnService).
 *
 * The codes a shopper applied are kept in the session, one per shop: [seller_id => CODE].
 */
class ShopDiscountService
{
    public const SESSION_KEY = 'shop_codes';

    /** Codes taken off the cart because they stopped working, with the reason, for the next page. */
    public const NOTICES_KEY = 'shop_code_notices';

    // ---- The codes in the shopper's session -----------------------------------------------------

    /**
     * @return array<int, string> seller_id => code
     */
    public function appliedCodes(): array
    {
        $codes = [];
        foreach ((array) Session::get(self::SESSION_KEY, []) as $sellerId => $code) {
            if ((int) $sellerId > 0 && is_string($code) && $code !== '') {
                $codes[(int) $sellerId] = $code;
            }
        }

        return $codes;
    }

    public function forgetCode(int $sellerId): void
    {
        $codes = $this->appliedCodes();
        unset($codes[$sellerId]);
        $codes ? Session::put(self::SESSION_KEY, $codes) : Session::forget(self::SESSION_KEY);
    }

    public function forgetAll(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Why codes were taken off the cart since the shopper last looked (shown once).
     *
     * @return array<int, string>
     */
    public function pullNotices(): array
    {
        return (array) Session::pull(self::NOTICES_KEY, []);
    }

    /**
     * The cart page's "Shop code" box. The code is looked up among the shops with items in the cart
     * (codes are unique per shop, so it may belong to more than one of them: each gets its own).
     * It replaces any code already applied for that shop.
     *
     * @return array{ok: bool, message: string}
     */
    public function apply(Cart $cart, string $input, ?User $user): array
    {
        $text = ShopDiscountCode::normalize($input);
        $cart->loadMissing('items.product');
        $sellerIds = $cart->items->map(fn ($item) => $item->product?->seller_id)->filter()->unique()->values();

        $matches = $text === '' ? collect() : ShopDiscountCode::whereIn('seller_id', $sellerIds)->where('code', $text)->get();
        if ($matches->isEmpty()) {
            return ['ok' => false, 'message' => Voucher::where('code', trim($input))->exists()
                ? __('That is an iruali voucher. Please enter it under "Voucher code".')
                : __('The shop code :code is not valid for anything in your cart.', ['code' => $text])];
        }

        $codes = $this->appliedCodes();
        $trial = $codes;
        foreach ($matches as $match) {
            $trial[(int) $match->seller_id] = $match->code;
        }
        $deals = $this->evaluate($cart, $trial, $user);

        $shops = [];
        $problem = null;
        foreach ($matches as $match) {
            $sellerId = (int) $match->seller_id;
            if (isset($deals['codes'][$sellerId])) {
                $codes[$sellerId] = $match->code;
                $shops[] = $deals['codes'][$sellerId]['shop'];
            } else {
                $problem ??= $deals['errors'][$sellerId]['message'] ?? null;
            }
        }

        if ($shops === []) {
            return ['ok' => false, 'message' => $problem ?? __('The shop code :code is not valid for anything in your cart.', ['code' => $text])];
        }

        Session::put(self::SESSION_KEY, $codes);

        return ['ok' => true, 'message' => __('Shop code :code applied to your items from :shop.', ['code' => $text, 'shop' => implode(', ', $shops)])];
    }

    // ---- Working out a cart ---------------------------------------------------------------------

    /**
     * The shopper's cart with the codes in their session (signed-in customer limits checked).
     * Codes that no longer work are taken off, with the reason kept for the cart page.
     *
     * @return array{lines: array<int, array<string, mixed>>, codes: array<int, array{code: ShopDiscountCode, amount: float, eligible: float, shop: string}>, errors: array<int, array{code: string, message: string}>, unused: list<int>, multibuy_discount: float, code_discount: float, amount: float}
     */
    public function forCart(Cart $cart): array
    {
        $user = auth()->user();
        $deals = $this->evaluate($cart, $this->appliedCodes(), $user instanceof User ? $user : null);

        foreach ($deals['unused'] as $sellerId) {
            $this->forgetCode($sellerId);
        }
        if ($deals['errors'] !== []) {
            $notices = (array) Session::get(self::NOTICES_KEY, []);
            foreach ($deals['errors'] as $sellerId => $error) {
                $this->forgetCode($sellerId);
                $notices[$sellerId] = $error['message'];
            }
            Session::put(self::NOTICES_KEY, $notices);
        }

        return $deals;
    }

    /**
     * Work out the multi-buy savings and the given shop codes for a cart. Changes nothing.
     *
     * lines: cart item id => product_id, variant_id, seller_id, quantity, gross (price × quantity),
     *        multibuy, multibuy_percent, code (the line's share of its shop's code), net, next_tier
     *        ({more, percent}: units still to add for the next multi-buy tier) and group (the
     *        mix-and-match group's name). Money in MVR.
     * codes: seller_id => the code applied, what it took off and the eligible total it was worked on.
     * errors: seller_id => why that shop's code does not apply.
     * unused: shops whose code has nothing of theirs left in the cart.
     *
     * @param  array<int, string>  $codes  seller_id => code
     * @return array{lines: array<int, array<string, mixed>>, codes: array<int, array{code: ShopDiscountCode, amount: float, eligible: float, shop: string}>, errors: array<int, array{code: string, message: string}>, unused: list<int>, multibuy_discount: float, code_discount: float, amount: float}
     */
    public function evaluate(Cart $cart, array $codes, ?User $user = null, ?string $email = null): array
    {
        $cart->loadMissing(['items.product.multibuyOffer', 'items.variant']);

        $lines = [];
        foreach ($cart->items as $item) {
            $product = $item->product;
            if (! $product) {
                continue; // like Cart::total, a line whose product is gone counts for nothing
            }
            $sellerId = $product->seller_id ? (int) $product->seller_id : null;
            $offer = $product->multibuyOffer;
            $lines[$item->id] = [
                'product_id' => (int) $product->id,
                'variant_id' => $item->product_variant_id ? (int) $item->product_variant_id : null,
                'seller_id' => $sellerId,
                'quantity' => (int) $item->quantity,
                'gross' => self::toLaari($item->unit_price) * (int) $item->quantity,
                'offer' => $offer && $sellerId && (int) $offer->seller_id === $sellerId ? $offer : null,
                'multibuy' => 0,
                'multibuy_percent' => null,
                'next_tier' => null,
                'code' => 0,
            ];
        }

        // Multi-buy: units of the same offer count together (a product's variants, a mix-and-match group)
        $units = [];
        foreach ($lines as $line) {
            if ($line['offer']) {
                $units[$line['offer']->id] = ($units[$line['offer']->id] ?? 0) + $line['quantity'];
            }
        }
        foreach ($lines as $id => $line) {
            if (! $line['offer']) {
                continue;
            }
            $count = $units[$line['offer']->id];
            if ($tier = $line['offer']->tierFor($count)) {
                $lines[$id]['multibuy'] = self::percentOf($line['gross'], $tier['percent']);
                $lines[$id]['multibuy_percent'] = $tier['percent'];
            }
            if ($next = $line['offer']->nextTier($count)) {
                $lines[$id]['next_tier'] = ['more' => $next['min_qty'] - $count, 'percent' => $next['percent']];
            }
        }

        // Shop codes, one per shop, on what the shop's eligible lines cost after multi-buy
        $applied = [];
        $errors = [];
        $unused = [];
        $shopNames = $codes === [] ? collect() : User::whereIn('id', array_keys($codes))->get(['id', 'name', 'business_name'])->keyBy('id');
        foreach ($codes as $sellerId => $input) {
            $sellerId = (int) $sellerId;
            $shopLines = array_filter($lines, fn ($line) => $line['seller_id'] === $sellerId);
            if ($shopLines === []) {
                $unused[] = $sellerId;

                continue;
            }

            $shopName = (string) $shopNames->get($sellerId)?->shopName();
            $priced = $this->priceCode($sellerId, $shopName, (string) $input, $shopLines, $user, $email);
            if (isset($priced['error'])) {
                $errors[$sellerId] = ['code' => ShopDiscountCode::normalize((string) $input), 'message' => $priced['error']];

                continue;
            }

            foreach ($priced['shares'] as $id => $share) {
                $lines[$id]['code'] = $share;
            }
            $applied[$sellerId] = [
                'code' => $priced['code'],
                'amount' => $priced['amount'] / 100,
                'eligible' => $priced['eligible'] / 100,
                'shop' => $shopName,
            ];
        }

        $multibuy = 0;
        $codeTotal = 0;
        $out = [];
        foreach ($lines as $id => $line) {
            $multibuy += $line['multibuy'];
            $codeTotal += $line['code'];
            $out[$id] = [
                'product_id' => $line['product_id'],
                'variant_id' => $line['variant_id'],
                'seller_id' => $line['seller_id'],
                'quantity' => $line['quantity'],
                'gross' => $line['gross'] / 100,
                'multibuy' => $line['multibuy'] / 100,
                'multibuy_percent' => $line['multibuy_percent'],
                'code' => $line['code'] / 100,
                'net' => ($line['gross'] - $line['multibuy'] - $line['code']) / 100,
                'next_tier' => $line['next_tier'],
                'group' => $line['offer']?->isShared() ? $line['offer']->name : null,
            ];
        }

        return [
            'lines' => $out,
            'codes' => $applied,
            'errors' => $errors,
            'unused' => $unused,
            'multibuy_discount' => $multibuy / 100,
            'code_discount' => $codeTotal / 100,
            'amount' => ($multibuy + $codeTotal) / 100,
        ];
    }

    /**
     * One shop's code on that shop's lines: the discount and each eligible line's share (laari),
     * or why it does not apply.
     *
     * @param  array<int, array<string, mixed>>  $shopLines
     * @return array{error: string}|array{code: ShopDiscountCode, amount: int, eligible: int, shares: array<int, int>}
     */
    protected function priceCode(int $sellerId, string $shopName, string $input, array $shopLines, ?User $user, ?string $email): array
    {
        $text = ShopDiscountCode::normalize($input);
        $code = ShopDiscountCode::where('seller_id', $sellerId)->where('code', $text)
            ->with(['products' => fn ($q) => $q->select('products.id')])
            ->first();
        if (! $code) {
            return ['error' => __('The shop code :code is not valid.', ['code' => $text])];
        }
        if ($problem = $this->problem($code, $user, $email)) {
            return ['error' => $problem];
        }

        $eligible = array_filter($shopLines, fn ($line) => $code->covers($line['product_id']));
        if ($eligible === []) {
            return ['error' => __('The shop code :code does not cover the items in your cart.', ['code' => $code->code])];
        }

        $weights = array_map(fn ($line) => $line['gross'] - $line['multibuy'], $eligible);
        $base = array_sum($weights);
        $minimum = self::toLaari($code->min_spend);
        if ($minimum > 0 && $base < $minimum) {
            return ['error' => __('Spend :amount on eligible items from :shop to use the code :code (add :more more).', [
                'amount' => Money::format($minimum / 100),
                'shop' => $shopName,
                'code' => $code->code,
                'more' => Money::format(($minimum - $base) / 100),
            ])];
        }

        $discount = $code->isPercent() ? self::percentOf($base, (float) $code->value) : min(self::toLaari($code->value), $base);

        return ['code' => $code, 'amount' => $discount, 'eligible' => $base, 'shares' => self::allocate($discount, $weights)];
    }

    /**
     * Why this code can't be used right now (paused, not started, ended, used up, or used up by this
     * customer), or null. A customer is known by their account and email; a guest by the email given
     * at checkout. The cart's own conditions (minimum spend, products) are checked in priceCode().
     * $latest counts the newest committed uses (see countUses()).
     */
    public function problem(ShopDiscountCode $code, ?User $user = null, ?string $email = null, bool $latest = false): ?string
    {
        $name = ['code' => $code->code];

        return match (true) {
            ! $code->is_active => __('The shop code :code is not active right now.', $name),
            $code->starts_at !== null && $code->starts_at->isFuture() => __('The shop code :code can be used from :date.', $name + ['date' => $code->starts_at->translatedFormat('j M Y, H:i')]),
            $code->ends_at !== null && $code->ends_at->isPast() => __('The shop code :code has expired.', $name),
            $code->max_uses !== null && $this->usesCount($code, $latest) >= $code->max_uses => __('The shop code :code has been used the most times it can be.', $name),
            $code->max_uses_per_customer !== null && $this->customerUses($code, $user, $email, $latest) >= $code->max_uses_per_customer => __('You have already used the shop code :code as many times as it allows.', $name),
            default => null,
        };
    }

    /**
     * Uses on orders that still stand.
     */
    public function usesCount(ShopDiscountCode $code, bool $latest = false): int
    {
        return $this->countUses($code, null, $latest);
    }

    /**
     * This customer's uses: by account, or by email (a guest's, and the account's own address, so
     * signing up or checking out as a guest does not reset the limit).
     */
    public function customerUses(ShopDiscountCode $code, ?User $user, ?string $email, bool $latest = false): int
    {
        $emails = array_values(array_unique(array_filter([self::normalEmail($email), self::normalEmail($user?->email)])));
        if (! $user && $emails === []) {
            return 0;
        }

        return $this->countUses($code, function (Builder $query) use ($user, $emails) {
            $query->where(function (Builder $who) use ($user, $emails) {
                if ($user) {
                    $who->orWhere('user_id', $user->id);
                }
                if ($emails !== []) {
                    $who->orWhereIn('email', $emails);
                }
            });
        }, $latest);
    }

    /**
     * Uses of a code (narrowed to one customer by $who) on orders that were not cancelled.
     *
     * $latest is for the checkout transaction, which holds the code's row locked. There a plain read
     * sees the database as it was when the transaction began and could miss a use another checkout
     * has just committed, so every recorded use is counted with a locking read (the newest committed
     * rows), less those whose order was cancelled.
     *
     * @param  (\Closure(Builder<ShopDiscountRedemption>): void)|null  $who
     */
    protected function countUses(ShopDiscountCode $code, ?\Closure $who, bool $latest): int
    {
        $uses = function () use ($code, $who): Builder {
            $query = ShopDiscountRedemption::query()->where('shop_discount_code_id', $code->id);
            if ($who) {
                $who($query);
            }

            return $query;
        };

        if (! $latest) {
            return $uses()->counted()->count();
        }

        $recorded = $uses()->sharedLock()->count();
        $cancelled = $recorded > 0 ? $uses()->whereDoesntHave('order', fn (Builder $order) => $order->where('status', '!=', 'cancelled'))->count() : 0;

        return max(0, $recorded - $cancelled);
    }

    // ---- Placing the order ----------------------------------------------------------------------

    /**
     * Checkout checks the codes again, now for the person ordering (a guest by the email they gave).
     * A code that stopped working since the cart was shown (ended, paused, used up) refuses the order
     * with the reason. It has been taken off the cart, so the customer sees the new total first.
     *
     * @param  array{codes: array<int, array{code: ShopDiscountCode, amount: float, eligible: float, shop: string}>, errors: array<int, array{code: string, message: string}>}  $deals  forCart()'s result
     *
     * @throws RuntimeException
     */
    public function assertStillValid(array $deals, ?User $user, ?string $email): void
    {
        $problems = array_values(array_column($deals['errors'], 'message'));
        foreach ($deals['codes'] as $sellerId => $applied) {
            if ($problem = $this->problem($applied['code'], $user, $email)) {
                $this->forgetCode((int) $sellerId);
                $problems[] = $problem;
            }
        }

        if ($problems !== []) {
            Session::forget(self::NOTICES_KEY); // the refusal tells them
            throw new RuntimeException(self::refusal($problems[0]));
        }
    }

    /**
     * Put the shop discounts on a new order: each line's amounts on the order item made from it, the
     * total on the order, and one use of each code. Each code's row is locked first, so the last use
     * can't be taken twice. Runs inside the checkout transaction, after the items are created and
     * before the shops' parts are worked out from them.
     *
     * @param  array{lines: array<int, array<string, mixed>>, codes: array<int, array{code: ShopDiscountCode, amount: float, eligible: float, shop: string}>, amount: float}  $deals  forCart()'s result
     *
     * @throws RuntimeException when a code ran out while the order was being placed
     */
    public function applyToOrder(Order $order, array $deals, ?User $user, ?string $email): void
    {
        foreach ($deals['codes'] as $sellerId => $applied) {
            $locked = ShopDiscountCode::whereKey($applied['code']->id)->lockForUpdate()->first();
            $problem = $locked ? $this->problem($locked, $user, $email, true) : __('The shop code :code is not valid.', ['code' => $applied['code']->code]);
            if ($problem) {
                $this->forgetCode((int) $sellerId);
                throw new RuntimeException(self::refusal($problem));
            }
        }

        // Each cart line onto the order item made from it (same product and option, in cart order)
        $items = $order->items()->orderBy('id')->get();
        $matched = [];
        $sales = [];
        foreach ($deals['lines'] as $line) {
            $item = $items->first(fn ($i) => ! isset($matched[$i->id])
                && (int) $i->product_id === $line['product_id']
                && ($i->product_variant_id ? (int) $i->product_variant_id : null) === $line['variant_id']);
            if (! $item) {
                if ($line['multibuy'] > 0 || $line['code'] > 0) {
                    throw new LogicException("Order {$order->id} has no item for a discounted cart line.");
                }

                continue;
            }
            $matched[$item->id] = true;
            if ($line['multibuy'] > 0 || $line['code'] > 0) {
                $item->forceFill(['multibuy_discount' => $line['multibuy'], 'shop_code_discount' => $line['code']])->saveQuietly();
            }
            $sales[(int) $line['seller_id']] = ($sales[(int) $line['seller_id']] ?? 0) + self::toLaari($line['net']);
        }

        $email = self::normalEmail($email ?? $user?->email);
        foreach ($deals['codes'] as $sellerId => $applied) {
            ShopDiscountRedemption::create([
                'shop_discount_code_id' => $applied['code']->id,
                'code' => $applied['code']->code,
                'seller_id' => $sellerId,
                'order_id' => $order->id,
                'user_id' => $user?->id,
                'email' => $email,
                'amount' => $applied['amount'],
                'sales' => ($sales[$sellerId] ?? 0) / 100,
            ]);
        }

        if ($deals['amount'] > 0) {
            $order->forceFill(['shop_discount' => $deals['amount']])->save();
        }

        $this->forgetAll();
    }

    // ---- The shop's report ----------------------------------------------------------------------

    /**
     * Uses (orders that still stand), discount given and sales for each of these codes.
     *
     * @param  EloquentCollection<int, ShopDiscountCode>  $codes
     * @return array<int, array{uses: int, discount: float, sales: float}> code id => figures
     */
    public function stats(EloquentCollection $codes): array
    {
        $rows = ShopDiscountRedemption::query()->counted()
            ->whereIn('shop_discount_code_id', $codes->modelKeys())
            ->groupBy('shop_discount_code_id')
            ->selectRaw('shop_discount_code_id, COUNT(*) AS uses, SUM(amount) AS discount, SUM(sales) AS sales')
            ->toBase()
            ->get()
            ->keyBy('shop_discount_code_id');

        $stats = [];
        foreach ($codes as $code) {
            $row = $rows[$code->id] ?? null;
            $stats[$code->id] = [
                'uses' => (int) ($row->uses ?? 0),
                'discount' => round((float) ($row->discount ?? 0), 2),
                'sales' => round((float) ($row->sales ?? 0), 2),
            ];
        }

        return $stats;
    }

    // ---- Money --------------------------------------------------------------------------------

    public static function toLaari(float|int|string|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * Percent of an amount in laari, rounded half up to the laari (percent to 2 decimals).
     */
    public static function percentOf(int $laari, float $percent): int
    {
        return intdiv($laari * (int) round($percent * 100) + 5000, 10000);
    }

    /**
     * Share a total (laari) over weights in proportion, by the largest-remainder method: each share
     * is rounded down, and the laari left over go one each to the biggest remainders (ties: the
     * bigger weight, then the earlier line). The shares always add up to the total.
     *
     * @param  array<int, int>  $weights
     * @return array<int, int>
     */
    public static function allocate(int $total, array $weights): array
    {
        $shares = array_map(fn () => 0, $weights);
        $sum = array_sum($weights);
        if ($total <= 0 || $sum <= 0) {
            return $shares;
        }

        $order = [];
        $position = 0;
        foreach ($weights as $key => $weight) {
            $exact = $total * $weight;
            $shares[$key] = intdiv($exact, $sum);
            $order[] = ['key' => $key, 'rest' => $exact % $sum, 'weight' => $weight, 'position' => $position++];
        }
        usort($order, fn ($a, $b) => [$b['rest'], $b['weight'], $a['position']] <=> [$a['rest'], $a['weight'], $b['position']]);

        $left = $total - array_sum($shares);
        for ($i = 0; $i < $left; $i++) {
            $shares[$order[$i]['key']]++;
        }

        return $shares;
    }

    public static function normalEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    protected static function refusal(string $reason): string
    {
        return __('Your order was not placed: :reason We have taken the code off your cart, so please check your total and order again.', ['reason' => $reason]);
    }
}
