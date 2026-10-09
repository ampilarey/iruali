<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Support\CurrentShop;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Everyone can view products (public listing)
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Product $product): bool
    {
        // Everyone can view active products
        if ($product->is_active) {
            return true;
        }

        // Only admins and the product's seller (its owner or staff) can view inactive products
        return $user->isAdmin() || CurrentShop::for($user)?->id === $product->seller_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only authenticated users who are sellers (or work for one) or admins can create products
        return CurrentShop::for($user)?->isSeller() || $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Product $product): bool
    {
        // Admins can update any product
        if ($user->isAdmin()) {
            return true;
        }

        // Sellers can only update their own products (the shop's, for its staff; their role is checked by the route)
        $shop = CurrentShop::for($user);
        if ($shop?->isSeller()) {
            return $shop->id === $product->seller_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Product $product): bool
    {
        // Admins can delete any product
        if ($user->isAdmin()) {
            return true;
        }

        // Sellers can only delete their own products (the shop's, for its staff; their role is checked by the route)
        $shop = CurrentShop::for($user);
        if ($shop?->isSeller()) {
            return $shop->id === $product->seller_id;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Product $product): bool
    {
        // Only admins can restore products
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        // Only admins can permanently delete products
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can approve the product.
     */
    public function approve(User $user, Product $product): bool
    {
        // Only admins can approve products
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can reject the product.
     */
    public function reject(User $user, Product $product): bool
    {
        // Only admins can reject products
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can feature the product.
     */
    public function feature(User $user, Product $product): bool
    {
        // Only admins can feature products
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can manage product variants.
     */
    public function manageVariants(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}
