<?php

namespace App\Support;

use App\Models\User;

/**
 * Which admin routes a staff member may open (config/staff.php). Used by the StaffAccess
 * middleware and by the admin nav to hide links the person cannot follow.
 */
class StaffAccess
{
    /** @return string[] the staff roles this user has */
    public static function staffRoles(?User $user): array
    {
        if (! $user) {
            return [];
        }
        if (! $user->relationLoaded('roles')) {
            $user->load('roles');
        }

        return array_values(array_intersect(config('staff.roles', ['admin']), $user->roles->pluck('name')->all()));
    }

    public static function can(string $routeName, ?User $user = null): bool
    {
        $user ??= auth()->user();

        foreach (self::staffRoles($user) as $role) {
            foreach ((array) config("staff.access.{$role}", []) as $pattern) {
                if (self::matches($pattern, $routeName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** "admin.orders.*" is a prefix, anything else must match exactly. */
    public static function matches(string $pattern, string $routeName): bool
    {
        if (str_ends_with($pattern, '*')) {
            return str_starts_with($routeName, rtrim($pattern, '*'));
        }

        return $pattern === $routeName;
    }
}
