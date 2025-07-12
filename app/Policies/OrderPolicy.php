<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admins can view all orders
        if ($user->isAdmin()) {
            return true;
        }

        // Sellers can view orders containing their products
        if ($user->isSeller()) {
            return true;
        }

        // Customers can view their own orders
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Order $order): bool
    {
        // Admins can view any order
        if ($user->isAdmin()) {
            return true;
        }

        // Sellers can view orders containing their products
        if ($user->isSeller()) {
            return $order->items()->whereHas('product', function ($query) use ($user) {
                $query->where('seller_id', $user->id);
            })->exists();
        }

        // Customers can only view their own orders
        return $user->id === $order->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Any authenticated user can create orders
        return auth()->check();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Order $order): bool
    {
        // Admins can update any order
        if ($user->isAdmin()) {
            return true;
        }

        // Sellers can update order status for orders containing their products
        if ($user->isSeller()) {
            return $order->items()->whereHas('product', function ($query) use ($user) {
                $query->where('seller_id', $user->id);
            })->exists();
        }

        // Customers can only update their own orders in certain states
        if ($user->id === $order->user_id) {
            // Customers can only update orders that are pending or processing
            return in_array($order->status, ['pending', 'processing']);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Order $order): bool
    {
        // Only admins can delete orders
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Order $order): bool
    {
        // Only admins can restore orders
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        // Only admins can permanently delete orders
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can cancel the order.
     */
    public function cancel(User $user, Order $order): bool
    {
        // Admins can cancel any order
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can only cancel their own orders in certain states
        if ($user->id === $order->user_id) {
            return in_array($order->status, ['pending', 'processing']);
        }

        return false;
    }

    /**
     * Determine whether the user can refund the order.
     */
    public function refund(User $user, Order $order): bool
    {
        // Only admins can process refunds
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update order status.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        // Admins can update any order status
        if ($user->isAdmin()) {
            return true;
        }

        // Sellers can update status for orders containing their products
        if ($user->isSeller()) {
            return $order->items()->whereHas('product', function ($query) use ($user) {
                $query->where('seller_id', $user->id);
            })->exists();
        }

        return false;
    }
}
