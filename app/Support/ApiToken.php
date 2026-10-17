<?php

namespace App\Support;

use App\Models\User;

/**
 * Sanctum abilities for /api/v1. Login and registration issue the same set.
 * Routes check them; a token minted with "*" still passes.
 */
class ApiToken
{
    public const PROFILE_READ = 'profile:read';

    public const PROFILE_WRITE = 'profile:write';

    public const ORDER_READ = 'order:read';

    public const ORDER_WRITE = 'order:write';

    public const CART_WRITE = 'cart:write';

    public const WISHLIST_WRITE = 'wishlist:write';

    public const PRODUCTS_WRITE = 'products:write';

    /** @return list<string> */
    public static function forUser(User $user): array
    {
        $abilities = [
            self::PROFILE_READ,
            self::PROFILE_WRITE,
            self::ORDER_READ,
            self::ORDER_WRITE,
            self::CART_WRITE,
            self::WISHLIST_WRITE,
        ];

        if ($user->isSeller() || $user->isAdmin()) {
            $abilities[] = self::PRODUCTS_WRITE;
        }

        return $abilities;
    }
}
