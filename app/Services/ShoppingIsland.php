<?php

namespace App\Services;

use App\Models\Island;
use App\Models\User;
use Illuminate\Support\Facades\Cookie;

/**
 * The island a customer shops for ("Delivering to: Malé") and how listings are shown for it:
 *  - delivers (default): what can be delivered to the island, from shops anywhere;
 *  - local: only shops based on the island;
 *  - all: everything, with "Doesn't deliver to <island>" labels.
 *
 * Never a gate: with no island chosen (first visit, search engines) every listing shows
 * everything. The choice is kept in the session and a long-lived cookie, and on the account of a
 * signed-in customer (other devices); with none of those, the default address's island counts.
 * The product page's delivery quote and checkout's new address start from the same island.
 */
class ShoppingIsland
{
    /** The same session key the product page's delivery quote always used. */
    public const SESSION_ISLAND = DeliveryService::SESSION_ISLAND;

    public const SESSION_VIEW = 'shopping_view';

    public const COOKIE_ISLAND = 'iruali_island';

    public const COOKIE_VIEW = 'iruali_view';

    /** "Where should we deliver?" was closed without choosing: don't ask again. */
    public const COOKIE_PROMPT = 'iruali_island_prompt';

    public const COOKIE_MINUTES = 60 * 24 * 365;

    public const VIEW_DELIVERS = 'delivers';

    public const VIEW_LOCAL = 'local';

    public const VIEW_ALL = 'all';

    public const VIEWS = [self::VIEW_DELIVERS, self::VIEW_LOCAL, self::VIEW_ALL];

    public function __construct(protected IslandDirectory $islands) {}

    /** The chosen island, or null (show everything). */
    public function island(): ?Island
    {
        $attributes = request()->attributes;
        if (! $attributes->has('shopping_island')) {
            $attributes->set('shopping_island', $this->resolveIsland());
        }

        return $attributes->get('shopping_island');
    }

    protected function resolveIsland(): ?Island
    {
        $user = $this->user();
        $candidates = [
            fn () => (int) session(self::SESSION_ISLAND),
            fn () => (int) $user?->shopping_island_id,
            fn () => (int) request()->cookie(self::COOKIE_ISLAND),
            fn () => (int) $user?->defaultAddress()?->island_id,
        ];
        foreach ($candidates as $candidate) {
            if ($island = $this->islands->find($candidate())) {
                return $island;
            }
        }

        return null;
    }

    /** delivers, local or all. */
    public function view(): string
    {
        $user = $this->user();
        foreach ([session(self::SESSION_VIEW), $user?->shopping_view, request()->cookie(self::COOKIE_VIEW)] as $view) {
            if (in_array($view, self::VIEWS, true)) {
                return $view;
            }
        }

        return self::VIEW_DELIVERS;
    }

    /** The island listings are narrowed for (none while showing everything). */
    public function filterIsland(): ?Island
    {
        return $this->view() === self::VIEW_ALL ? null : $this->island();
    }

    /** The island products are labelled for ("Delivers to …" / "Doesn't deliver to …"). */
    public function labelIsland(): ?Island
    {
        return $this->island();
    }

    /**
     * Choose the island (and, optionally, the view) for this browser and the signed-in account.
     */
    public function choose(Island $island, ?string $view = null): void
    {
        session()->put(self::SESSION_ISLAND, $island->id);
        Cookie::queue(Cookie::make(self::COOKIE_ISLAND, (string) $island->id, self::COOKIE_MINUTES));
        $user = $this->user();
        if ($user && (int) $user->shopping_island_id !== (int) $island->id) {
            $user->forceFill(['shopping_island_id' => $island->id])->save();
        }
        request()->attributes->set('shopping_island', $island);

        if ($view !== null) {
            $this->setView($view);
        }
    }

    public function setView(string $view): void
    {
        $view = in_array($view, self::VIEWS, true) ? $view : self::VIEW_DELIVERS;
        session()->put(self::SESSION_VIEW, $view);
        Cookie::queue(Cookie::make(self::COOKIE_VIEW, $view, self::COOKIE_MINUTES));
        $user = $this->user();
        if ($user && $user->shopping_view !== $view) {
            $user->forceFill(['shopping_view' => $view])->save();
        }
    }

    public function dismissPrompt(): void
    {
        session()->put(self::COOKIE_PROMPT, true);
        Cookie::queue(Cookie::make(self::COOKIE_PROMPT, '1', self::COOKIE_MINUTES));
    }

    /** Ask "Where should we deliver?": nothing chosen yet and the question not closed before. */
    public function shouldPrompt(): bool
    {
        return $this->island() === null && ! session(self::COOKIE_PROMPT) && ! request()->cookie(self::COOKIE_PROMPT);
    }

    /**
     * The choice as JSON (the picker, the phone app).
     *
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $island = $this->island();

        return [
            'island' => $island ? $this->islands->toArray($island) : null,
            'view' => $this->view(),
            'views' => self::VIEWS,
            'has_local_shops' => $island ? app(ShopCoverage::class)->hasLocalShops($island) : false,
        ];
    }

    /**
     * The view switch's three options for the island.
     *
     * @return array<string, string>
     */
    public static function viewLabels(Island $island): array
    {
        return [
            self::VIEW_DELIVERS => __('Delivers to :island', ['island' => $island->localized_name]),
            self::VIEW_LOCAL => __('Only shops on :island', ['island' => $island->localized_name]),
            self::VIEW_ALL => __('Show everything'),
        ];
    }

    protected function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
