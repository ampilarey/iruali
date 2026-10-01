<?php

namespace App\Policies;

use App\Models\ProductReview;
use App\Models\User;

class ProductReviewPolicy
{
    /**
     * Only the shop that sells the product may reply to a review of it.
     */
    public function reply(User $user, ProductReview $review): bool
    {
        return $review->canBeRepliedBy($user);
    }

    /**
     * Admins moderate replies.
     */
    public function removeReply(User $user, ProductReview $review): bool
    {
        return $user->isAdmin();
    }
}
