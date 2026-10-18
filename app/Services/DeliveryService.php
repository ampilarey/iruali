<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryRate;
use App\Models\Island;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerDeliverySetting;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Delivery fees by area (Admin → Delivery: Greater Malé, each atoll, other islands), shops' own
 * delivery fees, delivery estimates, bulky-item charges, pickup from the shop and gifts at checkout.
 *
 * Who is paid for delivery: a shop with its own fees (SellerDeliverySetting::hasOwnFees) delivers
 * its part itself and gets its fee and bulky-item charges with its earnings. iruali's area fee is
 * charged once per order when any other shop's part is delivered, and iruali keeps it and those
 * shops' bulky-item charges (Order::delivery_fee_shops is the shops' share of shipping_amount).
 */
class DeliveryService
{
    /** Islands charged the Greater Malé rate. */
    public const GREATER_MALE = ['male', 'malé', 'hulhumale', 'hulhumalé', 'villimale', 'villimalé', 'villingili', 'hulhule', 'hulhulé'];

    public static function zones(): array
    {
        return [
            'greater_male' => __('Greater Malé (Malé, Hulhumalé, Villimalé)'),
            'islands' => __('Other islands'),
        ];
    }

    /**
     * Pick the zone from what the customer chose (Greater Malé only when the island typed is in
     * Greater Malé), else guess it from the island name. Villingili
     * is the one Greater Malé name another atoll shares (Gaafu Alif): with an atoll other than
     * Kaafu it is that one. A typed atoll never changes the others (a wrong one is a typo).
     */
    public function zoneFor(?string $zone, ?string $city = null, ?string $atoll = null): string
    {
        // Accents and case ignored: "Male", "Malé" and "MALÉ" are all Malé
        $normal = \App\Support\IslandList::normalize($city);
        $atollKey = \App\Support\IslandList::normalizeAtoll($atoll);
        $byAddress = ($normal === 'villingili' && $atollKey !== '' && $atollKey !== 'kaafu')
            || ! ($normal !== '' && in_array($normal, array_map(\App\Support\IslandList::normalize(...), self::GREATER_MALE), true))
            ? 'islands' : 'greater_male';

        if ($zone && array_key_exists($zone, self::zones())) {
            // The Greater Malé fee only for a Greater Malé address: a posted zone can't claim it for an island
            return $zone === 'greater_male' && $normal !== '' && $byAddress !== 'greater_male' ? 'islands' : $zone;
        }

        return $byAddress;
    }

    /**
     * The zone for an island picked from the islands table (its English name decides), or for a
     * typed island name when none was picked. The atoll alone never decides: Kaafu has many islands
     * outside Greater Malé.
     */
    public function zoneForIsland(?\App\Models\Island $island, ?string $islandName = null): string
    {
        $name = $island
            ? ($island->getTranslation('name', 'en', false) ?: $island->getTranslation('name', config('app.fallback_locale'), false))
            : $islandName;

        return $this->zoneFor(null, $name, $island?->atoll);
    }

    /**
     * All active islands grouped by atoll, for address pickers: ['Kaafu' => Collection<Island>, ...].
     * Pass the active islands when they are already loaded.
     */
    public function islandsByAtoll(?\Illuminate\Support\Collection $activeIslands = null): \Illuminate\Support\Collection
    {
        return ($activeIslands ?? \App\Models\Island::where('is_active', true)->get())
            ->sortBy(fn ($i) => $i->localized_name)
            ->groupBy(fn ($i) => $i->atoll ?: __('Other'))
            ->sortKeys();
    }

    /**
     * Delivery fee for a zone, given the order's goods total after discounts.
     */
    public function fee(string $zone, float $goodsTotal): float
    {
        $freeOver = (float) Setting::get('free_delivery_over');
        if ($freeOver > 0 && $goodsTotal >= $freeOver) {
            return 0.0;
        }

        return (float) Setting::get($zone === 'greater_male' ? 'delivery_fee_greater_male' : 'delivery_fee_islands');
    }

    /**
     * Fees per zone for this goods total (for showing on the checkout page).
     */
    public function quotes(float $goodsTotal): array
    {
        return collect(self::zones())->mapWithKeys(fn ($label, $zone) => [$zone => $this->fee($zone, $goodsTotal)])->all();
    }

    // ---- Delivery areas: Greater Malé, each atoll, other islands ------------------------------

    public const AREA_GREATER_MALE = 'greater_male';

    /** Every island whose atoll has no row of its own. */
    public const AREA_ISLANDS = 'islands';

    public const RATES_CACHE = 'delivery_rates';

    /** Transit days (min, max) until the owner sets them on the Delivery page. */
    public const DEFAULT_TRANSIT_DAYS = [self::AREA_GREATER_MALE => [1, 2], self::AREA_ISLANDS => [2, 5]];

    /** The island the product page quotes delivery for (the shopper picks it; kept in the session). */
    public const SESSION_ISLAND = 'delivery_island_id';

    /**
     * The Delivery page's rows: area => fee (null = the default fee), min and max transit days.
     *
     * @return array<string, array{fee: ?float, min: ?int, max: ?int}>
     */
    public function rateTable(): array
    {
        return Cache::rememberForever(self::RATES_CACHE, fn () => DeliveryRate::query()->get()
            ->mapWithKeys(fn (DeliveryRate $rate) => [$rate->area => [
                'fee' => $rate->fee === null ? null : (float) $rate->fee,
                'min' => $rate->transit_days_min,
                'max' => $rate->transit_days_max,
            ]])->all());
    }

    /**
     * Atolls in the islands table (and any atoll that already has its own row), sorted.
     *
     * @return Collection<int, non-empty-string>
     */
    public function atolls(): Collection
    {
        $fromIslands = Island::where('is_active', true)->whereNotNull('atoll')->where('atoll', '!=', '')->distinct()->pluck('atoll');
        $fromRates = collect(array_keys($this->rateTable()))->reject(fn ($area) => in_array($area, [self::AREA_GREATER_MALE, self::AREA_ISLANDS], true));

        return $fromIslands->merge($fromRates)->map(fn ($atoll): string => trim((string) $atoll))->filter(fn (string $atoll) => $atoll !== '')
            ->unique(fn (string $atoll) => mb_strtolower($atoll))->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /**
     * Every delivery area with its label: Greater Malé, each atoll, then other islands.
     *
     * @return array<string, string>
     */
    public function areas(): array
    {
        $areas = [self::AREA_GREATER_MALE => __('Greater Malé')];
        foreach ($this->atolls() as $atoll) {
            $areas[$atoll] = $this->areaLabel($atoll);
        }
        $areas[self::AREA_ISLANDS] = __('Other islands');

        return $areas;
    }

    public function areaLabel(string $area): string
    {
        return match ($area) {
            self::AREA_GREATER_MALE => __('Greater Malé'),
            self::AREA_ISLANDS => __('Other islands'),
            default => __(':atoll Atoll', ['atoll' => $area]),
        };
    }

    /**
     * The area an address is charged for: Greater Malé when its zone is, else its atoll when the
     * atoll has its own row, else other islands.
     */
    public function areaFor(?string $zone, ?string $atoll): string
    {
        if ($zone === self::AREA_GREATER_MALE) {
            return self::AREA_GREATER_MALE;
        }

        $atoll = mb_strtolower(trim((string) $atoll));
        if ($atoll !== '') {
            foreach (array_keys($this->rateTable()) as $area) {
                if (! in_array($area, [self::AREA_GREATER_MALE, self::AREA_ISLANDS], true) && mb_strtolower($area) === $atoll) {
                    return $area;
                }
            }
        }

        return self::AREA_ISLANDS;
    }

    /**
     * Atolls with a fee of their own, for the delivery policy and help pages: label => fee.
     *
     * @return array<string, float>
     */
    public function atollFees(): array
    {
        $fees = [];
        foreach ($this->rateTable() as $area => $row) {
            if ($row['fee'] !== null && ! in_array($area, [self::AREA_GREATER_MALE, self::AREA_ISLANDS], true)) {
                $fees[$this->areaLabel($area)] = $row['fee'];
            }
        }
        ksort($fees, SORT_NATURAL | SORT_FLAG_CASE);

        return $fees;
    }

    public function areaForIsland(Island $island): string
    {
        return $this->areaFor($this->zoneForIsland($island), $island->atoll);
    }

    /**
     * The area's delivery fee before free delivery: Greater Malé and other islands come from
     * Settings; an atoll uses its own fee, or the other islands' fee when it has none.
     */
    public function areaFee(string $area): float
    {
        if ($area === self::AREA_GREATER_MALE) {
            return (float) Setting::get('delivery_fee_greater_male');
        }

        $own = $area === self::AREA_ISLANDS ? null : ($this->rateTable()[$area]['fee'] ?? null);

        return $own ?? (float) Setting::get('delivery_fee_islands');
    }

    /**
     * Free delivery: the order's goods total after discounts reaches the threshold in Settings.
     */
    public function freeDeliveryApplies(float $goodsTotal): bool
    {
        $freeOver = (float) Setting::get('free_delivery_over');

        return ($freeOver > 0 && $goodsTotal >= $freeOver) || \App\Support\Promotions::freeDelivery($goodsTotal);
    }

    /**
     * The area's fee for an order with this goods total (0 when delivery is free).
     */
    public function areaFeeFor(string $area, float $goodsTotal): float
    {
        return $this->freeDeliveryApplies($goodsTotal) ? 0.0 : $this->areaFee($area);
    }

    /**
     * Estimated days on the way to the area (min, max). An atoll without its own days uses the
     * other islands' days.
     *
     * @return array{0: int, 1: int}
     */
    public function transitDays(string $area): array
    {
        $row = $this->rateTable()[$area] ?? null;
        if (! $row || $row['min'] === null) {
            return $area === self::AREA_GREATER_MALE || $area === self::AREA_ISLANDS
                ? self::DEFAULT_TRANSIT_DAYS[$area]
                : $this->transitDays(self::AREA_ISLANDS);
        }

        return [$row['min'], max($row['min'], $row['max'] ?? $row['min'])];
    }

    /**
     * First and last day an order should arrive: the shop's dispatch days plus the transit days,
     * counted in plain calendar days.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function arrivalWindow(int $dispatchDays, string $area, ?CarbonInterface $from = null): array
    {
        [$min, $max] = $this->transitDays($area);
        $start = CarbonImmutable::instance($from ?? now())->startOfDay();

        return [$start->addDays($dispatchDays + $min), $start->addDays($dispatchDays + $max)];
    }

    /**
     * "Arrives 14–16 Oct".
     */
    public function arrivalText(int $dispatchDays, string $area, ?CarbonInterface $from = null): string
    {
        [$first, $last] = $this->arrivalWindow($dispatchDays, $area, $from);

        return __('Arrives :dates', ['dates' => self::dateRange($first, $last)]);
    }

    /**
     * "14 Oct", "14–16 Oct" or "30 Oct – 2 Nov".
     */
    public static function dateRange(CarbonInterface $first, CarbonInterface $last): string
    {
        if ($first->isSameDay($last)) {
            return $first->translatedFormat('j M');
        }
        if ($first->isSameMonth($last)) {
            return $first->translatedFormat('j').'–'.$last->translatedFormat('j M');
        }

        return $first->translatedFormat('j M').' – '.$last->translatedFormat('j M');
    }

    // ---- Shops in a cart, and placing the order -----------------------------------------------

    /**
     * The cart's items by shop, keyed by seller id (0 for none). Each shop is an array with
     * seller_id, name, seller (its User, if any), items (its cart items), units, items_total (MVR,
     * for its own free-delivery amount), surcharge (the bulky-item charges for all its items) and
     * setting (its SellerDeliverySetting).
     */
    public function cartShops(Cart $cart): Collection
    {
        $cart->loadMissing(['items.product.seller', 'items.variant']);
        $groups = $cart->items->filter(fn (CartItem $item) => $item->product !== null)
            ->groupBy(fn (CartItem $item) => (int) ($item->product->seller_id ?? 0));
        $settings = SellerDeliverySetting::forSellers($groups->keys());

        return $groups->map(function (Collection $items, int $sellerId) use ($settings) {
            $seller = $items->first()->product->seller;

            return [
                'seller_id' => $sellerId ?: null,
                'name' => $seller ? $seller->shopName() : 'iruali',
                'seller' => $seller,
                'items' => $items->values(),
                'units' => (int) $items->sum('quantity'),
                'items_total' => round((float) $items->sum(fn (CartItem $item) => (float) $item->unit_price * (int) $item->quantity), 2),
                'surcharge' => round((float) $items->sum(fn (CartItem $item) => (float) $item->product->delivery_surcharge * (int) $item->quantity), 2),
                'setting' => $settings[$sellerId] ?? new SellerDeliverySetting,
            ];
        });
    }

    /**
     * A shop's own fee for delivering its part to the area and island (its local fee when the
     * island is its home island), or null when it uses iruali's fees.
     *
     * @param  array<string, mixed>  $shop  one of cartShops()
     */
    public function shopFee(array $shop, string $area, ?Island $island): ?float
    {
        $seller = $shop['seller'] ?? null;
        $home = $island !== null && $seller instanceof User && $seller->home_island_id !== null && (int) $seller->home_island_id === (int) $island->id;

        return $shop['setting']->ownFee($area, $home, (float) ($shop['items_total'] ?? 0));
    }

    /**
     * Work out the order's delivery from the checkout choices: deliver or collect per shop (only
     * from shops that offer it), each delivered shop's own fee, iruali's area fee once when any
     * other shop's part is delivered (none when all is collected, no address needed then), the
     * delivered items' extra charges (never waived), the Malé time slot (re-checked and held under
     * a lock) and the gift details.
     *
     * Adds to the shipping data: shipping_amount, delivery_fee_shops (the shops' share of it),
     * delivery_area, delivery_surcharge, the slot, the gift fields and fulfilment_parts (per shop,
     * for applyToOrder()).
     *
     * @param  array<string, mixed>  $shippingData
     * @return array<string, mixed>
     */
    public function prepareOrder(Cart $cart, array $shippingData, float $goodsTotal): array
    {
        $choices = is_array($shippingData['fulfilment'] ?? null) ? $shippingData['fulfilment'] : [];
        $shops = $this->cartShops($cart);

        $parts = [];
        foreach ($shops as $sellerId => $shop) {
            $setting = $shop['setting'];
            $pickup = ($choices[$sellerId] ?? null) === SellerOrder::METHOD_PICKUP && $setting->offersPickup();
            $parts[$sellerId] = [
                'method' => $pickup ? SellerOrder::METHOD_PICKUP : SellerOrder::METHOD_DELIVER,
                'surcharge' => $pickup ? 0.0 : $shop['surcharge'],
                'pickup_address' => $pickup ? $setting->pickup_address : null,
                'pickup_island' => $pickup ? $setting->pickupIslandForOrder() : null,
                'pickup_hours' => $pickup ? $setting->pickup_hours : null,
                'fee' => 0.0,
                'to_shop' => false,
            ];
        }

        $delivered = collect($parts)->contains(fn (array $part) => $part['method'] === SellerOrder::METHOD_DELIVER);
        $surcharge = round((float) collect($parts)->sum('surcharge'), 2);

        // A shop's items can only be delivered to an island it reaches (a gift goes to the gift's
        // address, so its island decides); otherwise collect them or take them out of the cart
        $island = null;
        if ($delivered) {
            $island = $this->islandFor($shippingData);
            foreach ($parts as $sellerId => $part) {
                if ($part['method'] === SellerOrder::METHOD_DELIVER && ($message = $this->undeliverableMessage($shops[$sellerId], $island, $shippingData['shipping_city'] ?? null))) {
                    throw new \RuntimeException($message);
                }
            }
            if ($island) {
                $shippingData['island_id'] = $island->id;
            }
        }

        if ($delivered) {
            $area = $this->areaFor($shippingData['delivery_zone'] ?? null, $shippingData['shipping_state'] ?? null);

            // Shops with their own fees charge (and keep) them; iruali's fee once for the others
            $irualiDelivers = false;
            foreach ($parts as $sellerId => $part) {
                if ($part['method'] !== SellerOrder::METHOD_DELIVER) {
                    continue;
                }
                $own = $this->shopFee($shops[$sellerId], $area, $island);
                $parts[$sellerId]['fee'] = round($own ?? 0.0, 2);
                $parts[$sellerId]['to_shop'] = $own !== null;
                $irualiDelivers = $irualiDelivers || $own === null;
            }
            $base = $irualiDelivers ? $this->areaFeeFor($area, $goodsTotal) : 0.0;
        } else {
            // Everything is collected: no delivery fee, and no address needed (the phone still is)
            $area = null;
            $base = 0.0;
            $shippingData['delivery_zone'] = null;
            $placeholders = ['shipping_address' => __('Pickup from the shop'), 'shipping_city' => '', 'shipping_state' => '', 'shipping_country' => 'Maldives'];
            foreach ($placeholders as $field => $placeholder) {
                if (blank($shippingData[$field] ?? null)) {
                    $shippingData[$field] = $placeholder;
                }
            }
        }

        $shopsShare = round((float) collect($parts)->where('to_shop', true)->sum(fn (array $part) => $part['fee'] + $part['surcharge']), 2);
        $shippingData['delivery_area'] = $area;
        $shippingData['delivery_surcharge'] = $surcharge;
        $shippingData['delivery_fee_shops'] = $shopsShare;
        $shippingData['shipping_amount'] = round($base + (float) collect($parts)->sum('fee') + $surcharge, 2);
        $shippingData['fulfilment_parts'] = $parts;

        // A Malé time slot only applies to a delivery to Greater Malé while slots are on
        $slots = app(DeliverySlotService::class);
        $slotKey = trim((string) ($shippingData['delivery_slot'] ?? ''));
        $slot = $area === self::AREA_GREATER_MALE && $slotKey !== '' && $slots->enabled() ? $slots->reserve($slotKey) : null;
        $shippingData['delivery_slot_starts_at'] = $slot['starts_at'] ?? null;
        $shippingData['delivery_slot_ends_at'] = $slot['ends_at'] ?? null;

        $gift = filter_var($shippingData['is_gift'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $shippingData['is_gift'] = $gift;
        $shippingData['gift_receiver_name'] = $gift ? (trim((string) ($shippingData['gift_receiver_name'] ?? '')) ?: null) : null;
        $shippingData['gift_receiver_phone'] = $gift ? (preg_replace('/\s+/', '', (string) ($shippingData['gift_receiver_phone'] ?? '')) ?: null) : null;
        $shippingData['gift_message'] = $gift ? (mb_substr(trim((string) ($shippingData['gift_message'] ?? '')), 0, 200) ?: null) : null;
        $shippingData['gift_hide_prices'] = $gift && filter_var($shippingData['gift_hide_prices'] ?? true, FILTER_VALIDATE_BOOLEAN);

        return $shippingData;
    }

    /**
     * The island an order goes to: the one picked from the list (or the saved address's), else
     * the typed island name and atoll looked up in the list.
     *
     * @param  array<string, mixed>  $shippingData
     */
    public function islandFor(array $shippingData): ?Island
    {
        $id = (int) ($shippingData['island_id'] ?? 0);
        if ($id && ($island = Island::find($id))) {
            return $island;
        }

        return app(IslandDirectory::class)->findByName($shippingData['shipping_city'] ?? null, $shippingData['shipping_state'] ?? null);
    }

    /**
     * Why a shop's part can't be delivered to the island, or null when it can. An island typed
     * by hand that is not in the list can only be reached by shops that deliver everywhere.
     *
     * @param  array<string, mixed>  $shop  one of cartShops()
     */
    public function undeliverableMessage(array $shop, ?Island $island, ?string $typedIsland = null): ?string
    {
        $seller = $shop['items']->first()?->product?->seller;
        if (! $seller instanceof User) {
            return null;
        }
        $coverage = app(ShopCoverage::class);
        if ($island ? $coverage->canDeliverTo($seller, $island) : ($seller->deliveryCoverage() === User::COVERAGE_ALL || ! $seller->home_island_id)) {
            return null;
        }

        $where = $island ? $island->localized_name : (trim((string) $typedIsland) ?: __('this island'));

        return $shop['setting']->offersPickup()
            ? __(':shop doesn\'t deliver to :island. Choose "Pick up from :shop" at checkout, or remove its items from your cart.', ['shop' => $shop['name'], 'island' => $where])
            : __(':shop doesn\'t deliver to :island. Please remove its items from your cart, or deliver to another island.', ['shop' => $shop['name'], 'island' => $where]);
    }

    /**
     * Store what prepareOrder() worked out on the new order and its shop parts.
     *
     * @param  array<string, mixed>  $shippingData
     */
    public function applyToOrder(Order $order, array $shippingData): void
    {
        $order->forceFill([
            'delivery_area' => $shippingData['delivery_area'] ?? null,
            'delivery_surcharge' => $shippingData['delivery_surcharge'] ?? 0,
            'delivery_slot_starts_at' => $shippingData['delivery_slot_starts_at'] ?? null,
            'delivery_slot_ends_at' => $shippingData['delivery_slot_ends_at'] ?? null,
            'is_gift' => (bool) ($shippingData['is_gift'] ?? false),
            'gift_receiver_name' => $shippingData['gift_receiver_name'] ?? null,
            'gift_receiver_phone' => $shippingData['gift_receiver_phone'] ?? null,
            'gift_message' => $shippingData['gift_message'] ?? null,
            'gift_hide_prices' => (bool) ($shippingData['gift_hide_prices'] ?? false),
        ])->save();

        app(FulfilmentService::class)->applyDeliveryMethods($order, $shippingData['fulfilment_parts'] ?? []);
    }

    /**
     * Lines for a shop's "new order" email about how its part reaches the customer: collected
     * from the shop, the Malé time slot, a gift (pack it with the packing slip).
     *
     * @return list<string>
     */
    public function newOrderNotes(Order $order, ?int $sellerId): array
    {
        $part = $order->sellerOrders()->where('seller_id', $sellerId)->first();
        $notes = [];
        if ($part?->isPickup()) {
            $notes[] = __('The customer picks these items up from your shop, so there is nothing to send. Mark them ready for pickup in the Seller Centre and the customer gets a code to collect with.');
        } elseif ($order->deliverySlotLabel()) {
            $notes[] = __('Delivery time in Malé: :slot', ['slot' => $order->deliverySlotLabel()]);
        }
        if ($order->isGift()) {
            $notes[] = __('This is a gift for :name. Pack it with the packing slip from the Seller Centre.', ['name' => (string) $order->gift_receiver_name]);
        }

        return $notes;
    }

    // ---- Checkout page ------------------------------------------------------------------------

    /**
     * What the checkout's delivery sections show: each shop with its choices and estimates, the
     * area fees after free delivery, the fee for the current choices and the Malé time slots.
     *
     * @param  Collection<int, Address>|null  $addresses  the customer's saved addresses
     * @return array<string, mixed>
     */
    public function checkout(Cart $cart, float $goodsTotal, ?Address $selectedAddress = null, ?User $user = null, ?Collection $addresses = null): array
    {
        $choices = (array) old('fulfilment', []);
        $areas = $this->areas();

        // The island the order is going to as the page opens: the saved address's, else the island
        // picked before (or the customer's "Delivering to" island, which prefills a new address)
        $activeIslands = app(IslandDirectory::class)->active();
        $initialIsland = $selectedAddress
            ? ($selectedAddress->island_id ? $activeIslands->firstWhere('id', $selectedAddress->island_id) : app(IslandDirectory::class)->findByName($selectedAddress->island, $selectedAddress->atoll))
            : ($activeIslands->firstWhere('id', (int) old('island_id')) ?? app(ShoppingIsland::class)->island());

        if ($selectedAddress) {
            $area = $this->areaFor($selectedAddress->deliveryZone(), $selectedAddress->atoll);
        } else {
            $zone = $this->zoneFor(old('delivery_zone'), old('shipping_city', $user?->city), old('shipping_state', $user?->state));
            $area = $this->areaFor($zone, old('shipping_state', $user?->state));
        }

        $coverage = app(ShopCoverage::class);
        $shops = $this->cartShops($cart)->map(function (array $shop, int $sellerId) use ($choices, $areas, $coverage, $activeIslands, $initialIsland, $area) {
            $setting = $shop['setting'];
            $days = $setting->shipsWithinDays();
            $seller = $shop['items']->first()?->product?->seller;
            // The islands a shop that doesn't deliver everywhere reaches, for the page's script
            $reach = $seller instanceof User && $seller->deliveryCoverage() !== User::COVERAGE_ALL && $seller->home_island_id
                ? $activeIslands->filter(fn (Island $island) => $coverage->canDeliverTo($seller, $island))->pluck('id')->values()->all()
                : null;

            return [
                'key' => (string) $sellerId,
                'name' => $shop['name'],
                'units' => $shop['units'],
                'surcharge' => $shop['surcharge'],
                'items_total' => $shop['items_total'],
                // The shop's own delivery fees, for the page's script (null: iruali's fees)
                'own' => $setting->hasOwnFees() ? [
                    'home' => $seller instanceof User && $seller->home_island_id ? (int) $seller->home_island_id : null,
                    'local' => $setting->fee_local === null ? null : min((float) $setting->fee_local, SellerDeliverySetting::maxFee()),
                    'greater_male' => min((float) $setting->fee_greater_male, SellerDeliverySetting::maxFee()),
                    'islands' => min((float) $setting->fee_islands, SellerDeliverySetting::maxFee()),
                    'free_over' => $setting->free_over === null || (float) $setting->free_over <= 0 ? null : (float) $setting->free_over,
                ] : null,
                'fee' => $this->shopFee($shop, $area, $initialIsland),
                'pickup' => $setting->offersPickup() ? [
                    'address' => (string) $setting->pickup_address,
                    'island' => $setting->pickupIslandName(),
                    'hours' => (string) $setting->pickup_hours,
                    'open' => $setting->hoursText(),
                    'ready' => $days > 0 ? trans_choice('Usually ready to collect within :count day|Usually ready to collect within :count days', $days, ['count' => $days]) : __('Usually ready to collect the same day'),
                ] : null,
                'method' => $setting->offersPickup() && ($choices[$sellerId] ?? null) === SellerOrder::METHOD_PICKUP ? SellerOrder::METHOD_PICKUP : SellerOrder::METHOD_DELIVER,
                // Counted from the next day the shop works (its opening hours)
                'estimates' => collect($areas)->map(fn ($label, $key) => $this->arrivalText($days, (string) $key, $setting->nextWorkingDay()))->all(),
                'reach' => $reach,
                'blocked' => $reach !== null && $initialIsland !== null && ! in_array($initialIsland->id, $reach, true),
            ];
        })->values();

        $free = $this->freeDeliveryApplies($goodsTotal);
        $areaFees = collect($areas)->map(fn ($label, $key) => $free ? 0.0 : $this->areaFee((string) $key))->all();
        $delivered = $shops->contains('method', SellerOrder::METHOD_DELIVER);
        $surcharge = round((float) $shops->where('method', SellerOrder::METHOD_DELIVER)->sum('surcharge'), 2);

        // The delivery fees (before bulky-item charges) for an area: each delivering shop's own fee,
        // plus iruali's area fee once when any other shop delivers
        $cartShops = $this->cartShops($cart);
        $feesFor = function (string $key) use ($shops, $cartShops, $areaFees, $initialIsland): float {
            $total = 0.0;
            $iruali = false;
            foreach ($shops->where('method', SellerOrder::METHOD_DELIVER) as $shop) {
                $own = $this->shopFee($cartShops[(int) $shop['key']], $key, $initialIsland);
                $total += $own ?? 0.0;
                $iruali = $iruali || $own === null;
            }

            return round($total + ($iruali ? (float) ($areaFees[$key] ?? 0) : 0.0), 2);
        };
        $islandsArea = $area === self::AREA_GREATER_MALE ? self::AREA_ISLANDS : $area;

        // The atolls with their own rate, and the area of each saved address, for the page's script
        $atollAreas = collect(array_keys($areas))->reject(fn ($key) => in_array($key, [self::AREA_GREATER_MALE, self::AREA_ISLANDS], true))
            ->filter(fn ($key) => array_key_exists($key, $this->rateTable()))
            ->mapWithKeys(fn ($key) => [mb_strtolower($key) => $key])->all();
        $addressAreas = ($addresses ?? collect())->mapWithKeys(fn (Address $address) => [$address->id => $this->areaFor($address->deliveryZone(), $address->atoll)])->all();
        $slots = app(DeliverySlotService::class);
        $slotsEnabled = $slots->enabled();

        return [
            'shops' => $shops,
            'areas' => $areas,
            'areaFees' => $areaFees,
            'area' => $area,
            'free' => $free,
            'freeOver' => (float) Setting::get('free_delivery_over'),
            'delivered' => $delivered,
            'surcharge' => $surcharge,
            'fee' => $delivered ? round($feesFor($area) + $surcharge, 2) : 0.0,
            'deliveryFees' => $delivered ? $feesFor($area) : 0.0,
            // What each delivery-area radio costs as the page opens (the script keeps them up to date)
            'zoneFees' => [
                self::AREA_GREATER_MALE => $delivered ? round($feesFor(self::AREA_GREATER_MALE) + $surcharge, 2) : 0.0,
                self::AREA_ISLANDS => $delivered ? round($feesFor($islandsArea) + $surcharge, 2) : 0.0,
            ],
            'islandsArea' => $islandsArea,
            'atollAreas' => $atollAreas,
            'addressAreas' => $addressAreas,
            'slotsEnabled' => $slotsEnabled,
            'slotDays' => $slotsEnabled ? $slots->days() : collect(),
            'island' => $initialIsland,
            'addressIslands' => ($addresses ?? collect())->mapWithKeys(fn (Address $address) => [$address->id => [
                'id' => $address->island_id,
                'name' => ($found = $activeIslands->firstWhere('id', $address->island_id)) ? (string) $found->localized_name : (string) $address->island,
            ]])->all(),
        ];
    }

    // ---- Product page -------------------------------------------------------------------------

    /**
     * Where the product page quotes delivery to: the customer's "Delivering to" island
     * (ShoppingIsland: picked, saved on the account, or the default address's), else the default
     * address's typed island, else Greater Malé.
     *
     * @param  Collection<int, Island>|null  $activeIslands  when already loaded
     * @return array{label: string, area: string, island_id: ?int}
     */
    public function destination(?User $user, ?Collection $activeIslands = null): array
    {
        if ($island = app(ShoppingIsland::class)->island()) {
            return $this->destinationForIsland($island);
        }

        $islands = $activeIslands ?? app(IslandDirectory::class)->active();
        if ($user && ($address = $user->defaultAddress()) && filled($address->island)) {
            return ['label' => (string) $address->island, 'area' => $this->areaFor($address->deliveryZone(), $address->atoll), 'island_id' => null];
        }

        $male = $islands->first(fn (Island $island) => \App\Support\IslandList::normalize($island->getTranslation('name', 'en', false)) === 'male');

        return $male ? $this->destinationForIsland($male) : ['label' => __('Greater Malé'), 'area' => self::AREA_GREATER_MALE, 'island_id' => null];
    }

    /**
     * @return array{label: string, area: string, island_id: ?int}
     */
    protected function destinationForIsland(Island $island): array
    {
        return ['label' => (string) $island->localized_name, 'area' => $this->areaForIsland($island), 'island_id' => $island->id];
    }

    /**
     * The product page's "Delivery to <island>" box.
     *
     * @return array<string, mixed>
     */
    public function productBox(Product $product, ?User $user): array
    {
        $islands = app(IslandDirectory::class)->active();
        $destination = $this->destination($user, $islands);
        $setting = SellerDeliverySetting::for($product->seller_id);
        $days = $setting->shipsWithinDays();
        $chosen = app(ShoppingIsland::class)->island();

        // A shop with its own fees quotes them (before its free-delivery amount: one item rarely reaches it)
        $toHome = $chosen !== null && $product->seller?->home_island_id !== null && (int) $product->seller->home_island_id === (int) $chosen->id;
        $ownFee = $setting->ownFee($destination['area'], $toHome, 0.0);

        return [
            'destination' => $destination,
            'fee' => $ownFee ?? $this->areaFee($destination['area']),
            'freeOver' => $ownFee !== null ? (float) ($setting->free_over ?? 0) : (float) Setting::get('free_delivery_over'),
            'ownFees' => $ownFee !== null,
            'surcharge' => (float) $product->delivery_surcharge,
            // "Coming soon": counted from its launch day, and none without one
            'arrival' => $product->isComingSoon()
                ? ($product->available_on ? $this->arrivalText($days, $destination['area'], $product->available_on) : null)
                : $this->arrivalText($days, $destination['area'], $setting->nextWorkingDay()),
            'shipsWithinDays' => $days,
            'hours' => $setting->hoursText(),
            'pickup' => $setting->offersPickup() ? ['address' => (string) $setting->pickup_address, 'island' => $setting->pickupIslandName(), 'hours' => (string) $setting->pickup_hours] : null,
            'islandsByAtoll' => $this->islandsByAtoll($islands),
            // Whether this product's shop reaches the island (null: no island chosen, nothing to say)
            'delivers' => $chosen ? app(ShopCoverage::class)->deliversProduct($product, $chosen) : null,
        ];
    }
}
