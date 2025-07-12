<?php

namespace App\Policies;

use App\Models\Voucher;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class VoucherPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Only admins can view voucher management
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Voucher $voucher): bool
    {
        // Only admins can view voucher details
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only admins can create vouchers
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Voucher $voucher): bool
    {
        // Only admins can update vouchers
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Voucher $voucher): bool
    {
        // Only admins can delete vouchers
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Voucher $voucher): bool
    {
        // Only admins can restore vouchers
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Voucher $voucher): bool
    {
        // Only admins can permanently delete vouchers
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can apply vouchers to orders.
     */
    public function apply(User $user): bool
    {
        // Any authenticated user can apply vouchers to their orders
        return auth()->check();
    }

    /**
     * Determine whether the user can manage voucher usage.
     */
    public function manageUsage(User $user, Voucher $voucher): bool
    {
        // Only admins can manage voucher usage
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view voucher analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        // Only admins can view voucher analytics
        return $user->isAdmin();
    }
}
