<?php

namespace App\Support;

use App\Models\ShopStaff;
use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

/**
 * The shop a Seller Centre request works on. A shop is its owner's account: the owner works on their
 * own shop, and a member of a shop's staff (shop_staff) works on the shop they belong to, within
 * their role (config/shop_staff.php). The role:seller middleware (App\Http\Middleware\ShopAccess)
 * checks the person and sets it on every seller route.
 *
 *   CurrentShop::get()   the shop (a User): its products, orders, settings ... ("the shop")
 *   auth()->user()       the person signed in: audit entries, message senders, review replies and
 *                        question answers record them ("who did it")
 *
 * It is worked out again on every request, so a removed staff member loses access on their next one.
 * For anyone else (a customer, an owner) it is simply the signed-in user, as before.
 *
 * A Seller Centre controller with a shop() helper is converted by its one line:
 *   protected function shop(): User { return \App\Support\CurrentShop::get(); }
 * and its route names are given to the staff roles that may open them in config/shop_staff.php.
 */
class CurrentShop
{
    private const KEY = 'iruali.current_shop';

    /**
     * Used by the role:seller middleware once it has let the request in.
     */
    public static function set(User $shop, ?ShopStaff $membership): void
    {
        $user = auth()->user();
        if ($user instanceof User) {
            self::remember(['user' => $user->getKey(), 'shop' => $shop, 'membership' => $membership]);
        }
    }

    /**
     * The shop this request works on: the signed-in owner, or the shop a staff member works for.
     */
    public static function get(): User
    {
        $state = self::state();
        abort_if($state === null, 403);

        return $state['shop'];
    }

    public static function id(): int
    {
        return (int) self::get()->getKey();
    }

    /** The signed-in person's place on the shop's staff; null for the owner (and everyone else). */
    public static function membership(): ?ShopStaff
    {
        return self::state()['membership'] ?? null;
    }

    public static function isStaff(): bool
    {
        return self::membership() !== null;
    }

    /** 'owner', or the staff member's role (manager, packer). */
    public static function role(): string
    {
        return (string) (self::membership()->role ?? 'owner');
    }

    /**
     * Can the signed-in person open this Seller Centre route for the shop? The owner: every page.
     * Staff: their role's pages. Views use it to hide links and sections (earnings, invoices...).
     */
    public static function can(string $routeName): bool
    {
        $membership = self::membership();

        return $membership === null || ShopStaffAccess::allows((string) $membership->role, $routeName);
    }

    /**
     * Can the signed-in person open this Seller Centre address (a link built elsewhere, e.g. the
     * onboarding checklist)?
     */
    public static function canOpenUrl(string $url): bool
    {
        if (! self::isStaff()) {
            return true;
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create($url));
        } catch (Throwable) {
            return false;
        }

        return self::can((string) $route->getName());
    }

    /**
     * The shop a user works for: their own (owners, and anyone not on a shop's staff) or the shop
     * whose staff they are.
     */
    public static function for(?User $user): ?User
    {
        if (! $user) {
            return null;
        }
        if ($user->hasRole('seller')) {
            return $user;
        }

        return self::membershipOf($user)->shop ?? $user;
    }

    /**
     * The user's place on a shop's staff, or null. Read from the database once per request.
     */
    public static function membershipOf(?User $user): ?ShopStaff
    {
        if (! $user) {
            return null;
        }
        $signedIn = auth()->user();
        if ($signedIn instanceof User && $signedIn->is($user)) {
            return self::membership();
        }

        return self::lookup($user);
    }

    /**
     * Is this user on the staff of this shop, while the shop is open (approved or pending, not
     * suspended or rejected), and may their role open this route? For places outside the Seller
     * Centre routes where the shop side acts: message photos, answering a question, the unread badge.
     */
    public static function worksFor(?User $user, int $shopId, ?string $routeName = null): bool
    {
        $membership = self::membershipOf($user);
        if (! $membership || $shopId === 0 || (int) $membership->shop_id !== $shopId) {
            return false;
        }

        $shop = $membership->shop;
        if (! $shop || ! $shop->hasRole('seller') || $shop->status === 'suspended') {
            return false;
        }

        return $routeName === null || ShopStaffAccess::allows((string) $membership->role, $routeName);
    }

    /**
     * Does the account menu offer this user the Seller Centre (a shop's owner or one of its staff)?
     */
    public static function opensSellerCentre(?User $user): bool
    {
        return $user !== null && ($user->hasRole('seller') || self::membershipOf($user) !== null);
    }

    /**
     * @return array{user: mixed, shop: User, membership: ?ShopStaff}|null
     */
    protected static function state(): ?array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        $cached = self::request()?->attributes->get(self::KEY);
        if (is_array($cached) && $cached['user'] === $user->getKey()) {
            return $cached;
        }

        if ($user->hasRole('seller')) {
            $state = ['user' => $user->getKey(), 'shop' => $user, 'membership' => null];
        } else {
            $membership = self::lookup($user);
            $state = ['user' => $user->getKey(), 'shop' => $membership->shop ?? $user, 'membership' => $membership];
        }
        self::remember($state);

        return $state;
    }

    /**
     * @param  array{user: mixed, shop: User, membership: ?ShopStaff}  $state
     */
    protected static function remember(array $state): void
    {
        self::request()?->attributes->set(self::KEY, $state);
    }

    protected static function lookup(User $user): ?ShopStaff
    {
        $membership = ShopStaff::with('shop')->where('user_id', $user->getKey())->first();

        // The shop's account is gone (deleted): there is nothing to work on
        return $membership?->shop ? $membership : null;
    }

    protected static function request(): ?Request
    {
        return app()->bound('request') ? app('request') : null;
    }
}
